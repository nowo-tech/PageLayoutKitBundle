<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Security;

use Dom\HTMLDocument;
use Dom\Node;
use Nowo\PageLayoutKitBundle\Security\Html\AllowlistPageLayoutHtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function in_array;
use function sprintf;

use const LIBXML_NOERROR;
use const XML_COMMENT_NODE;

final class AllowlistPageLayoutHtmlSanitizerTest extends TestCase
{
    public function testStripsScriptTags(): void
    {
        $sanitizer = new AllowlistPageLayoutHtmlSanitizer();
        $result    = $sanitizer->sanitize('<p>Hello</p><script>alert(1)</script>');

        self::assertSame('<p>Hello</p>', $result);
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

        self::assertSame('<p>Text</p>', $result);
    }

    public function testRejectsUnsafeHrefAndSrc(): void
    {
        $sanitizer = new AllowlistPageLayoutHtmlSanitizer();
        $result    = $sanitizer->sanitize(
            '<a href="javascript:alert(1)">X</a><a href="//evil.test">Y</a><img src="//evil.test/x.png" alt="x"><img src="/local.png" alt="ok">',
        );

        self::assertStringNotContainsString('javascript:', $result);
        self::assertStringNotContainsString('evil.test', $result);
        self::assertStringContainsString('src="/local.png"', $result);
    }

    public function testAllowsRelativePathsAndMailtoLinks(): void
    {
        $sanitizer = new AllowlistPageLayoutHtmlSanitizer();
        $result    = $sanitizer->sanitize('<a href="/contact">Contact</a><a href="mailto:hi@example.com">Mail</a>');

        self::assertSame(['/contact', 'mailto:hi@example.com'], $this->attributeValues($result, 'a', 'href'));
    }

    public function testAllowsExternalHttpsImageSrc(): void
    {
        $sanitizer = new AllowlistPageLayoutHtmlSanitizer();
        $result    = $sanitizer->sanitize('<img src="https://cdn.example.com/logo.png" alt="logo">');

        self::assertSame(['https://cdn.example.com/logo.png'], $this->attributeValues($result, 'img', 'src'));
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
        self::assertSame('', $blocked);
    }

    public function testRestrictsIframeAllowPermissions(): void
    {
        $result = (new AllowlistPageLayoutHtmlSanitizer())->sanitize(
            '<iframe src="https://player.vimeo.com/video/1" allow="autoplay *; camera; Fullscreen; autoplay; geolocation; ; encrypted-media" allowfullscreen onload="x()"></iframe>'
            . '<iframe src="https://www.youtube-nocookie.com/embed/2" allow="camera; microphone"></iframe>',
        );

        self::assertSame(['autoplay; fullscreen; encrypted-media'], $this->attributeValues($result, 'iframe', 'allow'));
        self::assertSame(
            ['https://player.vimeo.com/video/1', 'https://www.youtube-nocookie.com/embed/2'],
            $this->attributeValues($result, 'iframe', 'src'),
        );
        self::assertStringNotContainsString('onload', $result);
    }

    public function testKeepsAllowlistedMarkupAndUnwrapsStructuralElements(): void
    {
        $result = (new AllowlistPageLayoutHtmlSanitizer())->sanitize(
            '<section><h2 class="t">Title</h2><p>Tom &amp; <strong>Jerry</strong><br>next</p></section><custom-widget>gone</custom-widget>',
        );

        self::assertSame('<h2 class="t">Title</h2><p>Tom &amp; <strong>Jerry</strong><br />next</p>', $result);
    }

    public function testKeepsLongBodiesIntact(): void
    {
        $body = str_repeat('<p>' . str_repeat('x', 1000) . '</p>', 60);

        self::assertSame($body, (new AllowlistPageLayoutHtmlSanitizer())->sanitize($body));
    }

