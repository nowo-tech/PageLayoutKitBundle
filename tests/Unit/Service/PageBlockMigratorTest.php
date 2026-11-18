<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageCardItem;
use Nowo\PageLayoutKitBundle\Entity\PageCardsBlock;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlock;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Event\PageBlocksMigratedEvent;
use Nowo\PageLayoutKitBundle\Exception\PageMigrationInProgressException;
use Nowo\PageLayoutKitBundle\Legacy\LegacyPageContentProviderInterface;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Persistence\PageLayoutTransaction;
use Nowo\PageLayoutKitBundle\Persistence\PageMigrationLock;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use Nowo\PageLayoutKitBundle\Service\PageBlockMigrator;
use Nowo\PageLayoutKitBundle\Tests\Support\LocaleTestSupport;
use Nowo\PageLayoutKitBundle\Tests\Support\SqliteEntityManagerFactory;
use Nowo\TransactionContract\TransactionRunnerInterface;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_map;
use function array_values;
use function sort;

final class PageBlockMigratorTest extends TestCase
{
    public function testIsEmptyFollowsTheEnabledHomeLayout(): void
    {
        $manager  = SqliteEntityManagerFactory::create();
        $migrator = $this->migrator($manager);
        self::assertTrue($migrator->isEmpty());

        $this->entry($manager, 'home', 1);
        self::assertFalse($migrator->isEmpty());
    }

    public function testMigrateReturnsFalseWhenNoLegacyProviderIsAvailable(): void
    {
        $manager = SqliteEntityManagerFactory::create();

        self::assertFalse($this->migrator($manager, false)->migrate());
        self::assertSame(0, $this->rowCount($manager, PageLayoutEntry::class));
    }

    public function testMigrateBuildsBothPagesAndRecordsOneEventAfterTheCommits(): void
    {
        $manager  = SqliteEntityManagerFactory::create();
        $events   = [];
        $migrator = $this->migrator($manager, transaction: $this->transaction($manager, $events));

        self::assertTrue($migrator->migrate());

        $entries = $this->entries($manager);
        self::assertCount(14, $entries);
        self::assertSame(['home', PageBlockType::Hero], [$entries[0]->getPageKey(), $entries[0]->getBlockType()]);
        self::assertSame(['contact', PageBlockType::Cta], [$entries[13]->getPageKey(), $entries[13]->getBlockType()]);
        self::assertGreaterThan(0, $entries[0]->getBlockId());
        self::assertSame([[['home', 'contact'], false]], $events);
    }

    public function testMigrateWithoutForceKeepsPagesThatHaveALayout(): void
    {
        $manager = SqliteEntityManagerFactory::create();
        $this->entry($manager, 'home', 1);
        $this->entry($manager, 'contact', 1);
        $events = [];

        self::assertFalse($this->migrator($manager, transaction: $this->transaction($manager, $events))->migrate());
        self::assertSame(2, $this->rowCount($manager, PageLayoutEntry::class));
        self::assertSame([], $events);
    }

    public function testMigrateWithoutForceResumesARunStoppedBetweenTwoPages(): void
    {
        $manager = SqliteEntityManagerFactory::create();
        $this->entry($manager, 'home', 1);
        $events = [];

        self::assertTrue($this->migrator($manager, transaction: $this->transaction($manager, $events))->migrate());
        self::assertSame(1, $this->countPage($manager, 'home'), 'home is kept');
        self::assertSame(5, $this->countPage($manager, 'contact'));
        self::assertSame([[['contact'], false]], $events);
    }

    public function testAPageWithOnlyDisabledEntriesIsKeptWithoutForce(): void
    {
        $manager = SqliteEntityManagerFactory::create();
        $this->entry($manager, 'home', 1, false);
        $this->entry($manager, 'contact', 1);

        self::assertFalse($this->migrator($manager)->migrate());
        self::assertSame(1, $this->countPage($manager, 'home'));
        self::assertSame(1, $this->countPage($manager, 'contact'));
    }

    public function testMigrateForceReplacesTheLayoutAndTheBlocksOfThePagesOnly(): void
    {
        $manager = SqliteEntityManagerFactory::create();
        self::assertTrue($this->migrator($manager)->migrate());
        $this->entry($manager, 'about', 77);
        $events = [];

        self::assertTrue($this->migrator($manager, transaction: $this->transaction($manager, $events))->migrate(true));

        self::assertSame([9, 5, 1], [$this->countPage($manager, 'home'), $this->countPage($manager, 'contact'), $this->countPage($manager, 'about')]);
        self::assertSame(1, $this->rowCount($manager, PageHeroBlock::class), 'the old hero went with the old layout');
        self::assertSame(5, $this->rowCount($manager, PageTextBlock::class));
        self::assertSame([[['home', 'contact'], true]], $events);
    }

