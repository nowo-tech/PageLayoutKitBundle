<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Integration\Database;

use Closure;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlock;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlock;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlockTranslation;
use Nowo\PageLayoutKitBundle\EventListener\PageLayoutConflictListener;
use Nowo\PageLayoutKitBundle\Exception\PageMigrationInProgressException;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderData;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderRowData;
use Nowo\PageLayoutKitBundle\Legacy\LegacyPageContentProviderInterface;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Persistence\PageLayoutTransaction;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use Nowo\PageLayoutKitBundle\Service\PageBlockMigrator;
use Nowo\PageLayoutKitBundle\Service\PageLayoutReorderer;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\Dbal\CommitTrackingMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

use function array_intersect;
use function array_map;
use function array_reverse;
use function getenv;
use function is_string;
use function range;

use const CASE_LOWER;

/**
 * 2.3 (REQ-RUNTIME-003): two connections on real MySQL 8.4, MariaDB 11.4 and PostgreSQL 16 servers.
 *
 * Every test session sets a 2 s `innodb_lock_wait_timeout` / `lock_timeout`, so a lock wait fails fast instead of
 * hanging. Set PLK_MYSQL_URL, PLK_MARIADB_URL and PLK_POSTGRES_URL; CI runs the `database` group with
 * `--fail-on-skipped`.
 */
#[Group('database')]
final class PageLayoutConcurrencyDatabaseTest extends TestCase
{
    /** @var list<EntityManager> */
    private array $managers = [];

    /** @var array<string, mixed> */
    private array $params = [];

