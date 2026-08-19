<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageCardsBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCompareBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlock;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Nowo\PageLayoutKitBundle\Entity\PageListBlock;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlock;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Repository\PageCardsBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageCompareBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageCtaBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageHeroBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageListBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageTextBlockRepository;
use Nowo\PageLayoutKitBundle\Service\PageBlockRegistry;
use PHPUnit\Framework\TestCase;

final class PageBlockRegistryTest extends TestCase
{
    public function testGetReturnsEntitiesForEachBlockType(): void
    {
        $hero    = new PageHeroBlock();
        $text    = new PageTextBlock();
        $cards   = new PageCardsBlock();
        $list    = new PageListBlock();
        $cta     = new PageCtaBlock();
        $compare = new PageCompareBlock();

        $registry = new PageBlockRegistry(
            $this->createRepository(PageHeroBlockRepository::class, PageHeroBlock::class, 1, $hero),
            $this->createRepository(PageTextBlockRepository::class, PageTextBlock::class, 2, $text),
            $this->createRepository(PageCardsBlockRepository::class, PageCardsBlock::class, 3, $cards),
            $this->createRepository(PageListBlockRepository::class, PageListBlock::class, 4, $list),
            $this->createRepository(PageCtaBlockRepository::class, PageCtaBlock::class, 5, $cta),
            $this->createRepository(PageCompareBlockRepository::class, PageCompareBlock::class, 6, $compare),
        );

        self::assertSame($hero, $registry->get(PageBlockType::Hero, 1));
        self::assertSame($text, $registry->get(PageBlockType::Text, 2));
        self::assertSame($cards, $registry->get(PageBlockType::Cards, 3));
        self::assertSame($list, $registry->get(PageBlockType::List, 4));
        self::assertSame($cta, $registry->get(PageBlockType::Cta, 5));
        self::assertSame($compare, $registry->get(PageBlockType::Compare, 6));
    }

    public function testGetReturnsNullForMissingIds(): void
    {
        $registry = new PageBlockRegistry(
            $this->createRepository(PageHeroBlockRepository::class, PageHeroBlock::class, 1, null),
            $this->createRepository(PageTextBlockRepository::class, PageTextBlock::class, 1, null),
            $this->createRepository(PageCardsBlockRepository::class, PageCardsBlock::class, 1, null),
            $this->createRepository(PageListBlockRepository::class, PageListBlock::class, 1, null),
            $this->createRepository(PageCtaBlockRepository::class, PageCtaBlock::class, 1, null),
            $this->createRepository(PageCompareBlockRepository::class, PageCompareBlock::class, 1, null),
        );

        foreach (PageBlockType::cases() as $type) {
            self::assertNull($registry->get($type, 999_999));
        }
    }

    /**
     * @template TRepository of object
     * @template TEntity of object
     *
     * @param class-string<TRepository> $repositoryClass
     * @param class-string<TEntity> $entityClass
     * @param TEntity|null $result
     *
     * @return TRepository
     */
    private function createRepository(
        string $repositoryClass,
        string $entityClass,
        int $expectedId,
        ?object $result,
    ): object {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')
            ->with($entityClass)
            ->willReturn(new ClassMetadata($entityClass));
        $entityManager->method('createQueryBuilder')
            ->willReturnCallback(function () use ($expectedId, $result): QueryBuilder {
                $params = [];
                $query  = $this->createMock(Query::class);
                $query->method('getOneOrNullResult')
                    ->willReturnCallback(static function () use (&$params, $expectedId, $result): ?object {
                        return ($params['id'] ?? null) === $expectedId ? $result : null;
                    });

                $queryBuilder = $this->createMock(QueryBuilder::class);
                $queryBuilder->method('select')->willReturnSelf();
                $queryBuilder->method('from')->willReturnSelf();
                $queryBuilder->method('leftJoin')->willReturnSelf();
                $queryBuilder->method('addSelect')->willReturnSelf();
                $queryBuilder->method('andWhere')->willReturnSelf();
                $queryBuilder->method('orderBy')->willReturnSelf();
                $queryBuilder->method('setParameter')
                    ->willReturnCallback(static function (string $key, mixed $value) use (&$params, $queryBuilder): QueryBuilder {
                        $params[$key] = $value;

                        return $queryBuilder;
                    });
                $queryBuilder->method('getQuery')->willReturn($query);

                return $queryBuilder;
            });

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')
            ->with($entityClass)
            ->willReturn($entityManager);

        return new $repositoryClass($registry);
    }
}
