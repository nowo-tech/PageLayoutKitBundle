<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlockTranslation;

/**
 * Doctrine repository for text page block translations.
 *
 * @extends ServiceEntityRepository<PageTextBlockTranslation>
 */
final class PageTextBlockTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageTextBlockTranslation::class);
    }
}
