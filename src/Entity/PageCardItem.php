<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nowo\PageLayoutKitBundle\Model\TranslatableBlockTrait;
use Nowo\PageLayoutKitBundle\Repository\PageCardItemRepository;

#[ORM\Entity(repositoryClass: PageCardItemRepository::class)]
#[ORM\Table(name: 'content_page_card_item')]
/**
 * Single card within a {@see PageCardsBlock}.
 */
class PageCardItem
{
    /** @use TranslatableBlockTrait<PageCardItemTranslation> */
    use TranslatableBlockTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PageCardsBlock::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'block_id', nullable: false, onDelete: 'CASCADE')]
    private PageCardsBlock $pageCardsBlock;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<int, PageCardItemTranslation> */
    #[ORM\OneToMany(targetEntity: PageCardItemTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true)]
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

    /** @return Collection<int, PageCardItemTranslation> */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBlock(): PageCardsBlock
    {
        return $this->pageCardsBlock;
    }

    public function setBlock(PageCardsBlock $pageCardsBlock): self
    {
        $this->pageCardsBlock = $pageCardsBlock;

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

    /** @return class-string<PageCardItemTranslation> */
    protected function translationClass(): string
    {
        return PageCardItemTranslation::class;
    }
}
