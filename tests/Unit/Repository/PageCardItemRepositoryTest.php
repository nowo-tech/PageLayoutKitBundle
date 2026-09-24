<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageCardItem;
use Nowo\PageLayoutKitBundle\Repository\PageCardItemRepository;
use PHPUnit\Framework\TestCase;

final class PageCardItemRepositoryTest extends TestCase
{
    public function testRepositoryConstructsForPageCardItemEntity(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')
            ->willReturnCallback(static function (string $className): ClassMetadata {
                self::assertSame(PageCardItem::class, $className);

                return new ClassMetadata(PageCardItem::class);
            });

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')
            ->willReturnCallback(static function (string $className) use ($entityManager): EntityManagerInterface {
                self::assertSame(PageCardItem::class, $className);

                return $entityManager;
            });

        $repository = new PageCardItemRepository($registry);

        self::assertSame(PageCardItem::class, $repository->getClassName());
    }
}
