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
            ->with(PageCardItem::class)
            ->willReturn(new ClassMetadata(PageCardItem::class));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')
            ->with(PageCardItem::class)
            ->willReturn($entityManager);

        $repository = new PageCardItemRepository($registry);

        self::assertInstanceOf(PageCardItemRepository::class, $repository);
    }
}
