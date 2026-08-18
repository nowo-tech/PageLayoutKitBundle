<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\DependencyInjection;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\PageLayoutKitBundle\DependencyInjection\TablePrefixListener;
use PHPUnit\Framework\TestCase;

final class TablePrefixListenerTest extends TestCase
{
    public function testDoesNothingWhenPrefixIsEmpty(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects(self::never())->method('setPrimaryTable');

        $event = new LoadClassMetadataEventArgs(
            $metadata,
            $this->createMock(EntityManagerInterface::class),
        );

        (new TablePrefixListener(''))->loadClassMetadata($event);
    }

    public function testDoesNothingForClassesOutsideBundleNamespace(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn('App\\Entity\\Post');
        $metadata->expects(self::never())->method('setPrimaryTable');

        $event = new LoadClassMetadataEventArgs(
            $metadata,
            $this->createMock(EntityManagerInterface::class),
        );

        (new TablePrefixListener('acme_'))->loadClassMetadata($event);
    }

    public function testPrefixesBundleEntityTableNames(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn('Nowo\\PageLayoutKitBundle\\Entity\\PageHeroBlock');
        $metadata->method('getTableName')->willReturn('content_page_hero_block');
        $metadata->expects(self::once())
            ->method('setPrimaryTable')
            ->with([
                'name' => 'acme_content_page_hero_block',
            ]);

        $event = new LoadClassMetadataEventArgs(
            $metadata,
            $this->createMock(EntityManagerInterface::class),
        );

        (new TablePrefixListener('acme_'))->loadClassMetadata($event);
    }
}
