<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Entity;

use Nowo\PageLayoutKitBundle\Model\TranslatableBlockTrait;
use Nowo\PageLayoutKitBundle\Repository\PageCompareBlockRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageCompareBlockRepository::class)]
#[ORM\Table(name: 'content_page_compare_block')]
/**
 * Before/after comparison section block.
 */
class PageCompareBlock
{
    /** @use TranslatableBlockTrait<PageCompareBlockTranslation> */
    use TranslatableBlockTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, PageCompareBlockTranslation> */
    #[ORM\OneToMany(targetEntity: PageCompareBlockTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    /** @return array<string, string> */
    public function toArray(string $locale): array
    {
        return $this->getTranslationOrFallback($locale)->toArray();
    }

    public function addTranslation(object $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setTranslatable($this);
        }

        return $this;
    }

    /** @return Collection<int, PageCompareBlockTranslation> */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /** @return class-string<PageCompareBlockTranslation> */
    protected function translationClass(): string
    {
        return PageCompareBlockTranslation::class;
    }
}
