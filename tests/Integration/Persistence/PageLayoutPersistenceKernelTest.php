<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Integration\Persistence;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Event\PageLayoutChangedEvent;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderType;
use Nowo\PageLayoutKitBundle\Persistence\PageLayoutTransaction;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use Nowo\PageLayoutKitBundle\Tests\Fixtures\Host\HostNote;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelEvents;

use function array_map;
use function dirname;
use function memory_get_usage;

/**
 * Rule 6/7 through a real kernel (2.2): which entity manager the admin writes use and flush, the dedicated
 * `doctrine.entity_manager`, the write events and a kept-alive kernel driven through the `http_kernel` service
 * (FrankenPHP worker mode without kernel reset, no services resetter).
 */
final class PageLayoutPersistenceKernelTest extends TestCase
{
    private ?PersistenceTestKernel $kernel = null;

    /** @var list<PageLayoutChangedEvent> */
    private array $events = [];

    /** @var list<bool> */
    private array $inTransaction = [];

    /** @var list<int> */
    private array $entryIds = [];

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testStandardHostReordersOnTheDefaultManagerAndDispatchesAfterCommit(): void
    {
        $container = $this->boot($this->singleManager(), []);
        $default   = $this->manager($container, 'default');
        self::assertSame($default, $container->get('nowo_page_layout_kit.entity_manager'));

        $response = $this->reorder($container, [10, 5]);
        self::assertSame(302, $response->getStatusCode());

        self::assertSame([10, 5], $this->positions($default));
        self::assertCount(1, $this->events);
        self::assertSame('home', $this->events[0]->pageKey);
        self::assertSame([$this->entryIds[1], $this->entryIds[0]], $this->events[0]->entryIds, 'IDs in the new order');
        self::assertSame([false], $this->inTransaction);
    }

    public function testDedicatedManagerWithAutoMappingOnDefaultKeepsTheHostUnitOfWorkUntouched(): void
    {
        $container = $this->boot($this->twoManagers(autoMapping: true), ['entity_manager' => 'page_layout']);
        $registry  = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $default = $this->manager($container, 'default');
        $bundle  = $this->manager($container, 'page_layout');

        self::assertTrue($default->getMetadataFactory()->isTransient(PageLayoutEntry::class));
        self::assertSame($bundle, $registry->getManagerForClass(PageLayoutEntry::class));
        self::assertSame('default', $registry->getDefaultManagerName());

        $note = new HostNote('pending host change');
        $default->persist($note);

        self::assertSame(302, $this->reorder($container, [7, 3])->getStatusCode());
        self::assertSame([7, 3], $this->positions($bundle));
        self::assertSame([], $default->getConnection()->fetchFirstColumn('SELECT text FROM host_note'), 'the host change is not flushed by the bundle');
        self::assertTrue($default->getUnitOfWork()->isScheduledForInsert($note));
        self::assertCount(1, $this->events);

        $layout = $this->httpKernel($container)->handle(Request::create('/t/layout'));
        self::assertSame(200, $layout->getStatusCode(), 'public read (batch SQL) runs on the bundle connection');
    }

