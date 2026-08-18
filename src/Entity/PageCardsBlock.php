<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nowo\PageLayoutKitBundle\Model\TranslatableBlockTrait;
use Nowo\PageLayoutKitBundle\Repository\PageCardsBlockRepository;

#[ORM\Entity(repositoryClass: PageCardsBlockRepository::class)]
#[ORM\Table(name: 'content_page_cards_block')]
/**
 * Cards grid block with ordered card items.
 */
class PageCardsBlock
{
    /** @use TranslatableBlockTrait<PageCardsBlockTranslation> */
    use TranslatableBlockTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $sectionKey = '';

    /** @var Collection<int, PageCardsBlockTranslation> */
    #[ORM\OneToMany(targetEntity: PageCardsBlockTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    /** @var Collection<int, PageCardItem> */
    #[ORM\OneToMany(targetEntity: PageCardItem::class, mappedBy: 'pageCardsBlock', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->items        = new ArrayCollection();
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

    /** @return Collection<int, PageCardsBlockTranslation> */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addItem(PageCardItem $pageCardItem): self
    {
        if (!$this->items->contains($pageCardItem)) {
            $this->items->add($pageCardItem);
            $pageCardItem->setBlock($this);
        }

        return $this;
    }

    public function removeItem(PageCardItem $pageCardItem): self
    {
        $this->items->removeElement($pageCardItem);

        return $this;
    }

    /** @return Collection<int, PageCardItem> */
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

    /** @return class-string<PageCardsBlockTranslation> */
    protected function translationClass(): string
    {
        return PageCardsBlockTranslation::class;
    }
}
