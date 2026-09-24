<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageCardItemTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageCardsBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageCompareBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageListBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageListItem;
use Nowo\PageLayoutKitBundle\Entity\PageListItemTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlockTranslation;
use Nowo\PageLayoutKitBundle\Repository\PageCardItemTranslationRepository;
use Nowo\PageLayoutKitBundle\Repository\PageCardsBlockTranslationRepository;
use Nowo\PageLayoutKitBundle\Repository\PageCompareBlockTranslationRepository;
use Nowo\PageLayoutKitBundle\Repository\PageCtaBlockTranslationRepository;
use Nowo\PageLayoutKitBundle\Repository\PageHeroBlockTranslationRepository;
use Nowo\PageLayoutKitBundle\Repository\PageListBlockTranslationRepository;
use Nowo\PageLayoutKitBundle\Repository\PageListItemRepository;
use Nowo\PageLayoutKitBundle\Repository\PageListItemTranslationRepository;
use Nowo\PageLayoutKitBundle\Repository\PageTextBlockTranslationRepository;
use PHPUnit\Framework\TestCase;

final class PageTranslationRepositoriesTest extends TestCase
{
    public function testTranslationRepositoriesCanBeInstantiated(): void
    {
        self::assertSame(PageCardItemTranslation::class, (new PageCardItemTranslationRepository($this->createRegistry(PageCardItemTranslation::class)))->getClassName());
        self::assertSame(PageCardsBlockTranslation::class, (new PageCardsBlockTranslationRepository($this->createRegistry(PageCardsBlockTranslation::class)))->getClassName());
        self::assertSame(PageCompareBlockTranslation::class, (new PageCompareBlockTranslationRepository($this->createRegistry(PageCompareBlockTranslation::class)))->getClassName());
        self::assertSame(PageCtaBlockTranslation::class, (new PageCtaBlockTranslationRepository($this->createRegistry(PageCtaBlockTranslation::class)))->getClassName());
        self::assertSame(PageHeroBlockTranslation::class, (new PageHeroBlockTranslationRepository($this->createRegistry(PageHeroBlockTranslation::class)))->getClassName());
        self::assertSame(PageListBlockTranslation::class, (new PageListBlockTranslationRepository($this->createRegistry(PageListBlockTranslation::class)))->getClassName());
        self::assertSame(PageListItemTranslation::class, (new PageListItemTranslationRepository($this->createRegistry(PageListItemTranslation::class)))->getClassName());
        self::assertSame(PageListItem::class, (new PageListItemRepository($this->createRegistry(PageListItem::class)))->getClassName());
        self::assertSame(PageTextBlockTranslation::class, (new PageTextBlockTranslationRepository($this->createRegistry(PageTextBlockTranslation::class)))->getClassName());
    }

    /** @param class-string $entityClass */
    private function createRegistry(string $entityClass): ManagerRegistry
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn(new ClassMetadata($entityClass));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        return $registry;
    }
}
