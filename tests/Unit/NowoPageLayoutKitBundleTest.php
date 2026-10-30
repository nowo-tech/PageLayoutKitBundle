<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Nowo\PageLayoutKitBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use Nowo\PageLayoutKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\PageLayoutKitBundle\DependencyInjection\NowoPageLayoutKitExtension;
use Nowo\PageLayoutKitBundle\NowoPageLayoutKitBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class NowoPageLayoutKitBundleTest extends TestCase
{
    public function testBuildRegistersTwigAndDoctrineCompilerPasses(): void
    {
        $bundle    = new NowoPageLayoutKitBundle();
        $container = new ContainerBuilder();

        $bundle->build($container);

        $passes = $container->getCompilerPassConfig()->getPasses();
        self::assertNotEmpty(array_filter($passes, static fn (object $pass): bool => $pass instanceof TwigPathsPass));
        self::assertNotEmpty(array_filter($passes, static fn (object $pass): bool => $pass instanceof CsrfProtectionPass));
        self::assertNotEmpty(array_filter($passes, static fn (object $pass): bool => $pass instanceof DoctrineOrmMappingsPass));
    }

    public function testBootDoesNotTouchTheContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('has');
        $container->expects(self::never())->method('get');

        $bundle = new NowoPageLayoutKitBundle();
        $bundle->setContainer($container);
        $bundle->boot();
    }

    public function testGetContainerExtensionReturnsBundleExtensionInstance(): void
    {
        $bundle = new NowoPageLayoutKitBundle();

        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(NowoPageLayoutKitExtension::class, $extension);
        self::assertSame($extension, $bundle->getContainerExtension());
    }
}
