<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Persistence;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Persistence\Mapping\MappingException;
use Nowo\PageLayoutKitBundle\DependencyInjection\Compiler\PageLayoutEntityManagerPass;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Persistence\ExcludedEntityNamespaceDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExcludedEntityNamespaceDriver::class)]
final class ExcludedEntityNamespaceDriverTest extends TestCase
{
    #[Test]
    public function mapsNothing(): void
    {
        $driver = new ExcludedEntityNamespaceDriver();

        self::assertTrue($driver->isTransient(PageLayoutEntry::class));
        self::assertSame([], $driver->getAllClassNames());

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(PageLayoutEntry::class);
        $driver->loadMetadataForClass(PageLayoutEntry::class, new ClassMetadata(PageLayoutEntry::class));
    }

    /**
     * A chain asks its namespace drivers before its default driver: a default driver that claims every entity class
     * no longer claims the bundle entities.
     */
    #[Test]
    public function winsOverTheDefaultDriverOfAChain(): void
    {
        $catchAll = $this->createStub(MappingDriver::class);
        $catchAll->method('isTransient')->willReturn(false);
        $chain = new MappingDriverChain();
        $chain->setDefaultDriver($catchAll);
        self::assertFalse($chain->isTransient(PageLayoutEntry::class));

        $chain->addDriver(new ExcludedEntityNamespaceDriver(), PageLayoutEntityManagerPass::ENTITY_NAMESPACE);

        self::assertTrue($chain->isTransient(PageLayoutEntry::class));
        self::assertTrue($chain->isTransient(PageHeroBlock::class));
        self::assertFalse($chain->isTransient(self::class), 'other classes still go to the default driver');
    }
}
