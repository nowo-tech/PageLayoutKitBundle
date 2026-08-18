<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Form;

/**
 * Non-Doctrine DTO for cards/list modal locale tabs (title + items textarea).
 */
final class PageBlockLocalePanelData
{
    public function __construct(
        public string $locale = '',
        public string $title = '',
        public string $items = '',
    ) {
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
