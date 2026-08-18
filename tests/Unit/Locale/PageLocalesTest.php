<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Locale;

use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PageLocalesTest extends TestCase
{
    public function testStaticAccessRequiresABoundInstance(): void
    {
        $this->unbindPageLocales();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PageLocales is not bound.');

        PageLocales::default();
    }

    public function testBoundAndInstanceAccessReturnConfiguredLocales(): void
    {
        $locales = new PageLocales('es', ['es', 'en']);
        PageLocales::bind($locales);

        self::assertSame('es', PageLocales::default());
        self::assertSame(['es', 'en'], PageLocales::all());
        self::assertSame('es', $locales->getDefault());
        self::assertSame(['es', 'en'], $locales->getAll());
    }

    private function unbindPageLocales(): void
    {
        $reflection = new \ReflectionClass(PageLocales::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
}
