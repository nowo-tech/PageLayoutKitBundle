<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageListBlock;
use SortDirection;

/**
 * Doctrine repository for list page blocks.
 *
 * @extends ServiceEntityRepository<PageListBlock>
 */
final class PageListBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageListBlock::class);
    }

    /**
     * Block aggregates are refreshed from the database so a long-lived identity map
     * (worker mode without reset) cannot serve stale items/translations edited elsewhere.
     */
    public function findWithItemsAndTranslations(int $id): ?PageListBlock
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.translations', 'bt')->addSelect('bt')
            ->leftJoin('b.items', 'i')->addSelect('i')
            ->leftJoin('i.translations', 'it')->addSelect('it')
            ->andWhere('b.id = :id')
            ->setParameter('id', $id)
            ->orderBy('i.position', SortDirection::Ascending)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();
    }
}
