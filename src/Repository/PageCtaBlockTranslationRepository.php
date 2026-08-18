<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlockTranslation;

/**
 * Doctrine repository for CTA page block translations.
 *
 * @extends ServiceEntityRepository<PageCtaBlockTranslation>
 */
final class PageCtaBlockTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageCtaBlockTranslation::class);
    }
}
