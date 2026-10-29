<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Locale;

use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class PageLocalesTest extends TestCase
{
    public function testInstanceReturnsConfiguredLocales(): void
    {
        $locales = new PageLocales('es', ['es', 'en']);

        self::assertSame('es', $locales->getDefault());
        self::assertSame(['es', 'en'], $locales->getAll());
    }

    public function testInstancesAreIndependent(): void
    {
        $spanish = new PageLocales('es', ['es', 'en']);
        $french  = new PageLocales('fr', ['fr', 'de']);

        self::assertSame('es', $spanish->getDefault());
        self::assertSame('fr', $french->getDefault());
        self::assertSame(['fr', 'de'], $french->getAll());
    }

    public function testHoldsNoStaticState(): void
    {
        $reflection = new ReflectionClass(PageLocales::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertSame([], $reflection->getStaticProperties());
        self::assertSame([], array_values(array_filter(
            $reflection->getMethods(),
            static fn (ReflectionMethod $method): bool => $method->isStatic(),
        )));
    }
}
