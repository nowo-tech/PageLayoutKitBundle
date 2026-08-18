<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Enum;

use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use PHPUnit\Framework\TestCase;

final class PageBlockTypeTest extends TestCase
{
    public function testCasesExposeExpectedValuesAndModalFlags(): void
    {
        self::assertSame(
            ['hero', 'text', 'cards', 'list', 'cta', 'compare'],
            array_map(static fn (PageBlockType $type): string => $type->value, PageBlockType::cases()),
        );

        foreach (PageBlockType::cases() as $type) {
            self::assertTrue($type->isModalEditable());
        }
    }
}
