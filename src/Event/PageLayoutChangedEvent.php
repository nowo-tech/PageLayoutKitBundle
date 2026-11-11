<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Event;

/**
 * The block order of a page layout changed (admin reorder).
 *
 * Plain value object (IDs and scalars only, no Doctrine or Symfony types): dispatched after the write is committed
 * (2.2, rules 4/7). With `doctrine.transaction: host` it is dispatched after the host's own commit.
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
