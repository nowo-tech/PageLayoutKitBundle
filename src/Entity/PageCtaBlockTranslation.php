<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\PageLayoutKitBundle\Model\LocaleAwareTranslationInterface;
use Nowo\PageLayoutKitBundle\Repository\PageCtaBlockTranslationRepository;

#[ORM\Entity(repositoryClass: PageCtaBlockTranslationRepository::class)]
#[ORM\Table(name: 'content_page_cta_block_translation')]
#[ORM\UniqueConstraint(name: 'uniq_page_cta_block_locale', columns: ['translatable_id', 'locale'])]
/**
 * Locale-specific title and body for a {@see PageCtaBlock}.
 */
class PageCtaBlockTranslation implements LocaleAwareTranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PageCtaBlock::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'translatable_id', nullable: false, onDelete: 'CASCADE')]
    private PageCtaBlock $translatable;

    #[ORM\Column(length: 2)]
    private string $locale = 'es';

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'body'  => $this->body,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTranslatable(): PageCtaBlock
    {
        return $this->translatable;
    }

    public function setTranslatable(PageCtaBlock $translatable): self
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
