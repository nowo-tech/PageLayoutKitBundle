<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Support;

use Nowo\PageLayoutKitBundle\Locale\PageLocales;

final class LocaleTestSupport
{
    public static function bindDefaults(): void
    {
        PageLocales::bind(new PageLocales('es', ['es', 'en']));
    }
}
