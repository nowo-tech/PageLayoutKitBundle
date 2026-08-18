<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Locale;

use RuntimeException;

/**
 * Config-backed locale catalog for page blocks (bound in NowoPageLayoutKitBundle::boot()).
 */
final class PageLocales
{
    private static ?self $instance = null;

    /**
     * @param list<string> $locales
     */
    public function __construct(
        private readonly string $defaultLocale,
        private readonly array $locales,
    ) {
    }

    public static function bind(self $instance): void
    {
        self::$instance = $instance;
    }

    public static function default(): string
    {
        return self::instance()->defaultLocale;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return self::instance()->locales;
    }

    public function getDefault(): string
    {
        return $this->defaultLocale;
    }

    /** @return list<string> */
    public function getAll(): array
    {
        return $this->locales;
    }

    private static function instance(): self
    {
        if (!self::$instance instanceof self) {
            throw new RuntimeException('PageLocales is not bound. Ensure NowoPageLayoutKitBundle is registered and booted.');
        }

        return self::$instance;
    }
}
