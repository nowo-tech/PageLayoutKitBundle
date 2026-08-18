<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\DependencyInjection;

use Doctrine\ORM\Events;
use LogicException;
use Nowo\PageLayoutKitBundle\DependencyInjection\Configuration as BundleConfiguration;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Security\AllowAllPageLayoutKitAccessChecker;
use Nowo\PageLayoutKitBundle\Security\ConfigurablePageLayoutKitAccessChecker;
use Nowo\PageLayoutKitBundle\Security\PageLayoutKitAccessCheckerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function array_key_exists;
use function is_array;
use function is_string;

/**
 * Loads PageLayoutKit services and publishes configuration parameters.
 */
final class NowoPageLayoutKitExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_form_kit')) {
            return;
        }

        $hostHasProfile = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            /** @var array<string, mixed> $cfg */
            $profiles = $cfg['profiles'] ?? null;
            if (is_array($profiles) && array_key_exists('page_layout_kit', $profiles)) {
                $hostHasProfile = true;
            }
        }

        if ($hostHasProfile) {
            return;
        }

        $container->prependExtensionConfig('nowo_form_kit', [
            'profiles' => [
                'page_layout_kit' => [
                    'alias'              => 'page_layout_kit',
                    'translation_domain' => 'NowoPageLayoutKitBundle',
                    'defaults'           => [
                        'attr'     => ['class' => 'nowo-ui-input form-control'],
                        'row_attr' => ['class' => 'mb-2'],
                    ],
                ],
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new BundleConfiguration();
        $config        = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $container->setParameter('nowo_page_layout_kit.default_locale', $config['default_locale']);
        $container->setParameter('nowo_page_layout_kit.locales', $config['locales']);
        $container->setParameter('nowo_page_layout_kit.pages', $config['pages']);
        $container->setParameter('nowo_page_layout_kit.doctrine.table_prefix', $config['doctrine']['table_prefix']);
        $container->setParameter('nowo_page_layout_kit.doctrine.connection', $config['doctrine']['connection']);
        $container->setParameter('nowo_page_layout_kit.security.access_roles', $config['security']['access_roles']);
        $container->setParameter('nowo_page_layout_kit.security.access_checker', $config['security']['access_checker']);
        $container->setParameter('nowo_page_layout_kit.security.allow_unauthenticated', $config['security']['allow_unauthenticated']);
        $container->setParameter('nowo_page_layout_kit.web_ui.layout_template', $config['web_ui']['layout_template']);
        $container->setParameter('nowo_page_layout_kit.web_ui.css_framework', $config['web_ui']['css_framework']);

        $container->getDefinition(PageLocales::class)
            ->setArgument('$defaultLocale', $config['default_locale'])
            ->setArgument('$locales', $config['locales']);

        if (
            !$config['security']['allow_unauthenticated']
            && !$this->isSecurityBundleAvailable($container)
        ) {
            throw new LogicException('NowoPageLayoutKitBundle admin UI requires symfony/security-bundle when security.allow_unauthenticated is false.');
        }

        $this->registerAccessChecker($container, $config['security']);

        $tablePrefix = (string) $config['doctrine']['table_prefix'];
        if ($tablePrefix !== '') {
            $definition = new Definition(TablePrefixListener::class, [$tablePrefix]);
            $definition->addTag('doctrine.event_listener', ['event' => Events::loadClassMetadata]);
            $container->setDefinition(TablePrefixListener::class, $definition);
        }
    }

    public function getAlias(): string
    {
        return BundleConfiguration::ALIAS;
    }

    /**
     * @param array{access_checker: ?string, access_roles: list<string>, allow_unauthenticated: bool} $security
     */
    private function registerAccessChecker(ContainerBuilder $container, array $security): void
    {
        if ($security['allow_unauthenticated']) {
            $id = 'nowo_page_layout_kit.access_checker.allow_all';
            $container->setDefinition($id, new Definition(AllowAllPageLayoutKitAccessChecker::class));
            $container->setAlias(PageLayoutKitAccessCheckerInterface::class, $id);

            return;
        }

        $custom = $security['access_checker'] ?? null;
        if (is_string($custom) && $custom !== '') {
            $container->setAlias(PageLayoutKitAccessCheckerInterface::class, $custom);

            return;
        }

        $id         = 'nowo_page_layout_kit.access_checker.default';
        $definition = new Definition(ConfigurablePageLayoutKitAccessChecker::class);
        $definition->setArgument('$accessRoles', $security['access_roles']);
        $definition->setArgument('$authorizationChecker', new Reference('security.authorization_checker'));
        $container->setDefinition($id, $definition);
        $container->setAlias(PageLayoutKitAccessCheckerInterface::class, $id);
    }

    private function isSecurityBundleAvailable(ContainerBuilder $container): bool
    {
        if ($container->hasExtension('security')) {
            return true;
        }

        if (!$container->hasParameter('kernel.bundles')) {
            return false;
        }

        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        return isset($bundles['SecurityBundle']);
    }
}
