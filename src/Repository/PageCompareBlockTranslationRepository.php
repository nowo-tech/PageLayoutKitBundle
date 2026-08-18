<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Nowo\PageLayoutKitBundle\Entity\PageCompareBlockTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Doctrine repository for compare page block translations.
 *
 * @extends ServiceEntityRepository<PageCompareBlockTranslation>
 */
final class PageCompareBlockTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageCompareBlockTranslation::class);
    }
}
