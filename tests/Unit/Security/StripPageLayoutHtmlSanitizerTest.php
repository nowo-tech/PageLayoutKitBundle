<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Security;

use Nowo\PageLayoutKitBundle\Security\Html\StripPageLayoutHtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class StripPageLayoutHtmlSanitizerTest extends TestCase
{
    public function testStripsTagsAndDecodesEntities(): void
    {
        $sanitizer = new StripPageLayoutHtmlSanitizer();

        self::assertSame('Hello bold &', $sanitizer->sanitize('  Hello <b>bold</b> &amp;  '));
    }
}