    /**
     * Regression payloads of the 2026-10-30 audit (Appendix BJ, M1): unwrap skipping, iframe scheme, comment
     * parsing differentials, raw-text elements, foreign content, nesting depth.
     */
    #[DataProvider('xssPayloads')]
    public function testNeutralizesXssPayload(string $payload): void
    {
        $sanitizer = new AllowlistPageLayoutHtmlSanitizer();
        $once      = $sanitizer->sanitize($payload);

        $this->assertSafe($once);
        self::assertSame($once, $sanitizer->sanitize($once), 'Sanitizing must be idempotent (render-time re-sanitize).');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function xssPayloads(): iterable
    {
        yield 'unwrapped section child' => ['<section><img src=x onerror=alert(1)></section>'];
        yield 'nested unwrapped sections' => ['<section><section><img src=x onerror=alert(1)></section></section>'];
        yield 'nested font x4' => ['<font><font><font><font><img src=x onerror=alert(1)></font></font></font></font>'];
        yield 'deep nesting x300' => [str_repeat('<font>', 300) . '<img src=x onerror=alert(1)>' . str_repeat('</font>', 300)];
        yield 'deep allowed nesting x300' => [str_repeat('<div>', 300) . '<img src=x onerror=alert(1)>' . str_repeat('</div>', 300)];
        yield 'script in section' => ['<section><script>alert(1)</script></section>'];
        yield 'javascript link in section' => ['<section><a href="javascript:alert(1)">x</a></section>'];
        yield 'javascript iframe on allowlisted host' => ['<iframe src="javascript://www.youtube.com/%0aalert(document.domain)"></iframe>'];
        yield 'javascript iframe with newline' => ["<iframe src=\"javascript://www.youtube.com/\nalert(1)\"></iframe>"];
        yield 'data iframe on allowlisted host' => ['<iframe src="data://www.youtube.com/text/html,x"></iframe>'];
        yield 'http iframe' => ['<iframe src="http://www.youtube.com/embed/x"></iframe>'];
        yield 'iframe srcdoc' => ['<iframe src="https://www.youtube.com/embed/x" srcdoc="<script>alert(1)</script>"></iframe>'];
        yield 'comment bang close' => ['<p>x</p><!-- --!><img src=x onerror=alert(1)> -->'];
        yield 'comment short close' => ['<!--><img src=x onerror=alert(2)>-->'];
        yield 'comment dash close' => ['<!---><img src=x onerror=alert(3)>-->'];
        yield 'processing instruction' => ['<?x <img src=x onerror=alert(4)> ?>'];
        yield 'encoded markup in title' => ['<a href="https://x" title="&lt;/a&gt;&lt;img src=x onerror=alert(5)&gt;">t</a>'];
        yield 'noscript' => ['<noscript><p title="</noscript><img src=x onerror=alert(6)>"></noscript>'];
        yield 'style raw text' => ['<style><img src=x onerror=alert(7)></style>'];
        yield 'style in paragraph' => ['<p><style></style><img src=x onerror=alert(7)></p>'];
        yield 'textarea' => ['<textarea><img src=x onerror=alert(8)></textarea>'];
        yield 'xmp' => ['<xmp><img src=x onerror=alert(9)></xmp>'];
        yield 'svg style' => ['<svg><style><img src=x onerror=alert(10)></style></svg>'];
        yield 'svg script' => ['<svg><script>alert(1)</script><a xlink:href="javascript:alert(1)">x</a></svg>'];
        yield 'math mglyph' => ['<math><mtext><table><mglyph><style><img src=x onerror=alert(1)></style></mglyph></table></mtext></math>'];
        yield 'template' => ['<template><img src=x onerror=alert(1)></template>'];
        yield 'tab in javascript href' => ['<a href="java&#x09;script:alert(1)">x</a>'];
        yield 'entity javascript href' => ['<a href="&#106;avascript:alert(1)">x</a>'];
        yield 'vbscript and data links' => ['<a href="vbscript:x">a</a><a href="data:text/html,<script>alert(1)</script>">b</a>'];
        yield 'cdata' => ['<![CDATA[><img src=x onerror=alert(11)>]]>'];
        yield 'uppercase handlers' => ['<P ONMOUSEOVER="alert(1)">x</P><IMG SRC="https://x/y.png" ONERROR="alert(1)">'];
        yield 'style attribute' => ['<p style="background:url(javascript:alert(1))">x</p>'];
        yield 'object embed' => ['<object data="javascript:alert(1)"></object><embed src="javascript:alert(1)">'];
        yield 'form action' => ['<form action="javascript:alert(1)"><button formaction="javascript:alert(1)">x</button></form>'];
        yield 'meta refresh' => ['<meta http-equiv="refresh" content="0;url=javascript:alert(1)">'];
        yield 'base href' => ['<base href="javascript:alert(1)//">'];
        yield 'unclosed tag' => ['<img src=x onerror=alert(1)//'];
        yield 'null byte' => ["<img src=x on\0error=alert(1)>"];
    }

    private function assertSafe(string $html): void
    {
        $document = HTMLDocument::createFromString('<!DOCTYPE html><body>' . $html, LIBXML_NOERROR);

        foreach ($document->getElementsByTagName('*') as $element) {
            $tag = strtolower($element->localName);

            if (in_array($tag, ['html', 'head', 'body'], true)) {
                continue;
            }

            self::assertArrayHasKey($tag, AllowlistPageLayoutHtmlSanitizer::ALLOWED_ELEMENTS, sprintf('Unexpected <%s> in %s', $tag, $html));
            self::assertSame('http://www.w3.org/1999/xhtml', $element->namespaceURI, $html);

            foreach ($element->attributes as $attribute) {
                $name = strtolower($attribute->name);
                self::assertContains($name, AllowlistPageLayoutHtmlSanitizer::ALLOWED_ELEMENTS[$tag], sprintf('Unexpected %s[%s] in %s', $tag, $name, $html));
                self::assertStringNotContainsStringIgnoringCase('javascript:', $attribute->value, $html);
            }

            if ($tag === 'iframe') {
                self::assertMatchesRegularExpression('#^https://(www\.)?(youtube\.com|youtube-nocookie\.com)/|^https://player\.vimeo\.com/#', $element->getAttribute('src') ?? '', $html);
            }
        }

        self::assertCount(0, $this->commentNodes($document), $html);
    }

    /**
     * @return list<string>
     */
    private function attributeValues(string $html, string $tag, string $attribute): array
    {
        $document = HTMLDocument::createFromString('<!DOCTYPE html><body>' . $html, LIBXML_NOERROR);
        $values   = [];

        foreach ($document->getElementsByTagName($tag) as $element) {
            if ($element->hasAttribute($attribute)) {
                $values[] = (string) $element->getAttribute($attribute);
            }
        }

        return $values;
    }

    /**
     * @return list<Node>
     */
    private function commentNodes(HTMLDocument $document): array
    {
        $comments = [];
        $stack    = [$document];

        while ($stack !== []) {
            $node = array_pop($stack);

            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_COMMENT_NODE) {
                    $comments[] = $child;
                }

                $stack[] = $child;
            }
        }

        return $comments;
    }
}
