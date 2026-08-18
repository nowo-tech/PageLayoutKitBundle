<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Entity;

use Nowo\PageLayoutKitBundle\Model\TranslatableBlockTrait;
use Nowo\PageLayoutKitBundle\Repository\PageListBlockRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageListBlockRepository::class)]
#[ORM\Table(name: 'content_page_list_block')]
/**
 * List section block with ordered list items.
 */
class PageListBlock
{
    /** @use TranslatableBlockTrait<PageListBlockTranslation> */
    use TranslatableBlockTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $sectionKey = '';

    /** @var Collection<int, PageListBlockTranslation> */
    #[ORM\OneToMany(targetEntity: PageListBlockTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    /** @var Collection<int, PageListItem> */
    #[ORM\OneToMany(targetEntity: PageListItem::class, mappedBy: 'pageListBlock', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->items = new ArrayCollection();
    }

    /** @return array<string, mixed> */
    public function toArray(string $locale): array
    {
        $items = [];

        foreach ($this->items as $item) {
            $items[] = $item->toArray($locale);
        }

        return array_merge(
            ['sectionKey' => $this->sectionKey],
            $this->getTranslationOrFallback($locale)->toArray(),
            ['items' => $items],
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

    /** @return Collection<int, PageListBlockTranslation> */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addItem(PageListItem $pageListItem): self
    {
        if (!$this->items->contains($pageListItem)) {
            $this->items->add($pageListItem);
            $pageListItem->setBlock($this);
        }

        return $this;
    }

    public function removeItem(PageListItem $pageListItem): self
    {
        $this->items->removeElement($pageListItem);

        return $this;
    }

    /** @return Collection<int, PageListItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSectionKey(): string
    {
        return $this->sectionKey;
    }

    public function setSectionKey(string $sectionKey): self
    {
        $this->sectionKey = $sectionKey;

        return $this;
    }

    /** @return class-string<PageListBlockTranslation> */
    protected function translationClass(): string
    {
        return PageListBlockTranslation::class;
    }
}
