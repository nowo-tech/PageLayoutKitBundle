<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Nowo\PageLayoutKitBundle\Entity\PageCtaBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

    public function findWithTranslations(int $id): ?PageCtaBlock
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.translations', 'bt')->addSelect('bt')
            ->andWhere('b.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
