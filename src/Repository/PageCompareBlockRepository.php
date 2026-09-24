<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageCompareBlock;

/**
 * Doctrine repository for compare page blocks.
 *
 * @extends ServiceEntityRepository<PageCompareBlock>
 */
final class PageCompareBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageCompareBlock::class);
    }

    /**
     * Block aggregates are refreshed from the database so a long-lived identity map
     * (worker mode without reset) cannot serve stale translations edited elsewhere.
     */
    public function findWithTranslations(int $id): ?PageCompareBlock
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.translations', 'bt')->addSelect('bt')
            ->andWhere('b.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();
    }
}
