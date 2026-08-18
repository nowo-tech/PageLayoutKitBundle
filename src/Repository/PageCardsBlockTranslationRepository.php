<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageCardsBlockTranslation;

/**
 * Doctrine repository for cards page block translations.
 *
 * @extends ServiceEntityRepository<PageCardsBlockTranslation>
 */
final class PageCardsBlockTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageCardsBlockTranslation::class);
    }
}
