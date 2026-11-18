<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use SortDirection;

/**
 * Doctrine repository for CMS page layout entries.
 *
 * @extends ServiceEntityRepository<PageLayoutEntry>
 */
final class PageLayoutEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageLayoutEntry::class);
    }

    /**
     * Entries are refreshed from the database so a long-lived identity map (worker mode without
     * reset) cannot serve stale `position` / `enabled` values edited by another worker.
     *
     * @return list<PageLayoutEntry>
     */
    public function findEnabledByPageKey(string $pageKey): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.pageKey = :pageKey')
            ->andWhere('e.enabled = :enabled')
            ->setParameter('pageKey', $pageKey)
            ->setParameter('enabled', true)
            ->orderBy('e.position', SortDirection::Ascending)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
    }

    /**
     * Every entry of the page (enabled or not), locked `FOR UPDATE` in id order and refreshed from the database
     * (2.3, REQ-RUNTIME-003 rule 4): a write unit that rewrites the page's positions, or replaces its layout, reads the
     * committed rows under the lock, so two admins (or an admin and the migration) never interleave on one page.
     * SQLite takes no row lock (its writers are serialised by the database lock). Must run inside a transaction.
     *
     * @return list<PageLayoutEntry>
     */
    public function lockByPageKey(string $pageKey): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.pageKey = :pageKey')
            ->setParameter('pageKey', $pageKey)
            ->orderBy('e.id', SortDirection::Ascending)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
    }
}
