<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Entity;

use Nowo\PageLayoutKitBundle\Model\LocaleAwareTranslationInterface;
use Nowo\PageLayoutKitBundle\Repository\PageCompareBlockTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageCompareBlockTranslationRepository::class)]
#[ORM\Table(name: 'content_page_compare_block_translation')]
#[ORM\UniqueConstraint(name: 'uniq_page_compare_block_locale', columns: ['translatable_id', 'locale'])]
/**
 * Locale-specific before/after labels and copy for a {@see PageCompareBlock}.
 */
class PageCompareBlockTranslation implements LocaleAwareTranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PageCompareBlock::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'translatable_id', nullable: false, onDelete: 'CASCADE')]
    private PageCompareBlock $translatable;

    #[ORM\Column(length: 2)]
    private string $locale = 'es';

    #[ORM\Column(length: 80)]
    private string $beforeLabel = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $beforeText = '';

    #[ORM\Column(length: 80)]
    private string $afterLabel = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $afterText = '';

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'beforeLabel' => $this->beforeLabel,
            'beforeText' => $this->beforeText,
            'afterLabel' => $this->afterLabel,
            'afterText' => $this->afterText,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTranslatable(): PageCompareBlock
    {
        return $this->translatable;
    }

    public function setTranslatable(PageCompareBlock $translatable): self
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

    public function getBeforeLabel(): string
    {
        return $this->beforeLabel;
    }

    public function setBeforeLabel(string $beforeLabel): self
    {
        $this->beforeLabel = $beforeLabel;

        return $this;
    }

    public function getBeforeText(): string
    {
        return $this->beforeText;
    }

    public function setBeforeText(string $beforeText): self
    {
        $this->beforeText = $beforeText;

        return $this;
    }

    public function getAfterLabel(): string
    {
        return $this->afterLabel;
    }

    public function setAfterLabel(string $afterLabel): self
    {
        $this->afterLabel = $afterLabel;

        return $this;
    }

    public function getAfterText(): string
    {
        return $this->afterText;
    }

    public function setAfterText(string $afterText): self
    {
        $this->afterText = $afterText;

        return $this;
    }
}