    /**
     * DoctrineBundle 3.4 regression: `auto_discover_entities` (on by default for a manager without `mappings` and
     * `auto_mapping`) gives the host manager's chain a default attribute driver, which claims every `#[ORM\Entity]`
     * class, so `getManagerForClass()` answered the host manager and the repositories queried its database. The
     * explicit catch-all default driver reproduces it on every DoctrineBundle version.
     */
    #[DataProvider('hostManagersWithADefaultDriver')]
    public function testDedicatedManagerWinsOverADefaultDriverOfTheHostManager(bool $explicitDefaultDriver): void
    {
        $doctrine                                      = $this->twoManagers(autoMapping: false);
        $doctrine['orm']['entity_managers']['default'] = ['connection' => 'default', 'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware'];
        $resolvedBeforeInit                            = [];
        $container                                     = $this->boot($doctrine, ['entity_manager' => 'page_layout'], $explicitDefaultDriver, static function (ManagerRegistry $registry) use (&$resolvedBeforeInit): void {
            // No manager is initialized yet: DoctrineBundle 3.4 asks the metadata drivers.
            $resolvedBeforeInit = [$registry->getManagerForClass(PageLayoutEntry::class), $registry->getManagerForClass(PageHeroBlock::class)];
        });
        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $default = $this->manager($container, 'default');
        $bundle  = $this->manager($container, 'page_layout');

        self::assertSame([$bundle, $bundle], $resolvedBeforeInit);
        self::assertTrue($default->getMetadataFactory()->isTransient(PageLayoutEntry::class), 'not mapped on the host manager');
        self::assertSame($bundle, $registry->getManagerForClass(PageLayoutEntry::class));
        self::assertSame($bundle, $registry->getManagerForClass(PageHeroBlock::class));

        self::assertSame(302, $this->reorder($container, [7, 3])->getStatusCode());
        self::assertSame([7, 3], $this->positions($bundle));
        self::assertNotContains($bundle->getClassMetadata(PageLayoutEntry::class)->getTableName(), $default->getConnection()->createSchemaManager()->listTableNames());
        $repository = $container->get(PageLayoutEntryRepository::class);
        self::assertInstanceOf(PageLayoutEntryRepository::class, $repository);
        self::assertCount(2, $repository->findEnabledByPageKey('home'), 'the repository reads the bundle database');
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function hostManagersWithADefaultDriver(): iterable
    {
        yield 'host manager without mappings (DoctrineBundle 3.4 auto-discovery)' => [false];
        yield 'host manager with a catch-all default driver' => [true];
    }

    public function testManagersWithoutDefaultEntityManagerKeepTheHostDefault(): void
    {
        $container = $this->boot($this->twoManagers(autoMapping: false, defaultName: false), ['entity_manager' => 'page_layout']);
        $registry  = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);

        self::assertSame('default', $registry->getDefaultManagerName());
        self::assertTrue($this->manager($container, 'default')->getMetadataFactory()->isTransient(PageLayoutEntry::class));
        self::assertSame($this->manager($container, 'page_layout'), $registry->getManagerForClass(PageLayoutEntry::class));
    }

    public function testUnknownEntityManagerFailsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_page_layout_kit.doctrine.entity_manager is "missing"');

