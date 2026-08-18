<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Legacy;

/**
 * Optional host adapter for JSON page content used as layout fallback / migrate source.
 */
interface LegacyPageContentProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function contentForPage(string $pageKey, string $locale): array;

    public function defaultLocale(): string;
}
