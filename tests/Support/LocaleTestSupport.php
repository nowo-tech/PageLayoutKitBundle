<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Support;

use Nowo\PageLayoutKitBundle\Locale\PageLocales;

final class LocaleTestSupport
{
    /**
     * The locale catalog most tests use: default `es`, locales `es` and `en`.
     */
    public static function defaults(): PageLocales
    {
        return new PageLocales('es', ['es', 'en']);
    }
}
