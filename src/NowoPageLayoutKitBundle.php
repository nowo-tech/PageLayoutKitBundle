<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Nowo\PageLayoutKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\PageLayoutKitBundle\DependencyInjection\NowoPageLayoutKitExtension;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle entry point for the reusable page layout compositor.
 */
class NowoPageLayoutKitBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TwigPathsPass());

        $entityDir = __DIR__ . '/Entity';
        if (is_dir($entityDir)) {
            $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
                ['Nowo\\PageLayoutKitBundle\\Entity'],
                [$entityDir],
            ));
        }
    }

    public function boot(): void
    {
        if ($this->container?->has(PageLocales::class) === true) {
            /** @var PageLocales $locales */
            $locales = $this->container->get(PageLocales::class);
            PageLocales::bind($locales);
        }
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new NowoPageLayoutKitExtension();
        }

        $extension = $this->extension;

        return $extension === false ? null : $extension;
    }
}
