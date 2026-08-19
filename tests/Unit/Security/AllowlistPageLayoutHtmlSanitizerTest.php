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
}
