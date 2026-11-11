<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Event;

/**
 * The block order of a page layout changed (admin reorder).
 *
 * Plain value object (IDs and scalars only, no Doctrine or Symfony types): dispatched after the write is committed
 * (2.2, rules 4/7). Never dispatched for a write that was rolled back.
 */
final readonly class PageLayoutChangedEvent
{
    /**
     * @param list<int> $entryIds Layout entry IDs in their new order
     */
    public function __construct(
        public string $pageKey,
        public array $entryIds,
    ) {
    }
}
