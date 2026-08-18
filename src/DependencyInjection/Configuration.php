<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Validates and normalizes `nowo_page_layout_kit` configuration.
 */
final class Configuration implements ConfigurationInterface
{
    public const string ALIAS = 'nowo_page_layout_kit';

    public const array CSS_FRAMEWORKS = [
        'bootstrap',
        'bootstrap4',
        'bootstrap5',
        'tabler',
        'tailwind',
        'foundation',
        'custom',
        'none',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        /** @var ArrayNodeDefinition $root */
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode('default_locale')->defaultValue('es')->end()
                ->arrayNode('locales')
                    ->scalarPrototype()->end()
                    ->defaultValue(['es', 'en'])
                ->end()
                ->arrayNode('pages')
                    ->scalarPrototype()->end()
                    ->defaultValue(['home', 'contact'])
                    ->info('Allowed pageKey values for layout admin and getLayout().')
                ->end()
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('access_roles')
                            ->scalarPrototype()->end()
                            ->defaultValue(['ROLE_EDITOR'])
                        ->end()
                        ->scalarNode('access_checker')->defaultNull()->end()
                        ->booleanNode('allow_unauthenticated')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('web_ui')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('layout_template')
                            ->defaultValue('@NowoPageLayoutKitBundle/admin/layout.html.twig')
                        ->end()
                        ->enumNode('css_framework')
                            ->values(self::CSS_FRAMEWORKS)
                            ->defaultValue('tailwind')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('doctrine')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('table_prefix')->defaultValue('')->end()
                        ->scalarNode('connection')->defaultValue('default')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
