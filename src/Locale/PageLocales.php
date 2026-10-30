<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Locale;

/**
 * Config-backed locale catalog for page blocks.
 *
 * Immutable service built from `nowo_page_layout_kit.default_locale` / `nowo_page_layout_kit.locales`.
 * Inject it and use getDefault() / getAll(); there is no static accessor, so nothing is shared
 * across FrankenPHP worker requests.
 */
final readonly class PageLocales
{
    /**
     * @param list<string> $locales
     */
    public function __construct(
        private string $defaultLocale,
        private array $locales,
    ) {
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
}