    public function testAnotherRunningMigrationRefusesWithNothingChanged(): void
    {
        $manager = SqliteEntityManagerFactory::create();
        $lock    = $this->createMock(PageMigrationLock::class);
        $lock->expects(self::once())->method('acquire')->with($manager->getConnection(), 'content_page_layout_entry')->willReturn(false);
        $lock->expects(self::never())->method('release');

        try {
            $this->migrator($manager, lock: $lock)->migrate();
            self::fail('A held migration lock must refuse the run.');
        } catch (PageMigrationInProgressException $e) {
            self::assertSame(PageMigrationInProgressException::MESSAGE, $e->getMessage());
        }

        self::assertSame(0, $this->rowCount($manager, PageLayoutEntry::class));
    }

    public function testAFailedPageKeepsTheCommittedOnesReleasesTheLockAndRethrows(): void
    {
        $manager = SqliteEntityManagerFactory::create();
        $calls   = 0;
        $runner  = $this->createMock(TransactionRunnerInterface::class);
        $runner->method('run')->willReturnCallback(static function (callable $operation) use (&$calls, $manager): mixed {
            if (++$calls === 2) {
                throw TransientStorageFailure::fromPrevious(new RuntimeException('deadlock'), 1);
            }

            $connection = $manager->getConnection();
            $connection->beginTransaction();
            $result = $operation();
            $manager->flush();
            $connection->commit();

            return $result;
        });
        $lock = $this->createMock(PageMigrationLock::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects(self::once())->method('release');
        $events = [];

        try {
            $this->migrator($manager, transaction: $this->transaction($manager, $events, $runner), lock: $lock)->migrate();
            self::fail('The transient failure must be rethrown.');
        } catch (TransientStorageFailure) {
        }

        self::assertSame([9, 0], [$this->countPage($manager, 'home'), $this->countPage($manager, 'contact')]);
        self::assertSame([[['home'], false]], $events, 'the committed page is announced');
    }

    public function testAFailureOfTheFirstPageAnnouncesNothing(): void
    {
        $manager = SqliteEntityManagerFactory::create();
        $runner  = $this->createStub(TransactionRunnerInterface::class);
        $runner->method('run')->willThrowException(new RuntimeException('down'));
        $events = [];

        try {
            $this->migrator($manager, transaction: $this->transaction($manager, $events, $runner))->migrate();
            self::fail('The failure must be rethrown.');
        } catch (RuntimeException $e) {
            self::assertSame('down', $e->getMessage());
        }

        self::assertSame([], $events);
    }

    public function testTheMigratorBuildsItsOwnTransactionWhenNoneIsGiven(): void
    {
        $manager = SqliteEntityManagerFactory::create();

        self::assertTrue(new PageBlockMigrator($manager, $this->repository($manager), LocaleTestSupport::defaults(), new FakeMigratorLegacyProvider($this->legacyContentMap()))->migrate());
        self::assertSame(14, $this->rowCount($manager, PageLayoutEntry::class));
    }

    public function testContentForPageReturnsEmptyArrayWhenNoLegacyProviderIsAvailable(): void
    {
        $method = new ReflectionMethod(PageBlockMigrator::class, 'contentForPage');

        self::assertSame([], $method->invoke($this->migrator(SqliteEntityManagerFactory::create(), false), 'home'));
    }

    public function testAddLayoutUsesTheIdOfABlockThatAlreadyHasOne(): void
    {
        $manager = SqliteEntityManagerFactory::create();
        $block   = new PageHeroBlock();
        $manager->persist($block);
        $manager->flush();

        new ReflectionMethod(PageBlockMigrator::class, 'addLayout')->invoke($this->migrator($manager), 'home', PageBlockType::Hero, $block, 4);
        $manager->flush();

        $entries = $this->entries($manager);
        self::assertCount(1, $entries);
        self::assertSame([$block->getId(), 4], [$entries[0]->getBlockId(), $entries[0]->getPosition()]);
    }

    public function testBlockIdIsNullForAnObjectWithoutGetId(): void
    {
        self::assertNull(new ReflectionMethod(PageBlockMigrator::class, 'blockId')->invoke($this->migrator(SqliteEntityManagerFactory::create()), new stdClass()));
    }

    public function testMigrateUsesTheInjectedPageLocales(): void
    {
        $manager = SqliteEntityManagerFactory::create();

        self::assertTrue($this->migrator($manager, locales: new PageLocales('es', ['es', 'fr']))->migrate(false));
        $manager->clear();

        /** @var list<PageHeroBlock> $heroBlocks */
        $heroBlocks = $manager->createQuery('SELECT b FROM ' . PageHeroBlock::class . ' b')->getResult();
        self::assertCount(1, $heroBlocks);
        $locales = array_map(
            static fn (PageHeroBlockTranslation $translation): string => $translation->getLocale(),
            array_values($heroBlocks[0]->getTranslations()->toArray()),
        );
        sort($locales);
        self::assertSame(['es', 'fr'], $locales);
        self::assertSame('Hero', $heroBlocks[0]->getTranslation('fr')?->getTitle());

        $cardsBlock = $manager->createQuery('SELECT b FROM ' . PageCardsBlock::class . " b WHERE b.sectionKey = 'value'")->getOneOrNullResult();
        self::assertInstanceOf(PageCardsBlock::class, $cardsBlock);
        $cardItem = $cardsBlock->getItems()->first();
        self::assertInstanceOf(PageCardItem::class, $cardItem);
        self::assertSame('V1', $cardItem->getTranslation('fr')?->getTitle());
        self::assertNull($cardItem->getTranslation('en'));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function legacyContentMap(): array
    {
        return [
            'home:es' => [
                'page_title'         => 'Inicio',
                'page_description'   => 'Meta inicio',
                'hero_eyebrow'       => 'Hola',
                'hero_title'         => 'Hero',
                'hero_subtitle'      => 'Sub',
                'hero_cta_primary'   => 'Ir',
                'hero_cta_secondary' => 'Mas',
                'problem_title'      => 'Problema',
                'problem_text'       => 'Texto problema',
                'value_title'        => 'Valor',
                'value_1_title'      => 'V1',
                'value_1_text'       => 'VT1',
                'value_2_title'      => 'V2',
                'value_2_text'       => 'VT2',
                'value_3_title'      => 'V3',
                'value_3_text'       => 'VT3',
                'value_4_title'      => 'V4',
                'value_4_text'       => 'VT4',
                'pain_title'         => 'Dolor',
                'pain_1_title'       => 'P1',
                'pain_1_text'        => 'PT1',
                'pain_2_title'       => 'P2',
                'pain_2_text'        => 'PT2',
                'pain_3_title'       => 'P3',
                'pain_3_text'        => 'PT3',
                'detect_title'       => 'Detectar',
                'detect_items'       => ['Uno', 'Dos'],
                'services_title'     => 'Servicios',
                'services_text'      => 'Texto servicios',
                'profile_title'      => 'Perfil',
                'profile_text'       => 'Texto perfil',
                'process_title'      => 'Proceso',
                'process_items'      => ['Paso 1', 'Paso 2'],
                'cta_title'          => 'CTA',
                'cta_text'           => 'Texto CTA',
            ],
            'home:en'    => [],
            'contact:es' => [
                'page_title'       => 'Contacto',
                'page_description' => 'Meta contacto',
                'h1'               => 'Hablemos',
                'intro'            => 'Intro contacto',
                'expect_title'     => 'Que esperar',
                'expect_text'      => 'Texto expect',
                'expect_items'     => ['Item A', 'Item B'],
                'before_label'     => 'Antes',
                'before_text'      => 'Texto antes',
                'after_label'      => 'Despues',
                'after_text'       => 'Texto despues',
                'form_submit'      => 'Enviar',
                'form_note'        => 'Nota form',
            ],
            'contact:en' => [],
        ];
    }

    private function migrator(
        EntityManagerInterface $manager,
        bool $withLegacy = true,
        ?PageLayoutTransaction $transaction = null,
        ?PageMigrationLock $lock = null,
        ?PageLocales $locales = null,
    ): PageBlockMigrator {
        return new PageBlockMigrator(
            $manager,
            $this->repository($manager),
            $locales ?? LocaleTestSupport::defaults(),
            $withLegacy ? new FakeMigratorLegacyProvider($this->legacyContentMap()) : null,
            $transaction ?? new PageLayoutTransaction($manager),
            $lock ?? new PageMigrationLock(),
        );
    }

    /**
     * @param list<array{0: list<string>, 1: bool}> $events
     */
    private function transaction(EntityManagerInterface $manager, array &$events, ?TransactionRunnerInterface $runner = null): PageLayoutTransaction
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PageBlocksMigratedEvent::class, static function (PageBlocksMigratedEvent $event) use (&$events): void {
            $events[] = [$event->pageKeys, $event->forced];
        });

        return new PageLayoutTransaction($manager, $dispatcher, runner: $runner);
    }

