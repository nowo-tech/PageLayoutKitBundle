<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Security;

use Nowo\PageLayoutKitBundle\Security\Html\StripPageLayoutHtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StripPageLayoutHtmlSanitizerTest extends TestCase
{
    public function testStripsTagsAndKeepsEntitiesEncoded(): void
    {
        $sanitizer = new StripPageLayoutHtmlSanitizer();

        self::assertSame('Hello bold &amp;', $sanitizer->sanitize('  Hello <b>bold</b> &amp;  '));
    }

    public function testEscapesBareSpecialCharacters(): void
    {
        self::assertSame('Tom &amp; &quot;Jerry&quot; &apos;s', (new StripPageLayoutHtmlSanitizer())->sanitize("Tom & \"Jerry\" 's"));
    }

    /**
     * Regression for the 2026-10-30 audit (Appendix BJ, M2): decoding after strip_tags() revived encoded markup.
     */
    #[DataProvider('encodedMarkup')]
    public function testNeverDecodesEncodedMarkup(string $payload): void
    {
        $sanitizer = new StripPageLayoutHtmlSanitizer();
        $stored    = $sanitizer->sanitize($payload);
        $rendered  = $sanitizer->sanitize($stored);

        foreach ([$stored, $rendered] as $output) {
            self::assertStringNotContainsString('<', $output);
            self::assertStringNotContainsString('>', $output);
            self::assertStringNotContainsString('"', $output);
        }

        self::assertSame($stored, $rendered, 'Strip output must be idempotent (render-time re-sanitize).');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function encodedMarkup(): iterable
    {
        yield 'double-encoded img' => ['&amp;lt;img src=x onerror=alert(12)&amp;gt;'];
        yield 'single-encoded img' => ['&lt;img src=x onerror=alert(13)&gt;'];
        yield 'numeric entities' => ['&#60;script&#62;alert(1)&#60;/script&#62;'];
        yield 'hex entities' => ['&#x3c;img src=x onerror=alert(1)&#x3e;'];
        yield 'attribute breakout' => ['" onmouseover="alert(1)'];
        yield 'mixed tags and entities' => ['<b>x</b>&lt;svg onload=alert(1)&gt;<i>y</i>'];
    }
}
