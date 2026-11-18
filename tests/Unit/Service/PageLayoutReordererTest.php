<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Event\PageLayoutChangedEvent;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderData;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderRowData;
use Nowo\PageLayoutKitBundle\Persistence\PageLayoutTransaction;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use Nowo\PageLayoutKitBundle\Service\PageLayoutReorderer;
use Nowo\PageLayoutKitBundle\Tests\Support\SqliteEntityManagerFactory;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class PageLayoutReordererTest extends TestCase
{
    public function testTheSubmittedOrderReplacesTheCommittedOneWholeInOneUnit(): void
    {
        $manager     = SqliteEntityManagerFactory::create();
        [$a, $b, $c] = [$this->entry($manager, 0), $this->entry($manager, 1), $this->entry($manager, 2)];
        $disabled    = $this->entry($manager, 3, false);
        $this->entry($manager, 0, true, 'contact');
        $events     = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PageLayoutChangedEvent::class, static function (PageLayoutChangedEvent $event) use (&$events): void {
            $events[] = [$event->pageKey, $event->entryIds];
        });

        // Another admin moved the rows meanwhile: the lock re-reads them, so every submitted position is written.
        $manager->getConnection()->executeStatement('UPDATE content_page_layout_entry SET position = 9');
        $ids = new PageLayoutReorderer($this->repository($manager), new PageLayoutTransaction($manager, $dispatcher))->reorder('home', $this->order([
            [(int) $c->getId(), 0], [(int) $a->getId(), 1], [(int) $b->getId(), 2], [(int) $disabled->getId(), 0],
        ]));

        self::assertSame([$c->getId(), $a->getId(), $b->getId()], $ids);
        self::assertSame([['home', $ids]], $events);
        $positions = $manager->getConnection()->fetchAllKeyValue('SELECT id, position FROM content_page_layout_entry ORDER BY id');
        self::assertSame([$a->getId() => 1, $b->getId() => 2, $c->getId() => 0, $disabled->getId() => 9, $disabled->getId() + 1 => 9], array_map(intval(...), $positions), 'disabled and other pages untouched');
    }

    public function testAFailedUnitChangesNothingAndAnnouncesNothing(): void
    {
        $manager    = SqliteEntityManagerFactory::create();
        $entry      = $this->entry($manager, 0);
        $events     = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PageLayoutChangedEvent::class, static function () use (&$events): void {
            $events[] = true;
        });
        $manager->getEventManager()->addEventListener('onFlush', new class {
            public function onFlush(): never
            {
                throw TransientStorageFailure::fromPrevious(new RuntimeException('lock wait'), 1);
            }
        });

        try {
            new PageLayoutReorderer($this->repository($manager), new PageLayoutTransaction($manager, $dispatcher))->reorder('home', $this->order([[(int) $entry->getId(), 5]]));
            self::fail('The failure must be rethrown.');
        } catch (TransientStorageFailure) {
        }

        self::assertSame(0, (int) $manager->getConnection()->fetchOne('SELECT position FROM content_page_layout_entry'));
        self::assertFalse($manager->getConnection()->isTransactionActive());
        self::assertSame([], $events);
    }

    public function testNoSubmittedDataKeepsTheOrder(): void
    {
        $manager = SqliteEntityManagerFactory::create();
        $first   = $this->entry($manager, 1);
        $second  = $this->entry($manager, 0);

        self::assertSame([$second->getId(), $first->getId()], new PageLayoutReorderer($this->repository($manager), new PageLayoutTransaction($manager))->reorder('home', null));
    }

    /**
     * @param list<array{0: int, 1: int}> $rows
     */
    private function order(array $rows): PageLayoutReorderData
    {
        $data = new PageLayoutReorderData();
        foreach ($rows as [$id, $position]) {
            $data->rows[] = new PageLayoutReorderRowData($id, $position);
        }

        return $data;
    }

    private function entry(EntityManager $manager, int $position, bool $enabled = true, string $pageKey = 'home'): PageLayoutEntry
    {
        $entry = new PageLayoutEntry()->setPageKey($pageKey)->setBlockType(PageBlockType::Text)->setBlockId(1)->setPosition($position)->setEnabled($enabled);
        $manager->persist($entry);
        $manager->flush();

        return $entry;
    }

    private function repository(EntityManager $manager): PageLayoutEntryRepository
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        return new PageLayoutEntryRepository($registry);
    }
}
