<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Nowo\PageLayoutKitBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use Nowo\PageLayoutKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\PageLayoutKitBundle\DependencyInjection\NowoPageLayoutKitExtension;
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
        $container->addCompilerPass(new CsrfProtectionPass());
        $container->addCompilerPass(new TwigPathsPass());

        $entityDir = __DIR__ . '/Entity';
        if (is_dir($entityDir)) {
            $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
                ['Nowo\\PageLayoutKitBundle\\Entity'],
                [$entityDir],
            ));
        }
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            // @igor-ignore - Boot-time Symfony Bundle extension cache (not request state).
            $this->extension = new NowoPageLayoutKitExtension();
        }

        return $this->extension;
    }
}
