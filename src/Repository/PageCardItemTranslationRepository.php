<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageCardItemTranslation;

/**
 * Doctrine repository for card item translations.
 *
 * @extends ServiceEntityRepository<PageCardItemTranslation>
 */
final class PageCardItemTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageCardItemTranslation::class);
    }
}
