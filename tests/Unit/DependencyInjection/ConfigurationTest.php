<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\DependencyInjection;

use Nowo\PageLayoutKitBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testAliasAndFrameworksStayStable(): void
    {
        self::assertSame('nowo_page_layout_kit', Configuration::ALIAS);
        self::assertContains('tailwind', Configuration::CSS_FRAMEWORKS);
        self::assertContains('custom', Configuration::CSS_FRAMEWORKS);
    }

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
        self::assertSame('acme_', $config['doctrine']['table_prefix']);
        self::assertSame('reporting', $config['doctrine']['connection']);
    }
}
