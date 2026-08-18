<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nowo\PageLayoutKitBundle\Model\TranslatableBlockTrait;
use Nowo\PageLayoutKitBundle\Repository\PageListItemRepository;

#[ORM\Entity(repositoryClass: PageListItemRepository::class)]
#[ORM\Table(name: 'content_page_list_item')]
/**
 * Single list entry within a {@see PageListBlock}.
 */
class PageListItem
{
    /** @use TranslatableBlockTrait<PageListItemTranslation> */
    use TranslatableBlockTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PageListBlock::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'block_id', nullable: false, onDelete: 'CASCADE')]
    private PageListBlock $pageListBlock;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<int, PageListItemTranslation> */
    #[ORM\OneToMany(targetEntity: PageListItemTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    /** @return array<string, mixed> */
    public function toArray(string $locale): array
    {
        return array_merge(
            ['position' => $this->position],
            $this->getTranslationOrFallback($locale)->toArray(),
        );
    }

    public function addTranslation(object $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setTranslatable($this);
        }

        return $this;
    }

    /** @return Collection<int, PageListItemTranslation> */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBlock(): PageListBlock
    {
        return $this->pageListBlock;
    }

    public function setBlock(PageListBlock $pageListBlock): self
    {
        $this->pageListBlock = $pageListBlock;

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

    /** @return class-string<PageListItemTranslation> */
    protected function translationClass(): string
    {
        return PageListItemTranslation::class;
    }
}
