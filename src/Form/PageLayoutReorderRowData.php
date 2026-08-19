<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Form;

use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;

/**
 * One reorder row in the layout admin table.
 */
final class PageLayoutReorderRowData
{
    public function __construct(
        public int $id = 0,
        public int $position = 0,
    ) {
    }

    public static function fromEntry(PageLayoutEntry $entry): self
    {
        return new self(
            id: (int) $entry->getId(),
            position: $entry->getPosition(),
        );
    }
}
