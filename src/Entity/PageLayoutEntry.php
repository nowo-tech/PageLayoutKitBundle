<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;

#[ORM\Entity(repositoryClass: PageLayoutEntryRepository::class)]
#[ORM\Table(name: 'content_page_layout_entry')]
/**
 * Ordered block reference for a CMS page layout.
 */
class PageLayoutEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $pageKey = '';

    #[ORM\Column(name: 'block_type', length: 16, enumType: PageBlockType::class)]
    private PageBlockType $pageBlockType = PageBlockType::Hero;

    #[ORM\Column]
    private int $blockId = 0;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $enabled = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPageKey(): string
    {
        return $this->pageKey;
    }

    public function setPageKey(string $pageKey): self
    {
        $this->pageKey = $pageKey;

        return $this;
    }

    public function getBlockType(): PageBlockType
    {
        return $this->pageBlockType;
    }

    public function setBlockType(PageBlockType $pageBlockType): self
    {
        $this->pageBlockType = $pageBlockType;

        return $this;
    }

    public function getBlockId(): int
    {
        return $this->blockId;
    }

    public function setBlockId(int $blockId): self
    {
        $this->blockId = $blockId;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }
}
