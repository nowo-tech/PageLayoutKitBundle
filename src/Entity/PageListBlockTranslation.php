<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Nowo\PageLayoutKitBundle\Model\LocaleAwareTranslationInterface;
use Nowo\PageLayoutKitBundle\Repository\PageListBlockTranslationRepository;

#[ORM\Entity(repositoryClass: PageListBlockTranslationRepository::class)]
#[ORM\Table(name: 'content_page_list_block_translation')]
#[ORM\UniqueConstraint(name: 'uniq_page_list_block_locale', columns: ['translatable_id', 'locale'])]
/**
 * Locale-specific section title for a {@see PageListBlock}.
 */
class PageListBlockTranslation implements LocaleAwareTranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PageListBlock::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'translatable_id', nullable: false, onDelete: 'CASCADE')]
    private PageListBlock $translatable;

    #[ORM\Column(length: 2)]
    private string $locale = 'es';

    #[ORM\Column(length: 255)]
    private string $title = '';

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTranslatable(): PageListBlock
    {
        return $this->translatable;
    }

    public function setTranslatable(PageListBlock $translatable): self
    {
        $this->translatable = $translatable;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }
}
