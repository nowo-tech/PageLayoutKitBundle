<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Security;

use Nowo\PageLayoutKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageLayoutKitBundle\Security\Html\AllowlistPageLayoutHtmlSanitizer;
use Nowo\PageLayoutKitBundle\Security\Html\NullPageLayoutHtmlSanitizer;
use Nowo\PageLayoutKitBundle\Security\Html\PageLayoutHtmlSanitizerInterface;
use Nowo\PageLayoutKitBundle\Security\Html\StripPageLayoutHtmlSanitizer;
use Nowo\PageLayoutKitBundle\Security\PageLayoutProtection;
use Nowo\PageLayoutKitBundle\Security\PageLayoutProtectionConfig;
use PHPUnit\Framework\TestCase;

final class PageLayoutProtectionTest extends TestCase
{
    public function testResolvesNoneStrategy(): void
    {
        $protection = new PageLayoutProtection(new PageLayoutProtectionConfig(HtmlSanitizeStrategy::None, null));

        self::assertInstanceOf(NullPageLayoutHtmlSanitizer::class, $protection->htmlSanitizer());
    }

    public function testResolvesStripStrategy(): void
    {
        $protection = new PageLayoutProtection(new PageLayoutProtectionConfig(HtmlSanitizeStrategy::Strip, null));

        self::assertInstanceOf(StripPageLayoutHtmlSanitizer::class, $protection->htmlSanitizer());
    }

    public function testResolvesAllowlistStrategy(): void
    {
        $protection = new PageLayoutProtection(new PageLayoutProtectionConfig(HtmlSanitizeStrategy::Allowlist, null));

        self::assertInstanceOf(AllowlistPageLayoutHtmlSanitizer::class, $protection->htmlSanitizer());
    }

    public function testServiceStrategyUsesCustomSanitizerWhenProvided(): void
    {
        $custom = new class implements PageLayoutHtmlSanitizerInterface {
            public function sanitize(string $html): string
            {
                return 'custom:' . $html;
            }
        };

        $protection = new PageLayoutProtection(
            new PageLayoutProtectionConfig(HtmlSanitizeStrategy::Service, 'app.sanitizer'),
            $custom,
        );

        self::assertSame('custom:<p>x</p>', $protection->htmlSanitizer()->sanitize('<p>x</p>'));
    }

    public function testServiceStrategyFallsBackToNullSanitizerWithoutCustom(): void
    {
        $protection = new PageLayoutProtection(
            new PageLayoutProtectionConfig(HtmlSanitizeStrategy::Service, 'app.sanitizer'),
        );

        self::assertInstanceOf(NullPageLayoutHtmlSanitizer::class, $protection->htmlSanitizer());
    }
}
