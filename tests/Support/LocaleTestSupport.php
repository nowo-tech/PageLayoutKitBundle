<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Support;

use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use ReflectionProperty;

final class LocaleTestSupport
{
    public static function bindDefaults(): void
    {
        PageLocales::bind(new PageLocales('es', ['es', 'en']));
    }

    /**
     * Runs the callback with the deprecated static PageLocales binding cleared, then restores it.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public static function withoutStaticBinding(callable $callback): mixed
    {
        $property = new ReflectionProperty(PageLocales::class, 'instance');
        $previous = $property->getValue();
        $property->setValue(null, null);

        try {
            return $callback();
        } finally {
            $property->setValue(null, $previous);
        }
    }
}
