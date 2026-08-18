<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Entity;

use Nowo\PageLayoutKitBundle\Model\LocaleAwareTranslationInterface;
use Nowo\PageLayoutKitBundle\Repository\PageListItemTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageListItemTranslationRepository::class)]
#[ORM\Table(name: 'content_page_list_item_translation')]
#[ORM\UniqueConstraint(name: 'uniq_page_list_item_locale', columns: ['translatable_id', 'locale'])]
/**
 * Locale-specific text for a {@see PageListItem}.
 */
class PageListItemTranslation implements LocaleAwareTranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PageListItem::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'translatable_id', nullable: false, onDelete: 'CASCADE')]
    private PageListItem $translatable;

    #[ORM\Column(length: 2)]
    private string $locale = 'es';

    #[ORM\Column(type: Types::TEXT)]
    private string $text = '';

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTranslatable(): PageListItem
    {
        return $this->translatable;
    }

    public function setTranslatable(PageListItem $translatable): self
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

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }
}
