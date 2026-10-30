<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Security;

use Nowo\PageLayoutKitBundle\Security\Html\PageLayoutUrlAttributeSanitizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class PageLayoutUrlAttributeSanitizerTest extends TestCase
{
    public function testDeclaresSupportedElementsAndAttributes(): void
    {
        $sanitizer = new PageLayoutUrlAttributeSanitizer();

        self::assertSame(['a', 'img', 'iframe'], $sanitizer->getSupportedElements());
        self::assertSame(['href', 'src', 'allow'], $sanitizer->getSupportedAttributes());
    }

    public function testAppliesUrlPolicyPerElement(): void
    {
        $sanitizer = new PageLayoutUrlAttributeSanitizer();
        $config    = new HtmlSanitizerConfig();

        self::assertSame('/page', $sanitizer->sanitizeAttribute('a', 'href', ' /page ', $config));
        self::assertNull($sanitizer->sanitizeAttribute('a', 'href', '//evil.test', $config));
        self::assertNull($sanitizer->sanitizeAttribute('a', 'href', 'page', $config));
        self::assertSame('https://cdn.test/a.png', $sanitizer->sanitizeAttribute('img', 'src', 'https://cdn.test/a.png', $config));
        self::assertNull($sanitizer->sanitizeAttribute('img', 'src', 'mailto:x@y.test', $config));
        self::assertSame('https://WWW.YouTube.com/embed/x', $sanitizer->sanitizeAttribute('iframe', 'src', 'https://WWW.YouTube.com/embed/x', $config));
        self::assertNull($sanitizer->sanitizeAttribute('iframe', 'src', 'https:///www.youtube.com', $config));
        self::assertNull($sanitizer->sanitizeAttribute('iframe', 'allow', 'camera; microphone', $config));
        // Appendix BJ N1: parser-differential URLs are refused whatever symfony/html-sanitizer version is installed.
        foreach ([
            ['iframe', 'src', 'https://evil.tld\\@www.youtube.com/'],
            ['iframe', 'src', 'https://evil.tld@www.youtube.com/'],
            ['iframe', 'src', "https://www.youtube.com/embed/x\ty"],
            ['a', 'href', '/\\evil.tld'],
            ['a', 'href', 'https://user@evil.tld/'],
            ['a', 'href', '//user@evil.tld/'],
            ['a', 'href', "/a\x00b"],
            ['img', 'src', 'https://cdn.test/a b.png'],
            ['img', 'src', "/a\x7fb.png"],
        ] as [$element, $attribute, $url]) {
            self::assertNull($sanitizer->sanitizeAttribute($element, $attribute, $url, $config), $url);
        }

        self::assertSame('mailto:hi@example.com', $sanitizer->sanitizeAttribute('a', 'href', 'mailto:hi@example.com', $config));
        self::assertSame('https://example.com/a?x=@y', $sanitizer->sanitizeAttribute('a', 'href', 'https://example.com/a?x=@y', $config));

        // Combinations outside the policy (not reachable with the bundle's allowlist) pass through unchanged.
        self::assertSame('x', $sanitizer->sanitizeAttribute('a', 'src', 'x', $config));
    }
}