    protected function tearDown(): void
    {
        foreach ($this->managers as $manager) {
            $manager->getConnection()->close();
        }

        $this->managers = [];
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function databases(): iterable
    {
        foreach (['mysql' => 'PLK_MYSQL_URL', 'mariadb' => 'PLK_MARIADB_URL', 'postgres' => 'PLK_POSTGRES_URL'] as $name => $variable) {
            $url = getenv($variable);
            yield $name => [is_string($url) && $url !== '' ? $url : null];
        }
    }

    /**
     * Rule 1: each page is one write unit. A forced run replaces the pages whole: no duplicate entries and no
     * orphan blocks of the old layout.
     */
    #[DataProvider('databases')]
    public function testTheMigrationBuildsEachPageOnceAndAForcedRunReplacesThemWhole(?string $url): void
    {
        $manager = $this->freshDatabase($url);

        self::assertTrue($this->migrator($manager)->migrate());
        self::assertSame([9, 5], [$this->entries($manager, 'home'), $this->entries($manager, 'contact')]);
        self::assertFalse($this->migrator($manager)->migrate(), 'both pages have a layout');

        self::assertTrue($this->migrator($manager)->migrate(true));
        self::assertSame([9, 5], [$this->entries($manager, 'home'), $this->entries($manager, 'contact')]);
        self::assertSame(1, $this->rows($manager, PageHeroBlock::class), 'the old hero went with the old layout');
        self::assertSame(2, $this->rows($manager, PageCtaBlock::class));
    }

    /**
     * Admin edit against the migrator: an admin reorder holds the page's entries; the forced migration of that page
     * meets the lock (2 s), its unit rolls back whole and the command can simply run again. The admin's order is
     * committed, the page has no half-built layout, the migrator's connection is still usable.
     */
    #[DataProvider('databases')]
    public function testAForcedMigrationThatMeetsAnAdminReorderRollsBackThePageWhole(?string $url): void
    {
        $admin    = $this->freshDatabase($url);
        $migrator = $this->manager();
        $this->migrator($admin)->migrate();
        $before   = $this->ids($admin, 'home');
        $reversed = array_reverse($before);
        $failure  = null;

        // The admin's unit locks the page; the migration runs (and fails) while it is open.
        $this->transaction($admin)->run(function () use ($admin, $migrator, $reversed, &$failure): void {
            $entries = $this->repository($admin)->lockByPageKey('home');
            $this->order($reversed)->applyToEntries($entries);

            try {
                $this->migrator($migrator)->migrate(true);
            } catch (TransientStorageFailure $e) {
                $failure = $e;
            }
        });

        self::assertInstanceOf(TransientStorageFailure::class, $failure);
        self::assertSame($reversed, $this->idsByPosition($admin, 'home'), 'the admin order is committed');
        self::assertSame(1, $this->rows($migrator, PageHeroBlock::class), 'no block of the failed unit stays');
        self::assertSame(9, $this->entries($migrator, 'home'), 'migrator connection usable, page whole');
        self::assertSame(5, $this->entries($migrator, 'contact'), 'the next page was not started');

        self::assertTrue($this->migrator($migrator)->migrate(true), 'running it again succeeds');
        self::assertSame(9, $this->entries($migrator, 'home'));
        self::assertSame(1, $this->rows($migrator, PageHeroBlock::class));
    }

    /**
     * The migrator against an admin edit: while the migration's unit of `home` is open (old entries deleted, new
     * blocks inserted, not committed), an admin reorder of `home` meets the lock and answers 409 + Retry-After +
     * CONFLICT_001; nothing of the reorder is stored and the admin's connection is usable afterwards.
     */
    #[DataProvider('databases')]
    public function testAnAdminReorderThatMeetsTheMigrationAnswers409Conflict001(?string $url): void
    {
        $admin = $this->freshDatabase($url);
        $this->migrator($admin)->migrate();
        $old     = $this->ids($admin, 'home');
        $failure = null;

        $reorder = function () use ($admin, $old, &$failure): void {
            try {
                new PageLayoutReorderer($this->repository($admin), $this->transaction($admin))->reorder('home', $this->order(array_reverse($old)));
            } catch (TransientStorageFailure $e) {
                $failure = $e;
            }
        };
        $migrator = $this->manager(static function (EventManager $events) use ($reorder): void {
            $events->addEventListener(Events::postFlush, new FirstFlushHook($reorder));
        });

        self::assertTrue($this->migrator($migrator)->migrate(true));

        self::assertInstanceOf(TransientStorageFailure::class, $failure);
        $answer = $this->answer($failure, new PageLayoutConflictListener());
        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(['X-Conflict-Code' => ConflictCode::RETRY_SAFE, 'Retry-After' => '1'], $answer->getHeaders());
        self::assertSame(503, $this->answer($failure, new PageLayoutConflictListener(new ConflictResponsePolicy(503)))->getStatusCode(), 'host option');

        $new = $this->ids($admin, 'home');
        self::assertCount(9, $new, 'admin connection usable; the migrated layout is whole');
        self::assertSame([], array_intersect($old, $new), 'the old entries are gone, none was reordered');
        self::assertSame($new, $this->idsByPosition($admin, 'home'), 'the migration order, untouched by the failed reorder');
    }

    /**
     * Two admins: B read the page before A saved. With the lock and the re-read, B's order replaces A's whole; without
     * them B would only write the rows it changed against its stale copy, and the page would mix both orders.
     */
    #[DataProvider('databases')]
    public function testTwoReordersOfOnePageNeverMixTheirOrders(?string $url): void
    {
        $a = $this->freshDatabase($url);
        $b = $this->manager();
        $this->migrator($a)->migrate();
        $ids = $this->ids($a, 'home');

        $this->repository($b)->findEnabledByPageKey('home'); // B's stale copy
        new PageLayoutReorderer($this->repository($a), $this->transaction($a))->reorder('home', $this->order(array_reverse($ids)));

        $bOrder                  = $ids;
        [$bOrder[0], $bOrder[1]] = [$bOrder[1], $bOrder[0]];
        self::assertSame($bOrder, new PageLayoutReorderer($this->repository($b), $this->transaction($b))->reorder('home', $this->order($bOrder)));

        self::assertSame($bOrder, $this->idsByPosition($a, 'home'));
        self::assertSame(range(0, 8), $this->positions($a, 'home'), 'no duplicate position');
    }

    /**
     * "Only one migration": a second migration started while the first one is inside its `home` unit changes nothing
     * and fails fast; each page is built once.
     */
    #[DataProvider('databases')]
    public function testASecondMigrationAtTheSameTimeChangesNothing(?string $url): void
    {
        $this->freshDatabase($url);
        $second  = $this->manager();
        $refusal = null;

        $migrateAgain = function () use ($second, &$refusal): void {
            try {
                $this->migrator($second)->migrate();
            } catch (PageMigrationInProgressException $e) {
                $refusal = $e;
            }
        };
        $first = $this->manager(static function (EventManager $events) use ($migrateAgain): void {
            $events->addEventListener(Events::postFlush, new FirstFlushHook($migrateAgain));
        });

        self::assertTrue($this->migrator($first)->migrate());

        self::assertInstanceOf(PageMigrationInProgressException::class, $refusal);
        self::assertSame([9, 5], [$this->entries($second, 'home'), $this->entries($second, 'contact')]);
        self::assertSame(1, $this->rows($second, PageHeroBlock::class));
        self::assertTrue($this->migrator($second)->migrate(true), 'the lock was released');
    }

    /**
     * A lock conflict after a unit of the same request committed on the bundle's connection (counted by the real
     * commit-tracking middleware): CONFLICT_002, no Retry-After. The committed unit stays.
     */
    #[DataProvider('databases')]
    public function testALockConflictAfterACommittedUnitOfTheRequestAnswersConflict002(?string $url): void
    {
        $holder = $this->freshDatabase($url);
        $this->migrator($holder)->migrate();
        $ledger   = new CommitLedger();
        $admin    = $this->manager(null, $ledger);
        $listener = new PageLayoutConflictListener(null, $ledger);
        $request  = $this->request();
        $listener->onRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        // The first unit of the request commits ...
        $contact = array_reverse($this->ids($admin, 'contact'));
        new PageLayoutReorderer($this->repository($admin), $this->transaction($admin))->reorder('contact', $this->order($contact));
        self::assertGreaterThan(0, $ledger->count());

        // ... the second one meets the lock the other connection holds on `home`.
        $failure = null;
        $this->transaction($holder)->run(function () use ($holder, $admin, &$failure): void {
            $this->repository($holder)->lockByPageKey('home');

            try {
                new PageLayoutReorderer($this->repository($admin), $this->transaction($admin))->reorder('home', $this->order(array_reverse($this->ids($admin, 'home'))));
            } catch (TransientStorageFailure $e) {
                $failure = $e;
            }
        });

        self::assertInstanceOf(TransientStorageFailure::class, $failure);
        $answer = $this->answer($failure, $listener, $request);
        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(['X-Conflict-Code' => ConflictCode::COMMITTED], $answer->getHeaders());
        self::assertSame($contact, $this->idsByPosition($admin, 'contact'), 'the committed unit stays');
    }

    /**
     * Two editors add the same missing translation of one block: the unique key refuses the second insert. Not a lock
     * conflict: the answer is the domain 409 `PAGE_LAYOUT_CONCURRENT_EDIT`, never a CONFLICT_* code.
     */
    #[DataProvider('databases')]
    public function testTwoEditorsAddingTheSameTranslationAnswerTheDomain409(?string $url): void
    {
        $a     = $this->freshDatabase($url);
        $block = new PageTextBlock()->setSectionKey('race');
        $block->addTranslation(new PageTextBlockTranslation()->setLocale('es')->setTitle('es'));
        $a->persist($block);
        $a->flush();
        $id = $block->getId();

        $b      = $this->manager();
        $blockB = $b->find(PageTextBlock::class, $id);
        self::assertInstanceOf(PageTextBlock::class, $blockB);
        $blockB->ensureTranslations($this->locales());

        $block->ensureTranslations($this->locales());
        $a->flush();

        try {
            $b->flush();
            self::fail('The unique key must refuse the second translation.');
        } catch (Throwable $e) {
            $answer = $this->answer($e, new PageLayoutConflictListener());
        }

        self::assertSame(409, $answer->getStatusCode());
        self::assertSame([PageLayoutConflictListener::ERROR_CODE_HEADER => PageLayoutConflictListener::CONCURRENT_EDIT], $answer->getHeaders());
        self::assertSame(2, $this->rows($a, PageTextBlockTranslation::class));
    }

    private function freshDatabase(?string $url): EntityManager
    {
        if ($url === null) {
            self::markTestSkipped('Set PLK_MYSQL_URL, PLK_MARIADB_URL and PLK_POSTGRES_URL to run the real-database tests.');
        }

        $this->params = new DsnParser(['mysql' => 'pdo_mysql', 'pgsql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url);
        $manager      = $this->manager();
        $schemaTool   = new SchemaTool($manager);
        $metadata     = $manager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        return $manager;
    }

    /**
     * @param (callable(EventManager): void)|null $configureEvents
     */
    private function manager(?callable $configureEvents = null, ?CommitLedger $ledger = null): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../../../src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        $config->enableNativeLazyObjects(true);
        $dbal = new DbalConfiguration();
        if ($ledger instanceof CommitLedger) {
            $dbal->setMiddlewares([new CommitTrackingMiddleware($ledger)]);
        }

        $events = new EventManager();
        if ($configureEvents !== null) {
            $configureEvents($events);
        }

        $connection = DriverManager::getConnection($this->params, $dbal);
        $connection->executeStatement($connection->getDatabasePlatform() instanceof AbstractMySQLPlatform ? 'SET SESSION innodb_lock_wait_timeout = 2' : "SET lock_timeout = '2s'");
        $manager          = new EntityManager($connection, $config, $events);
        $this->managers[] = $manager;

        return $manager;
    }

    private function repository(EntityManager $manager): PageLayoutEntryRepository
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        return new PageLayoutEntryRepository($registry);
    }

    private function transaction(EntityManager $manager): PageLayoutTransaction
    {
        return new PageLayoutTransaction($manager);
    }

    private function migrator(EntityManager $manager): PageBlockMigrator
    {
        return new PageBlockMigrator($manager, $this->repository($manager), $this->locales(), new class implements LegacyPageContentProviderInterface {
            public function contentForPage(string $pageKey, string $locale): array
            {
                return ['hero_title' => $pageKey . ' ' . $locale, 'value_1_title' => 'v', 'detect_items' => ['a', 'b'], 'expect_items' => ['c']];
            }

            public function defaultLocale(): string
            {
                return 'es';
            }
        }, $this->transaction($manager));
    }

    private function locales(): PageLocales
    {
        return new PageLocales('es', ['es', 'en']);
    }

    /**
     * @param list<int> $ids
     */
    private function order(array $ids): PageLayoutReorderData
    {
        $data = new PageLayoutReorderData();
        foreach ($ids as $position => $id) {
            $data->rows[] = new PageLayoutReorderRowData($id, $position);
        }

        return $data;
    }

    private function entries(EntityManager $manager, string $pageKey): int
    {
        return (int) $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM content_page_layout_entry WHERE page_key = ?', [$pageKey]);
    }

    /**
     * @param class-string $class
     */
    private function rows(EntityManager $manager, string $class): int
    {
        return (int) $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $manager->getClassMetadata($class)->getTableName());
    }

