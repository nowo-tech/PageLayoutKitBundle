<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nowo\PageLayoutKitBundle\Model\TranslatableBlockTrait;
use Nowo\PageLayoutKitBundle\Repository\PageTextBlockRepository;

#[ORM\Entity(repositoryClass: PageTextBlockRepository::class)]
#[ORM\Table(name: 'content_page_text_block')]
/**
 * Text section block keyed by section identifier.
 */
class PageTextBlock
{
    /** @use TranslatableBlockTrait<PageTextBlockTranslation> */
    use TranslatableBlockTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $sectionKey = '';

    /** @var Collection<int, PageTextBlockTranslation> */
    #[ORM\OneToMany(targetEntity: PageTextBlockTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    /** @return array<string, mixed> */
    public function toArray(string $locale): array
    {
        return array_merge(
            ['sectionKey' => $this->sectionKey],
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

    /** @return Collection<int, PageTextBlockTranslation> */
    public function getTranslations(): Collection
    {
        return $this->translations;
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

    /** @return class-string<PageTextBlockTranslation> */
    protected function translationClass(): string
    {
        return PageTextBlockTranslation::class;
    }
}
