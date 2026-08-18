<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PageLayoutKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class TwigPathsPassTest extends TestCase
{
    public function testProcessSkipsWhenNoTwigLoaderCanBeResolved(): void
    {
        $container = new ContainerBuilder();

        (new TwigPathsPass())->process($container);

        self::assertTrue(true);
    }

    public function testProcessPrependsOverrideAndAddsViewsPathWhenAliasChainResolves(): void
    {
        $projectDir = sys_get_temp_dir() . '/page-layout-kit-' . uniqid('', true);
        mkdir($projectDir . '/templates/bundles/NowoPageLayoutKitBundle', 0777, true);

        try {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.project_dir', $projectDir . '///');
            $container->setDefinition('twig.loader.filesystem.inner', new Definition(stdClass::class));
            $container->setAlias('twig.loader.native', 'twig.loader.alias1');
            $container->setAlias('twig.loader.alias1', 'twig.loader.alias2');
            $container->setAlias('twig.loader.alias2', 'twig.loader.filesystem.inner');

            (new TwigPathsPass())->process($container);

            $calls = $container->getDefinition('twig.loader.filesystem.inner')->getMethodCalls();
            self::assertSame('prependPath', $calls[0][0]);
            self::assertSame($projectDir . '/templates/bundles/NowoPageLayoutKitBundle', $calls[0][1][0]);
            self::assertSame('NowoPageLayoutKitBundle', $calls[0][1][1]);
            self::assertSame('addPath', $calls[1][0]);
            self::assertStringEndsWith('/src/Resources/views', $calls[1][1][0]);
            self::assertSame('NowoPageLayoutKitBundle', $calls[1][1][1]);
        } finally {
            @rmdir($projectDir . '/templates/bundles/NowoPageLayoutKitBundle');
            @rmdir($projectDir . '/templates/bundles');
            @rmdir($projectDir . '/templates');
            @rmdir($projectDir);
        }
    }

    public function testProcessFallsBackToNativeLoaderDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('twig.loader.native', new Definition(stdClass::class));
        $container->setParameter('kernel.project_dir', 123);

        (new TwigPathsPass())->process($container);

        $calls = $container->getDefinition('twig.loader.native')->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('addPath', $calls[0][0]);
    }

    public function testProcessFallsBackToNativeFilesystemWhenNativeAliasIsUnresolved(): void
    {
        $container = new ContainerBuilder();
        $container->setAlias('twig.loader.native', 'twig.loader.missing');
        $container->setDefinition('twig.loader.native_filesystem', new Definition(stdClass::class));

        (new TwigPathsPass())->process($container);

        $calls = $container->getDefinition('twig.loader.native_filesystem')->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('addPath', $calls[0][0]);
    }

    public function testProcessFallsBackToFilesystemLoaderDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('twig.loader.filesystem', new Definition(stdClass::class));

        (new TwigPathsPass())->process($container);

        $calls = $container->getDefinition('twig.loader.filesystem')->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('addPath', $calls[0][0]);
    }
}