    /**
     * @return list<int>
     */
    private function ids(EntityManager $manager, string $pageKey): array
    {
        return array_map(intval(...), $manager->getConnection()->fetchFirstColumn('SELECT id FROM content_page_layout_entry WHERE page_key = ? ORDER BY id', [$pageKey]));
    }

    /**
     * @return list<int>
     */
    private function idsByPosition(EntityManager $manager, string $pageKey): array
    {
        return array_map(intval(...), $manager->getConnection()->fetchFirstColumn('SELECT id FROM content_page_layout_entry WHERE page_key = ? ORDER BY position, id', [$pageKey]));
    }

    /**
     * @return list<int>
     */
    private function positions(EntityManager $manager, string $pageKey): array
    {
        return array_map(intval(...), $manager->getConnection()->fetchFirstColumn('SELECT position FROM content_page_layout_entry WHERE page_key = ? ORDER BY position', [$pageKey]));
    }

    private function request(): Request
    {
        $request = Request::create('/admin/pages/home/layout', 'POST');
        $request->attributes->set('_controller', 'Nowo\\PageLayoutKitBundle\\Controller\\Admin\\PageLayoutController::layout');

        return $request;
    }

    private function answer(Throwable $failure, PageLayoutConflictListener $listener, ?Request $request = null): HttpException
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request ?? $this->request(), HttpKernelInterface::MAIN_REQUEST, $failure);
        $listener->onException($event);
        $answer = $event->getThrowable();
        self::assertInstanceOf(HttpException::class, $answer);

        return $answer;
    }
}

/**
 * Doctrine postFlush listener that runs a callback once, on the first flush: the point where a write unit holds its
 * row locks and has not committed yet.
 */
final class FirstFlushHook
{
    private bool $done = false;

    public function __construct(private readonly Closure $onFirstFlush)
    {
    }

    public function postFlush(): void
    {
        if (!$this->done) {
            $this->done = true;
            ($this->onFirstFlush)();
        }
    }
}
