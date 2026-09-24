<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlock;

/**
 * Doctrine repository for CTA page blocks.
 *
 * @extends ServiceEntityRepository<PageCtaBlock>
 */
final class PageCtaBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageCtaBlock::class);
    }

    /**
     * Block aggregates are refreshed from the database so a long-lived identity map
     * (worker mode without reset) cannot serve stale translations edited elsewhere.
     */
    public function findWithTranslations(int $id): ?PageCtaBlock
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
