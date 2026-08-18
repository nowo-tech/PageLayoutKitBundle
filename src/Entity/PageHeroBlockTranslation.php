<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\PageLayoutKitBundle\Model\LocaleAwareTranslationInterface;
use Nowo\PageLayoutKitBundle\Repository\PageHeroBlockTranslationRepository;

#[ORM\Entity(repositoryClass: PageHeroBlockTranslationRepository::class)]
#[ORM\Table(name: 'content_page_hero_block_translation')]
#[ORM\UniqueConstraint(name: 'uniq_page_hero_block_locale', columns: ['translatable_id', 'locale'])]
/**
 * Locale-specific hero copy and SEO for a {@see PageHeroBlock}.
 */
class PageHeroBlockTranslation implements LocaleAwareTranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PageHeroBlock::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'translatable_id', nullable: false, onDelete: 'CASCADE')]
    private PageHeroBlock $translatable;

    #[ORM\Column(length: 2)]
    private string $locale = 'es';

    #[ORM\Column(length: 255)]
    private string $pageTitle = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $pageDescription = '';

    #[ORM\Column(length: 255)]
    private string $eyebrow = '';

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $subtitle = '';

    #[ORM\Column(length: 120)]
    private string $ctaPrimary = '';

    #[ORM\Column(length: 120)]
    private string $ctaSecondary = '';

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'pageTitle'       => $this->pageTitle,
            'pageDescription' => $this->pageDescription,
            'eyebrow'         => $this->eyebrow,
            'title'           => $this->title,
            'subtitle'        => $this->subtitle,
            'ctaPrimary'      => $this->ctaPrimary,
            'ctaSecondary'    => $this->ctaSecondary,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTranslatable(): PageHeroBlock
    {
        return $this->translatable;
    }

    public function setTranslatable(PageHeroBlock $translatable): self
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

    public function getPageTitle(): string
    {
        return $this->pageTitle;
    }

    public function setPageTitle(string $pageTitle): self
    {
        $this->pageTitle = $pageTitle;

        return $this;
    }

    public function getPageDescription(): string
    {
        return $this->pageDescription;
    }

    public function setPageDescription(string $pageDescription): self
    {
        $this->pageDescription = $pageDescription;

        return $this;
    }

    public function getEyebrow(): string
    {
        return $this->eyebrow;
    }

    public function setEyebrow(string $eyebrow): self
    {
        $this->eyebrow = $eyebrow;

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

    public function getSubtitle(): string
    {
        return $this->subtitle;
    }

    public function setSubtitle(string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function getCtaPrimary(): string
    {
        return $this->ctaPrimary;
    }

    public function setCtaPrimary(string $ctaPrimary): self
    {
        $this->ctaPrimary = $ctaPrimary;

        return $this;
    }

    public function getCtaSecondary(): string
    {
        return $this->ctaSecondary;
    }

    public function setCtaSecondary(string $ctaSecondary): self
    {
        $this->ctaSecondary = $ctaSecondary;

        return $this;
    }
}
