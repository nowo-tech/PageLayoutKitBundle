<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Persistence\PageLayoutEntityManagerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageLayoutEntityManagerFactory::class)]
final class PageLayoutEntityManagerFactoryTest extends TestCase
{
    #[Test]
    public function returnsTheManagerOfTheBundleEntities(): void
    {
        $em       = $this->createStub(EntityManagerInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('getManagerForClass')->with(PageLayoutEntry::class)->willReturn($em);

        self::assertSame($em, PageLayoutEntityManagerFactory::create($registry));
    }

    #[Test]
    public function failsClearlyWhenNothingMapsTheEntities(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No Doctrine ORM entity manager maps');
        PageLayoutEntityManagerFactory::create($registry);
    }
}
