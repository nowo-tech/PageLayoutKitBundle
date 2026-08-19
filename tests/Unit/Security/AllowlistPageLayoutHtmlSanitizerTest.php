<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Security;

use Nowo\PageLayoutKitBundle\Security\Html\AllowlistPageLayoutHtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class AllowlistPageLayoutHtmlSanitizerTest extends TestCase
{
    public function testStripsScriptTags(): void
    {
        $sanitizer = new AllowlistPageLayoutHtmlSanitizer();
        $result    = $sanitizer->sanitize('<p>Hello</p><script>alert(1)</script>');

        self::assertStringContainsString('<p>Hello</p>', $result);
        self::assertStringNotContainsString('script', $result);
    }

    public function testAllowsSafeLinks(): void
    {
        $sanitizer = new AllowlistPageLayoutHtmlSanitizer();
        $result    = $sanitizer->sanitize('<a href="https://example.com">Link</a>');

        self::assertStringContainsString('href="https://example.com"', $result);
        self::assertStringContainsString('rel="noopener noreferrer"', $result);
    }

    public function testReturnsEmptyStringForBlankInput(): void
    {
        self::assertSame('', (new AllowlistPageLayoutHtmlSanitizer())->sanitize('   '));
    }

    public function testRemovesDisallowedTagsAndAttributes(): void
    {
        $sanitizer = new AllowlistPageLayoutHtmlSanitizer();
        $result    = $sanitizer->sanitize('<p onclick="evil()">Text</p><object data="x"></object>');

        self::assertStringContainsString('<p>Text</p>', $result);
        self::assertStringNotContainsString('onclick', $result);
        self::assertStringNotContainsString('object', $result);
    }

    public function testRejectsUnsafeHrefAndSrc(): void
    {
        $sanitizer = new AllowlistPageLayoutHtmlSanitizer();
        $result    = $sanitizer->sanitize(
            '<a href="javascript:alert(1)">X</a><img src="//evil.test/x.png" alt="x"><img src="/local.png" alt="ok">',
        );

        self::assertStringNotContainsString('javascript:', $result);
        self::assertStringNotContainsString('evil.test', $result);
        self::assertStringContainsString('src="/local.png"', $result);
    }

    public function testAllowsRelativePathsAndMailtoLinks(): void
    {
        $sanitizer = new AllowlistPageLayoutHtmlSanitizer();
        $result    = $sanitizer->sanitize('<a href="/contact">Contact</a><a href="mailto:hi@example.com">Mail</a>');

        self::assertStringContainsString('href="/contact"', $result);
        self::assertStringContainsString('href="mailto:hi@example.com"', $result);
    }

    public function testAllowsExternalHttpsImageSrc(): void
    {
        $sanitizer = new AllowlistPageLayoutHtmlSanitizer();
        $result    = $sanitizer->sanitize('<img src="https://cdn.example.com/logo.png" alt="logo">');

        self::assertStringContainsString('https://cdn.example.com/logo.png', $result);
    }

    public function testRemovesEmptyHrefFromLinks(): void
    {
        $sanitizer = new AllowlistPageLayoutHtmlSanitizer();
        $result    = $sanitizer->sanitize('<a href="">Empty</a>');

        self::assertStringNotContainsString('href=', $result);
        self::assertStringContainsString('Empty', $result);
    }

    public function testAllowsYouTubeIframeAndRemovesUnknownEmbeds(): void
    {
        $sanitizer = new AllowlistPageLayoutHtmlSanitizer();
        $allowed   = $sanitizer->sanitize('<iframe src="https://www.youtube.com/embed/abc"></iframe>');
        $blocked   = $sanitizer->sanitize('<iframe src="https://evil.example/embed"></iframe>');

        self::assertStringContainsString('youtube.com/embed/abc', $allowed);
        self::assertStringNotContainsString('iframe', $blocked);
    }
}
