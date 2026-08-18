<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Nowo\PageLayoutKitBundle\Entity\PageCardsBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Doctrine repository for cards page blocks.
 *
 * @extends ServiceEntityRepository<PageCardsBlock>
 */
final class PageCardsBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageCardsBlock::class);
    }

    public function findWithItemsAndTranslations(int $id): ?PageCardsBlock
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
