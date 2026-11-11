<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Event;

/**
 * A page block (and its translations) was saved from the admin inline editor.
 *
 * Plain value object (IDs and scalars only, no Doctrine or Symfony types): dispatched after the write is committed
 * (2.2, rules 4/7). With `doctrine.transaction: host` it is dispatched after the host's own commit.
 */
final readonly class PageBlockUpdatedEvent
{
    public function __construct(
        /** `PageBlockType` value (hero, text, cards, list, cta, compare). */
        public string $blockType,
        public int $blockId,
        /** Locale of the editing request. */
        public string $locale,
    ) {
    }
}
