<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Nowo\PageLayoutKitBundle\Entity\PageListBlockTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
