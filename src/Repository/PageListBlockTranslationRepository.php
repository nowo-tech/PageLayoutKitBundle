<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageListBlockTranslation;

/**
 * Doctrine repository for list page block translations.
 *
 * @extends ServiceEntityRepository<PageListBlockTranslation>
 */
final class PageListBlockTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageListBlockTranslation::class);
    }
}
