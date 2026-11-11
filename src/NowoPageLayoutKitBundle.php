<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Nowo\PageLayoutKitBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use Nowo\PageLayoutKitBundle\DependencyInjection\Compiler\PageLayoutEntityManagerPass;
use Nowo\PageLayoutKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\PageLayoutKitBundle\DependencyInjection\NowoPageLayoutKitExtension;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
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

        // Before the mapping passes: a clear error for an unknown doctrine.entity_manager (2.2).
        $container->addCompilerPass(new PageLayoutEntityManagerPass(checkOnly: true), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);

        $entityDir = __DIR__ . '/Entity';
        if (is_dir($entityDir)) {
            $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
                ['Nowo\\PageLayoutKitBundle\\Entity'],
                [$entityDir],
                [NowoPageLayoutKitExtension::ENTITY_MANAGER_PARAMETER],
            ));
        }

        // After the mapping pass above (and DoctrineBundle's auto_mapping): keep the entities on one manager (2.2).
        $container->addCompilerPass(new PageLayoutEntityManagerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -10);
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
