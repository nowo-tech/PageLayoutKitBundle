<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageListItemTranslation;

/**
 * Doctrine repository for list item translations.
 *
 * @extends ServiceEntityRepository<PageListItemTranslation>
 */
final class PageListItemTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageListItemTranslation::class);
    }
}
