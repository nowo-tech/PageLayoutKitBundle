<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
