<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Service;

/**
 * Read port of the page layouts (2.2, rule 1): ordered block DTOs by page key, no entity.
 *
 * Implemented by {@see PageBlockProvider} (autowire this interface).
 */
interface PageLayoutProviderInterface
{
    /**
     * @return list<PageBlockView>
     */
    public function getLayout(string $pageKey, ?string $locale = null): array;

    /**
     * @return array{title: string, description: string}
     */
    public function pageMeta(string $pageKey, ?string $locale = null): array;

    public function hasLayout(string $pageKey): bool;
}
