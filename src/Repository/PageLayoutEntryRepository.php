<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

    /** @return list<PageLayoutEntry> */
    public function findEnabledByPageKey(string $pageKey): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.pageKey = :pageKey')
            ->andWhere('e.enabled = :enabled')
            ->setParameter('pageKey', $pageKey)
            ->setParameter('enabled', true)
            ->orderBy('e.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
