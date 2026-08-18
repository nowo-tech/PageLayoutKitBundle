<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Entity;

use Nowo\PageLayoutKitBundle\Model\LocaleAwareTranslationInterface;
use Nowo\PageLayoutKitBundle\Repository\PageTextBlockTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageTextBlockTranslationRepository::class)]
#[ORM\Table(name: 'content_page_text_block_translation')]
#[ORM\UniqueConstraint(name: 'uniq_page_text_block_locale', columns: ['translatable_id', 'locale'])]
/**
 * Locale-specific title, body, and optional SEO for a {@see PageTextBlock}.
 */
class PageTextBlockTranslation implements LocaleAwareTranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PageTextBlock::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'translatable_id', nullable: false, onDelete: 'CASCADE')]
    private PageTextBlock $translatable;

    #[ORM\Column(length: 2)]
    private string $locale = 'es';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pageTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pageDescription = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'pageTitle' => $this->pageTitle,
            'pageDescription' => $this->pageDescription,
            'title' => $this->title,
            'body' => $this->body,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTranslatable(): PageTextBlock
    {
        return $this->translatable;
    }

    public function setTranslatable(PageTextBlock $translatable): self
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

    public function getPageTitle(): ?string
    {
        return $this->pageTitle;
    }

    public function setPageTitle(?string $pageTitle): self
    {
        $this->pageTitle = $pageTitle;

        return $this;
    }

    public function getPageDescription(): ?string
    {
        return $this->pageDescription;
    }

    public function setPageDescription(?string $pageDescription): self
    {
        $this->pageDescription = $pageDescription;

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

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }
}
