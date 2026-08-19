<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Repository\PageCardItemTranslationRepository;
use Nowo\PageLayoutKitBundle\Repository\PageCardsBlockTranslationRepository;
use Nowo\PageLayoutKitBundle\Repository\PageCompareBlockTranslationRepository;
use Nowo\PageLayoutKitBundle\Repository\PageCtaBlockTranslationRepository;
use Nowo\PageLayoutKitBundle\Repository\PageHeroBlockTranslationRepository;
use Nowo\PageLayoutKitBundle\Repository\PageListBlockTranslationRepository;
use Nowo\PageLayoutKitBundle\Repository\PageListItemTranslationRepository;
use Nowo\PageLayoutKitBundle\Repository\PageTextBlockTranslationRepository;
use PHPUnit\Framework\TestCase;

final class PageTranslationRepositoriesTest extends TestCase
{
    public function testTranslationRepositoriesCanBeInstantiated(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);

        self::assertInstanceOf(PageCardItemTranslationRepository::class, new PageCardItemTranslationRepository($registry));
        self::assertInstanceOf(PageCardsBlockTranslationRepository::class, new PageCardsBlockTranslationRepository($registry));
        self::assertInstanceOf(PageCompareBlockTranslationRepository::class, new PageCompareBlockTranslationRepository($registry));
        self::assertInstanceOf(PageCtaBlockTranslationRepository::class, new PageCtaBlockTranslationRepository($registry));
        self::assertInstanceOf(PageHeroBlockTranslationRepository::class, new PageHeroBlockTranslationRepository($registry));
        self::assertInstanceOf(PageListBlockTranslationRepository::class, new PageListBlockTranslationRepository($registry));
        self::assertInstanceOf(PageListItemTranslationRepository::class, new PageListItemTranslationRepository($registry));
        self::assertInstanceOf(PageTextBlockTranslationRepository::class, new PageTextBlockTranslationRepository($registry));
    }
}
