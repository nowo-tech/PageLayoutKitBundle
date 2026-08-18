<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Nowo\PageLayoutKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\PageLayoutKitBundle\DependencyInjection\NowoPageLayoutKitExtension;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\NowoPageLayoutKitBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class NowoPageLayoutKitBundleTest extends TestCase
{
    public function testBuildRegistersTwigAndDoctrineCompilerPasses(): void
    {
        $bundle = new NowoPageLayoutKitBundle();
        $container = new ContainerBuilder();

        $bundle->build($container);

        $passes = $container->getCompilerPassConfig()->getPasses();
        self::assertNotEmpty(array_filter($passes, static fn (object $pass): bool => $pass instanceof TwigPathsPass));
        self::assertNotEmpty(array_filter($passes, static fn (object $pass): bool => $pass instanceof DoctrineOrmMappingsPass));
    }

    public function testBootBindsLocalesFromContainer(): void
    {
        $this->unbindPageLocales();

        $locales = new PageLocales('es', ['es', 'en']);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('has')->with(PageLocales::class)->willReturn(true);
        $container->expects(self::once())->method('get')->with(PageLocales::class)->willReturn($locales);

        $bundle = new NowoPageLayoutKitBundle();
        $bundle->setContainer($container);
        $bundle->boot();

        self::assertSame('es', PageLocales::default());
        self::assertSame(['es', 'en'], PageLocales::all());
    }

    public function testBootSkipsBindingWhenLocalesServiceIsMissing(): void
    {
        $this->unbindPageLocales();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('has')->with(PageLocales::class)->willReturn(false);
        $container->expects(self::never())->method('get');

        $bundle = new NowoPageLayoutKitBundle();
        $bundle->setContainer($container);
        $bundle->boot();

        self::assertSame('fr', new PageLocales('fr', ['fr'])->getDefault());
    }

    public function testGetContainerExtensionReturnsBundleExtensionInstance(): void
    {
        $bundle = new NowoPageLayoutKitBundle();

        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(NowoPageLayoutKitExtension::class, $extension);
        self::assertSame($extension, $bundle->getContainerExtension());
    }

    private function unbindPageLocales(): void
    {
        $reflection = new \ReflectionClass(PageLocales::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
}
