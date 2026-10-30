<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\DependencyInjection;

use Nowo\PageLayoutKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageLayoutKitBundle\Ui\PageLayoutKitPages;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

use function array_diff_key;
use function array_keys;
use function implode;
use function sprintf;

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
                            ->info('Roles allowed to use the layout admin (user needs at least one). Empty list → grants nothing; fails container compilation unless access_checker or allow_unauthenticated is set.')
                        ->end()
                        ->scalarNode('access_checker')->defaultNull()->end()
                        ->booleanNode('allow_unauthenticated')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('web_ui')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function (array $v): array {
                            // The 2.0 default layout is deprecated (REQ-UI-003): null = nowo_ui_kit.layouts.admin.
                            if (($v['layout_template'] ?? null) === PageLayoutKitPages::LEGACY_DEFAULT_LAYOUT) {
                                $v['layout_template'] = null;
                            }

                            return $v;
                        })
                    ->end()
                    ->children()
                        ->scalarNode('layout_template')
                            ->defaultNull()
                            ->info('Layout the admin pages extend (REQ-UI-003). Null (default) = nowo_ui_kit.layouts.admin. A 2.0 layout without the UiKit canonical blocks (it renders {% block admin %}) is wrapped in a deprecated bridge.')
                        ->end()
                        ->enumNode('css_framework')
                            ->values([...self::CSS_FRAMEWORKS, null])
                            ->defaultNull()
                            ->info('Deprecated since 2.1: the admin pages take the CSS stack from nowo_ui_kit.css_framework. Only feeds the deprecated nowo_page_layout_kit_css_framework Twig global (null = tailwind).')
                            ->setDeprecated('nowo-tech/page-layout-kit-bundle', '2.1', 'The "%path%.%node%" option is deprecated: the admin pages use nowo_ui_kit.css_framework (REQ-UI-003). Remove it.')
                        ->end()
                        ->arrayNode('templates')
                            ->info('Per-page template overrides (REQ-UI-003), page id => template. Ids: ' . implode(', ', array_keys(PageLayoutKitPages::TEMPLATES)) . '. Prefer a thin {% extends \'@!NowoPageLayoutKitBundle/...\' %} that overrides one data block.')
                            ->useAttributeAsKey('page')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->defaultValue([])
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => array_diff_key($v, PageLayoutKitPages::TEMPLATES) !== [])
                                ->then(static function (array $v): array {
                                    throw new InvalidConfigurationException(sprintf('Unknown nowo_page_layout_kit.web_ui.templates page id(s) "%s"; allowed: %s.', implode('", "', array_keys(array_diff_key($v, PageLayoutKitPages::TEMPLATES))), implode(', ', array_keys(PageLayoutKitPages::TEMPLATES))));
                                })
                            ->end()
                        ->end()
                        ->arrayNode('page_routes')
                            ->info('Public route name per page key for the admin "View page" link and post-save redirect (e.g. {home: app_home}). Unmapped keys try a route named like the page key; missing routes hide the link.')
                            ->useAttributeAsKey('page_key')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
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
                ->arrayNode('html')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('sanitize')
                            ->addDefaultsIfNotSet()
                            ->info('Sanitize block HTML on persist and public render. Default allowlist strips scripts; set none only for fully trusted editors.')
                            ->children()
                                ->enumNode('strategy')
                                    ->values(HtmlSanitizeStrategy::values())
                                    ->defaultValue(HtmlSanitizeStrategy::Allowlist->value)
                                ->end()
                                ->scalarNode('service')
                                    ->defaultNull()
                                    ->info('Service id implementing PageLayoutHtmlSanitizerInterface when strategy=service.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
