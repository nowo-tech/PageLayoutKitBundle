<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Service;

use Nowo\PageLayoutKitBundle\Enum\PageBlockType;

/**
 * Resolved page block for public rendering and inline editing.
 */
final readonly class PageBlockView
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public int $layoutId,
        public string $pageKey,
        public PageBlockType $type,
        public int $blockId,
        public ?string $sectionKey,
        public array $data,
    ) {
    }

    public function isModalEditable(): bool
    {
        return $this->type->isModalEditable();
    }

    public function templateName(): string
    {
        return '@NowoPageLayoutKitBundle/blocks/' . $this->type->value . '.html.twig';
    }
}
