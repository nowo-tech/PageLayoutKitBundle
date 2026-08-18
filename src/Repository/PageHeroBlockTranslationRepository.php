<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlockTranslation;

/**
 * Doctrine repository for hero page block translations.
 *
 * @extends ServiceEntityRepository<PageHeroBlockTranslation>
 */
final class PageHeroBlockTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageHeroBlockTranslation::class);
    }
}
