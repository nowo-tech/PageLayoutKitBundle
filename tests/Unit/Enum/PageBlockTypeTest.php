<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Enum;

use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use PHPUnit\Framework\TestCase;

final class PageBlockTypeTest extends TestCase
{
    public function testCasesExposeExpectedValuesAndModalFlags(): void
    {
        $cases = PageBlockType::cases();

        foreach (['hero', 'text', 'cards', 'list', 'cta', 'compare'] as $position => $value) {
            self::assertSame($cases[$position], PageBlockType::from($value));
        }

        foreach (PageBlockType::cases() as $type) {
            self::assertTrue($type->isModalEditable());
        }
    }
}
