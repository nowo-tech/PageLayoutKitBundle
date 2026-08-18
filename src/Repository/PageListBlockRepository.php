<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageListBlock;

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

    public function findWithItemsAndTranslations(int $id): ?PageListBlock
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.translations', 'bt')->addSelect('bt')
            ->leftJoin('b.items', 'i')->addSelect('i')
            ->leftJoin('i.translations', 'it')->addSelect('it')
            ->andWhere('b.id = :id')
            ->setParameter('id', $id)
            ->orderBy('i.position', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