    private function repository(EntityManagerInterface $manager): PageLayoutEntryRepository
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        return new PageLayoutEntryRepository($registry);
    }

    private function entry(EntityManagerInterface $manager, string $pageKey, int $blockId, bool $enabled = true): void
    {
        $manager->persist(new PageLayoutEntry()->setPageKey($pageKey)->setBlockType(PageBlockType::Hero)->setBlockId($blockId)->setPosition(0)->setEnabled($enabled));
        $manager->flush();
    }

    /**
     * @return list<PageLayoutEntry>
     */
    private function entries(EntityManagerInterface $manager): array
    {
        return $this->repository($manager)->findBy([], ['id' => 'ASC']);
    }

    /**
     * @param class-string $class
     */
    private function rowCount(EntityManagerInterface $manager, string $class): int
    {
        return (int) $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $manager->getClassMetadata($class)->getTableName());
    }

    private function countPage(EntityManagerInterface $manager, string $pageKey): int
    {
        return $this->repository($manager)->count(['pageKey' => $pageKey]);
    }
}

final readonly class FakeMigratorLegacyProvider implements LegacyPageContentProviderInterface
{
    /**
     * @param array<string, array<string, mixed>> $contentByPageAndLocale
     */
    public function __construct(
        private array $contentByPageAndLocale,
        private string $defaultLocale = 'es',
    ) {
    }

    public function contentForPage(string $pageKey, string $locale): array
    {
        return $this->contentByPageAndLocale[$pageKey . ':' . $locale] ?? [];
    }

    public function defaultLocale(): string
    {
        return $this->defaultLocale;
    }
}
