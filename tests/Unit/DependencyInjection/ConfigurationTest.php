<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\DependencyInjection;

use Nowo\PageLayoutKitBundle\DependencyInjection\Configuration;
use Nowo\PageLayoutKitBundle\Ui\PageLayoutKitPages;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testAliasAndFrameworksStayStable(): void
    {
        self::assertSame('nowo_page_layout_kit', (new Configuration())->getConfigTreeBuilder()->buildTree()->getName());
        self::assertContains('tailwind', Configuration::CSS_FRAMEWORKS);
        self::assertContains('custom', Configuration::CSS_FRAMEWORKS);
    }

    public function testWebUiDefaultsToTheUiKitLayout(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertNull($config['web_ui']['layout_template']);
        self::assertNull($config['web_ui']['css_framework']);
        self::assertSame([], $config['web_ui']['templates']);
    }

    public function testTheDeprecated20DefaultLayoutMeansTheUiKitLayout(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'web_ui' => ['layout_template' => PageLayoutKitPages::LEGACY_DEFAULT_LAYOUT],
        ]]);

        self::assertNull($config['web_ui']['layout_template']);
    }

    public function testPerPageTemplateOverridesAreValidated(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'web_ui' => ['templates' => ['layout_index' => 'admin/page_layout.html.twig', 'block_edit' => 'admin/block.html.twig']],
        ]]);

        self::assertSame(['layout_index' => 'admin/page_layout.html.twig', 'block_edit' => 'admin/block.html.twig'], $config['web_ui']['templates']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown nowo_page_layout_kit.web_ui.templates page id(s) "index"; allowed: layout_index, block_edit.');

        (new Processor())->processConfiguration(new Configuration(), [[
            'web_ui' => ['templates' => ['index' => 'admin/index.html.twig']],
        ]]);
    }

    #[IgnoreDeprecations]
    public function testCssFrameworkIsDeprecated(): void
    {
        $this->expectUserDeprecationMessageMatches('/"nowo_page_layout_kit\.web_ui\.css_framework" option is deprecated/');

        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'web_ui' => ['css_framework' => 'bootstrap5'],
        ]]);

        self::assertSame('bootstrap5', $config['web_ui']['css_framework']);
    }

    #[IgnoreDeprecations]
    public function testConfigurationAppliesDefaultsAndCustomValues(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [[
            'default_locale' => 'en',
            'locales'        => ['en', 'es'],
            'pages'          => ['landing'],
            'security'       => [
                'access_roles'          => ['ROLE_ADMIN'],
                'access_checker'        => 'app.access_checker',
                'allow_unauthenticated' => true,
            ],
            'web_ui' => [
                'layout_template' => '@App/layout.html.twig',
                'css_framework'   => 'custom',
                'page_routes'     => ['home' => 'app_home'],
            ],
            'doctrine' => [
                'table_prefix' => 'acme_',
                'connection'   => 'reporting',
            ],
        ]]);

        self::assertSame('en', $config['default_locale']);
        self::assertSame(['en', 'es'], $config['locales']);
        self::assertSame(['landing'], $config['pages']);
        self::assertSame(['ROLE_ADMIN'], $config['security']['access_roles']);
        self::assertSame('app.access_checker', $config['security']['access_checker']);
        self::assertTrue($config['security']['allow_unauthenticated']);
        self::assertSame('@App/layout.html.twig', $config['web_ui']['layout_template']);
        self::assertSame('custom', $config['web_ui']['css_framework']);
        self::assertSame(['home' => 'app_home'], $config['web_ui']['page_routes']);
        self::assertSame('acme_', $config['doctrine']['table_prefix']);
        self::assertSame('reporting', $config['doctrine']['connection']);
        self::assertSame('allowlist', $config['html']['sanitize']['strategy']);
        self::assertNull($config['html']['sanitize']['service']);
    }

    public function testHtmlSanitizeStrategyAllowlist(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [[
            'html' => [
                'sanitize' => [
                    'strategy' => 'allowlist',
                ],
            ],
        ]]);

        self::assertSame('allowlist', $config['html']['sanitize']['strategy']);
    }

    public function testHtmlSanitizeStrategyNoneStillAllowed(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [[
            'html' => [
                'sanitize' => [
                    'strategy' => 'none',
                ],
            ],
        ]]);

        self::assertSame('none', $config['html']['sanitize']['strategy']);
    }
}
