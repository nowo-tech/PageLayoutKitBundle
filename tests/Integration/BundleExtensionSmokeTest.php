<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Integration;

use Nowo\PageLayoutKitBundle\DependencyInjection\Configuration;
use Nowo\PageLayoutKitBundle\NowoPageLayoutKitBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class BundleExtensionSmokeTest extends TestCase
{
    public function testBundleExposesExpectedName(): void
    {
        $bundle = new NowoPageLayoutKitBundle();
        self::assertSame('NowoPageLayoutKitBundle', $bundle->getName());
    }

    public function testConfigurationDefaults(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [[]]);

        self::assertSame('es', $config['default_locale']);
        self::assertSame(['es', 'en'], $config['locales']);
        self::assertSame(['home', 'contact'], $config['pages']);
        self::assertSame(['ROLE_EDITOR'], $config['security']['access_roles']);
        self::assertSame('tailwind', $config['web_ui']['css_framework']);
    }
}
