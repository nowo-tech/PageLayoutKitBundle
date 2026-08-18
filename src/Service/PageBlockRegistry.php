<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Service;

use Nowo\PageLayoutKitBundle\Entity\PageCardsBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCompareBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlock;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Nowo\PageLayoutKitBundle\Entity\PageListBlock;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlock;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Repository\PageCardsBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageCompareBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageCtaBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageHeroBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageListBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageTextBlockRepository;

/**
 * Loads typed page block entities by type and id.
 */
final readonly class PageBlockRegistry
{
    public function __construct(
        private PageHeroBlockRepository $pageHeroBlockRepository,
        private PageTextBlockRepository $pageTextBlockRepository,
        private PageCardsBlockRepository $pageCardsBlockRepository,
        private PageListBlockRepository $pageListBlockRepository,
        private PageCtaBlockRepository $pageCtaBlockRepository,
        private PageCompareBlockRepository $pageCompareBlockRepository,
    ) {
    }

    public function get(PageBlockType $pageBlockType, int $id): PageHeroBlock|PageTextBlock|PageCardsBlock|PageListBlock|PageCtaBlock|PageCompareBlock|null
    {
        return match ($pageBlockType) {
            PageBlockType::Hero    => $this->pageHeroBlockRepository->findWithTranslations($id),
            PageBlockType::Text    => $this->pageTextBlockRepository->findWithTranslations($id),
            PageBlockType::Cards   => $this->pageCardsBlockRepository->findWithItemsAndTranslations($id),
            PageBlockType::List    => $this->pageListBlockRepository->findWithItemsAndTranslations($id),
            PageBlockType::Cta     => $this->pageCtaBlockRepository->findWithTranslations($id),
            PageBlockType::Compare => $this->pageCompareBlockRepository->findWithTranslations($id),
        };
    }
}