        $this->boot($this->singleManager(), ['entity_manager' => 'missing']);
    }

    public function testKeptAliveKernelRecoversTheOwnManagerAndKeepsMemoryFlat(): void
    {
        $container = $this->boot($this->twoManagers(autoMapping: true), ['entity_manager' => 'page_layout']);
        $kernel    = $this->httpKernel($container);
        $default   = $this->manager($container, 'default');
        $bundle    = $this->manager($container, 'page_layout');
        $note      = new HostNote('host change survives');
        $default->persist($note);

        $request  = Request::create('/t/fail');
        $response = $kernel->handle($request);
        self::assertSame(500, $response->getStatusCode());
        $kernel->terminate($request, $response);
        self::assertFalse($bundle->isOpen(), 'the failed write closed the bundle manager');

        self::assertSame(302, $this->reorder($container, [2, 1])->getStatusCode());
        self::assertTrue($bundle->isOpen());
        self::assertSame([2, 1], $this->positions($bundle));
        self::assertSame(0, $bundle->getUnitOfWork()->size(), 'no managed entry survives the request');
        self::assertTrue($default->getUnitOfWork()->isScheduledForInsert($note), 'the host manager is never reset nor cleared');

        $baseline = 0;
        for ($i = 0; $i < 150; ++$i) {
            self::assertSame(302, $this->reorder($container, [$i, $i + 1])->getStatusCode());
            self::assertSame(0, $bundle->getUnitOfWork()->size());
            if ($i === 30) {
                $baseline = memory_get_usage();
            }
        }

        self::assertCount(151, $this->events);
        self::assertLessThan(4 * 1024 * 1024, memory_get_usage() - $baseline, 'memory stays flat over many requests');
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function layouts(): iterable
    {
        yield 'single' => [false];
        yield 'ddd' => [true];
    }

    #[DataProvider('layouts')]
    public function testHostRollbackAroundTheRequestDispatchesNothing(bool $dedicated): void
    {
        $container = $dedicated
            ? $this->boot($this->twoManagers(autoMapping: true), ['entity_manager' => 'page_layout'])
            : $this->boot($this->singleManager(), []);
        $bundle = $container->get('nowo_page_layout_kit.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $bundle);
        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        // Host listeners: a transaction around the request, rolled back at kernel.response (dry run / failed check).
        $begin = static function (RequestEvent $event) use ($bundle): void {
            if ($event->isMainRequest()) {
                $bundle->getConnection()->beginTransaction();
            }
        };
        $rollback = static function (ResponseEvent $event) use ($bundle): void {
            if ($event->isMainRequest()) {
                $bundle->getConnection()->rollBack();
                $bundle->clear();
            }
        };
        $dispatcher->addListener(KernelEvents::REQUEST, $begin, 1024);
        $dispatcher->addListener(KernelEvents::RESPONSE, $rollback, -1024);

        self::assertSame(302, $this->reorder($container, [10, 5])->getStatusCode());
        self::assertSame([1, 2], $this->positions($bundle), 'rolled back');
        self::assertSame([], $this->events, 'LH-PLK-M1: no event for the rolled-back reorder');

        $dispatcher->removeListener(KernelEvents::REQUEST, $begin);
        $dispatcher->removeListener(KernelEvents::RESPONSE, $rollback);
        self::assertSame(302, $this->reorder($container, [10, 5])->getStatusCode());
        self::assertSame([10, 5], $this->positions($bundle));
        self::assertSame(['home'], $this->eventKeys());
        self::assertSame([false], $this->inTransaction);
    }

    public function testSavepointRollbackDropsOnlyItsEvents(): void
    {
        $container   = $this->boot($this->singleManager(), []);
        $transaction = $container->get(PageLayoutTransaction::class);
        self::assertInstanceOf(PageLayoutTransaction::class, $transaction);
        $connection = $this->manager($container, 'default')->getConnection();

        $connection->beginTransaction();
        $transaction->commit(new PageLayoutChangedEvent('kept', [1]));
        $connection->beginTransaction(); // savepoint
        $transaction->commit(new PageLayoutChangedEvent('dropped', [2]));
        $connection->rollBack();
        self::assertSame(1, $transaction->countPending());
        $connection->commit();
        self::assertSame([], $this->events, 'not from inside the DBAL commit');
        self::assertSame(1, $transaction->dispatchPending());

        self::assertSame(['kept'], $this->eventKeys());
        self::assertSame([false], $this->inTransaction);
    }

    #[DataProvider('layouts')]
    public function testOptionNamingTheHostDefaultNeverClearsOrResetsIt(bool $twoManagers): void
    {
        $container = $this->boot($twoManagers ? $this->twoManagers(autoMapping: false) : $this->singleManager(), ['entity_manager' => 'default']);
        $kernel    = $this->httpKernel($container);
        $default   = $this->manager($container, 'default');

        $note = new HostNote('pending host change');
        $default->persist($note);
        $request  = Request::create('/t/layout');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        self::assertTrue($default->getUnitOfWork()->isScheduledForInsert($note), 'LH-PLK-M2: the host default is not cleared');

        $request  = Request::create('/t/fail');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        self::assertFalse($default->isOpen());

        $request = Request::create('/t/layout');
        $kernel->terminate($request, $kernel->handle($request));
        self::assertFalse($default->isOpen(), 'LH-PLK-M2: the host default is not reset');
    }

    public function testDefaultPathNeverResetsTheHostManager(): void
    {
        $container = $this->boot($this->singleManager(), []);
        $kernel    = $this->httpKernel($container);
        $default   = $this->manager($container, 'default');

        $request  = Request::create('/t/fail');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        self::assertFalse($default->isOpen());

        self::assertSame(500, $this->reorder($container, [1, 2])->getStatusCode(), 'the host owns its manager: the bundle does not reset it');
        self::assertFalse($default->isOpen());
        self::assertSame([], $this->events);
    }

    /**
     * Page keys of the dispatched events (a method call: PHPStan does not narrow it from earlier assertions).
     *
     * @return list<string>
     */
    private function eventKeys(): array
    {
        return array_map(static fn (PageLayoutChangedEvent $e): string => $e->pageKey, $this->events);
    }

    /**
     * POSTs the admin reorder form through `http_kernel` (handle + terminate).
     *
     * @param array{int, int} $positions
     */
    private function reorder(ContainerInterface $container, array $positions): Response
    {
        $forms = $container->get('form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $name = $forms->create(PageLayoutReorderType::class)->getName();

        $request = Request::create('/admin/pages/home/layout', 'POST', [$name => ['rows' => [
            ['id' => (string) $this->entryIds[0], 'position' => (string) $positions[0]],
            ['id' => (string) $this->entryIds[1], 'position' => (string) $positions[1]],
        ]]]);
        $kernel   = $this->httpKernel($container);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }

    /**
     * @param array<string, mixed> $doctrine
     * @param array<string, mixed> $bundleDoctrine
     * @param (Closure(ManagerRegistry): void)|null $beforeSchema runs before any manager is initialized
     */
    private function boot(array $doctrine, array $bundleDoctrine, bool $hostDefaultDriver = false, ?Closure $beforeSchema = null): ContainerInterface
    {
        $this->kernel = new PersistenceTestKernel($doctrine, $bundleDoctrine, $hostDefaultDriver);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        if ($beforeSchema instanceof Closure) {
            $beforeSchema($registry);
        }
        foreach ($registry->getManagers() as $manager) {
            self::assertInstanceOf(EntityManagerInterface::class, $manager);
            $tool = new SchemaTool($manager);
            $tool->dropDatabase();
            $tool->createSchema($manager->getMetadataFactory()->getAllMetadata());
        }

        $bundleManager = $container->get('nowo_page_layout_kit.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $bundleManager);
        foreach ([1, 2] as $position) {
            $entry = new PageLayoutEntry()->setPageKey('home')->setBlockType(PageBlockType::Hero)->setBlockId($position)->setPosition($position);
            $bundleManager->persist($entry);
            $bundleManager->flush();
            $this->entryIds[] = (int) $entry->getId();
        }
        $bundleManager->clear();

        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->addListener(PageLayoutChangedEvent::class, function (PageLayoutChangedEvent $event) use ($bundleManager): void {
            $this->events[]        = $event;
            $this->inTransaction[] = $bundleManager->getConnection()->isTransactionActive();
        });

        return $container;
    }

    /**
     * @return list<int>
     */
    private function positions(EntityManagerInterface $manager): array
    {
        $column = $manager->getClassMetadata(PageLayoutEntry::class)->getColumnName('position');

        return array_map(intval(...), $manager->getConnection()->fetchFirstColumn('SELECT ' . $column . ' FROM ' . $manager->getClassMetadata(PageLayoutEntry::class)->getTableName() . ' ORDER BY id'));
    }

    /**
     * The `http_kernel` service itself: FrankenPHP worker mode without kernel reset (Kernel::handle() would run the
     * services resetter between two requests).
     */
    private function httpKernel(ContainerInterface $container): HttpKernel
    {
        $kernel = $container->get('http_kernel');
        self::assertInstanceOf(HttpKernel::class, $kernel);

        return $kernel;
    }

    private function manager(ContainerInterface $container, string $name): EntityManagerInterface
    {
        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $manager = $registry->getManager($name);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    /**
     * @return array<string, mixed>
     */
    private function hostMapping(): array
    {
        return [
            'Host' => [
                'type'      => 'attribute',
                'dir'       => dirname(__DIR__, 2) . '/Fixtures/Host',
                'prefix'    => 'Nowo\\PageLayoutKitBundle\\Tests\\Fixtures\\Host',
                'is_bundle' => false,
            ],
        ];
    }

    /**
     * Flex shorthand: one (default) entity manager.
     *
     * @return array<string, mixed>
     */
    private function singleManager(): array
    {
        return [
            'dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/default.sqlite'],
            'orm'  => ['naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware', 'mappings' => $this->hostMapping()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function twoManagers(bool $autoMapping, bool $defaultName = true): array
    {
        $naming = 'doctrine.orm.naming_strategy.underscore_number_aware';
        $orm    = [
            'entity_managers' => [
                'default'     => ['connection' => 'default', 'naming_strategy' => $naming, 'auto_mapping' => $autoMapping, 'mappings' => $this->hostMapping()],
                'page_layout' => ['connection' => 'page_layout', 'naming_strategy' => $naming],
            ],
        ];
        if ($defaultName) {
            $orm = ['default_entity_manager' => 'default'] + $orm;
        }

        return [
            'dbal' => [
                'default_connection' => 'default',
                'connections'        => [
                    'default'     => ['url' => 'sqlite:///%kernel.cache_dir%/default.sqlite'],
                    'page_layout' => ['url' => 'sqlite:///%kernel.cache_dir%/page_layout.sqlite'],
                ],
            ],
            'orm' => $orm,
        ];
    }
}
