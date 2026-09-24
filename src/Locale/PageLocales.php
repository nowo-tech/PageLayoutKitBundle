<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Locale;

use RuntimeException;

/**
 * Config-backed locale catalog for page blocks.
 *
 * Inject this service and use getDefault()/getAll(). The static accessors are kept for backward
 * compatibility (bound in NowoPageLayoutKitBundle::boot()) and will be removed in 2.0.
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

    /**
     * @deprecated since 1.1.0, inject the PageLocales service instead; will be removed in 2.0
     */
    public static function bind(self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * @deprecated since 1.1.0, inject the PageLocales service and use getDefault() instead; will be removed in 2.0
     */
    public static function default(): string
    {
        return self::instance()->defaultLocale;
    }

    /**
     * @deprecated since 1.1.0, inject the PageLocales service and use getAll() instead; will be removed in 2.0
     *
     * @return list<string>
     */
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
