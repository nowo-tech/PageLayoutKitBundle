<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Entity;

use Nowo\PageLayoutKitBundle\Entity\PageCardItem;
use Nowo\PageLayoutKitBundle\Entity\PageCardItemTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageCardsBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCardsBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageCompareBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCompareBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Entity\PageListBlock;
use Nowo\PageLayoutKitBundle\Entity\PageListBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageListItem;
use Nowo\PageLayoutKitBundle\Entity\PageListItemTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlock;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlockTranslation;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Tests\Support\LocaleTestSupport;
use PHPUnit\Framework\TestCase;

final class PageBlockEntitiesTest extends TestCase
{
    protected function setUp(): void
    {
        LocaleTestSupport::bindDefaults();
    }

    public function testHeroTextAndCompareBlocksExposeExpectedData(): void
    {
        $hero   = new PageHeroBlock();
        $heroTr = (new PageHeroBlockTranslation())
            ->setLocale('es')
            ->setPageTitle('SEO')
            ->setPageDescription('Desc')
            ->setEyebrow('Hi')
            ->setTitle('Hero')
            ->setSubtitle('Sub')
            ->setCtaPrimary('Go')
            ->setCtaSecondary('More');
        $hero->addTranslation($heroTr);

        self::assertSame('SEO', $hero->toArray('es')['pageTitle']);
        self::assertSame($heroTr, $hero->getTranslation('es'));
        self::assertSame($hero, $heroTr->getTranslatable());
        self::assertSame('More', $heroTr->getCtaSecondary());
        self::assertSame('Sub', $heroTr->getSubtitle());

        $text            = (new PageTextBlock())->setSectionKey('intro');
        $textTranslation = (new PageTextBlockTranslation())
            ->setLocale('es')
            ->setPageTitle('Meta title')
            ->setPageDescription('Meta description')
            ->setTitle('T')
            ->setBody('B');
        $text->addTranslation($textTranslation);
        $payload = $text->toArray('es');

        self::assertSame('intro', $payload['sectionKey']);
        self::assertSame('Meta title', $payload['pageTitle']);
        self::assertSame('Meta description', $payload['pageDescription']);
        self::assertSame('T', $payload['title']);
        self::assertSame('B', $textTranslation->getBody());
        self::assertSame($text, $textTranslation->getTranslatable());

        $compare            = new PageCompareBlock();
        $compareTranslation = (new PageCompareBlockTranslation())
            ->setLocale('es')
            ->setBeforeLabel('Before')
            ->setBeforeText('Old')
            ->setAfterLabel('After')
            ->setAfterText('New');
        $compare->addTranslation($compareTranslation);

        self::assertSame('Before', $compare->toArray('es')['beforeLabel']);
        self::assertSame('Old', $compareTranslation->getBeforeText());
        self::assertSame('After', $compareTranslation->getAfterLabel());
        self::assertSame('New', $compareTranslation->getAfterText());
        self::assertSame($compare, $compareTranslation->getTranslatable());
    }

    public function testCardsListCtaBlocksAndItemsManageRelationsAndArrays(): void
    {
        $cards            = (new PageCardsBlock())->setSectionKey('value');
        $cardsTranslation = (new PageCardsBlockTranslation())->setLocale('es')->setTitle('Cards');
        $cards->addTranslation($cardsTranslation);
        $card            = (new PageCardItem())->setPosition(2);
        $cardTranslation = (new PageCardItemTranslation())->setLocale('es')->setTitle('Card')->setBody('Body');
        $card->addTranslation($cardTranslation);
        $cards->addItem($card)->addItem($card);

        self::assertCount(1, $cards->getItems());
        self::assertSame($cards, $card->getBlock());
        self::assertSame($card, $cardTranslation->getTranslatable());
        self::assertSame(2, $card->getPosition());
        self::assertSame('Cards', $cardsTranslation->getTitle());
        self::assertSame($cards, $cardsTranslation->getTranslatable());
        $cards->removeItem($card);
        self::assertCount(0, $cards->getItems());
        $cards->addItem($card);
        self::assertSame('Cards', $cards->toArray('es')['title']);
        self::assertSame('Card', $cards->toArray('es')['items'][0]['title']);
        self::assertSame('Body', $cards->toArray('es')['items'][0]['body']);

        $list            = (new PageListBlock())->setSectionKey('steps');
        $listTranslation = (new PageListBlockTranslation())->setLocale('es')->setTitle('Steps');
        $list->addTranslation($listTranslation);
        $item            = (new PageListItem())->setPosition(1);
        $itemTranslation = (new PageListItemTranslation())->setLocale('es')->setText('Step one');
        $item->addTranslation($itemTranslation);
        $list->addItem($item);

        self::assertSame('Step one', $list->toArray('es')['items'][0]['text']);
        self::assertSame(1, $item->getPosition());
        self::assertSame($list, $item->getBlock());
        self::assertSame($item, $itemTranslation->getTranslatable());
        self::assertSame('Steps', $listTranslation->getTitle());
        $list->removeItem($item);
        self::assertCount(0, $list->getItems());
        $list->addItem($item);

        $cta            = (new PageCtaBlock())->setSectionKey('footer');
        $ctaTranslation = (new PageCtaBlockTranslation())->setLocale('es')->setTitle('CTA')->setBody('Text');
        $cta->addTranslation($ctaTranslation);

        self::assertSame('footer', $cta->toArray('es')['sectionKey']);
        self::assertSame('CTA', $cta->toArray('es')['title']);
        self::assertSame('Text', $ctaTranslation->getBody());
        self::assertSame($cta, $ctaTranslation->getTranslatable());
    }

    public function testLayoutEntryAndEnsureTranslations(): void
    {
        $entry = (new PageLayoutEntry())
            ->setPageKey('home')
            ->setBlockType(PageBlockType::Hero)
            ->setBlockId(5)
            ->setPosition(1)
            ->setEnabled(false);

        self::assertNull($entry->getId());
        self::assertSame('home', $entry->getPageKey());
        self::assertSame(PageBlockType::Hero, $entry->getBlockType());
        self::assertSame(5, $entry->getBlockId());
        self::assertSame(1, $entry->getPosition());
        self::assertFalse($entry->isEnabled());

        $hero = new PageHeroBlock();
        $hero->ensureTranslations();
        self::assertNotNull($hero->getTranslation('es'));
        self::assertNotNull($hero->getTranslation('en'));
    }

    public function testRemainingEntityAccessorsAreReachable(): void
    {
        $cardsBlock            = (new PageCardsBlock())->setSectionKey('cards');
        $cardsBlockTranslation = (new PageCardsBlockTranslation())->setLocale('es')->setTitle('Cards');
        $cardsBlock->addTranslation($cardsBlockTranslation);

        $cardItem            = (new PageCardItem())->setPosition(1);
        $cardItemTranslation = (new PageCardItemTranslation())->setLocale('es')->setTitle('Item')->setBody('Body');
        $cardItem->setBlock($cardsBlock)->addTranslation($cardItemTranslation);

        $listBlock            = (new PageListBlock())->setSectionKey('list');
        $listBlockTranslation = (new PageListBlockTranslation())->setLocale('es')->setTitle('List');
        $listBlock->addTranslation($listBlockTranslation);

        $listItem            = (new PageListItem())->setPosition(2);
        $listItemTranslation = (new PageListItemTranslation())->setLocale('es')->setText('Line');
        $listItem->setBlock($listBlock)->addTranslation($listItemTranslation);

        $ctaBlock       = (new PageCtaBlock())->setSectionKey('cta');
        $ctaTranslation = (new PageCtaBlockTranslation())->setLocale('es')->setTitle('CTA')->setBody('CTA body');
        $ctaBlock->addTranslation($ctaTranslation);

        $compareBlock       = new PageCompareBlock();
        $compareTranslation = (new PageCompareBlockTranslation())
            ->setLocale('es')
            ->setBeforeLabel('Antes')
            ->setBeforeText('Viejo')
            ->setAfterLabel('Despues')
            ->setAfterText('Nuevo');
        $compareBlock->addTranslation($compareTranslation);

        $heroBlock       = new PageHeroBlock();
        $heroTranslation = (new PageHeroBlockTranslation())
            ->setLocale('es')
            ->setPageTitle('SEO')
            ->setPageDescription('Desc')
            ->setEyebrow('Hola')
            ->setTitle('Hero')
            ->setSubtitle('Sub')
            ->setCtaPrimary('Go')
            ->setCtaSecondary('More');
        $heroBlock->addTranslation($heroTranslation);

        $textBlock       = new PageTextBlock();
        $textTranslation = (new PageTextBlockTranslation())
            ->setLocale('es')
            ->setPageTitle('Meta')
            ->setPageDescription('Description')
            ->setTitle('Title')
            ->setBody('Body');
        $textBlock->addTranslation($textTranslation);

        self::assertNull($cardsBlock->getId());
        self::assertSame('cards', $cardsBlock->getSectionKey());
        self::assertNull($cardsBlockTranslation->getId());
        self::assertSame($cardsBlock, $cardsBlockTranslation->getTranslatable());
        self::assertNull($cardItem->getId());
        self::assertSame($cardsBlock, $cardItem->getBlock());
        self::assertNull($cardItemTranslation->getId());
        self::assertSame($cardItem, $cardItemTranslation->getTranslatable());

        self::assertNull($listBlock->getId());
        self::assertSame('list', $listBlock->getSectionKey());
        self::assertNull($listBlockTranslation->getId());
        self::assertSame($listBlock, $listBlockTranslation->getTranslatable());
        self::assertNull($listItem->getId());
        self::assertSame($listBlock, $listItem->getBlock());
        self::assertNull($listItemTranslation->getId());
        self::assertSame($listItem, $listItemTranslation->getTranslatable());

        self::assertSame('cta', $ctaBlock->getSectionKey());
        self::assertNull($ctaTranslation->getId());
        self::assertSame($ctaBlock, $ctaTranslation->getTranslatable());
        self::assertSame('CTA', $ctaTranslation->getTitle());

        self::assertNull($compareTranslation->getId());
        self::assertSame($compareBlock, $compareTranslation->getTranslatable());
        self::assertSame('Antes', $compareTranslation->getBeforeLabel());

        self::assertNull($heroTranslation->getId());
        self::assertSame($heroBlock, $heroTranslation->getTranslatable());
        self::assertSame('SEO', $heroTranslation->getPageTitle());
        self::assertSame('Desc', $heroTranslation->getPageDescription());
        self::assertSame('Hola', $heroTranslation->getEyebrow());
        self::assertSame('Hero', $heroTranslation->getTitle());
        self::assertSame('Go', $heroTranslation->getCtaPrimary());

        self::assertNull($textTranslation->getId());
        self::assertSame($textBlock, $textTranslation->getTranslatable());
        self::assertSame('Meta', $textTranslation->getPageTitle());
        self::assertSame('Description', $textTranslation->getPageDescription());
    }

    public function testTranslatableBlockTraitFallbacks(): void
    {
        $block    = new PageTextBlock();
        $fallback = $block->getTranslationOrFallback('es');
        self::assertInstanceOf(PageTextBlockTranslation::class, $fallback);
        self::assertSame('', $fallback->getTitle());

        $enTr   = (new PageTextBlockTranslation())->setLocale('en')->setTitle('EN Title');
        $block2 = new PageTextBlock();
        $block2->addTranslation($enTr);
        $got = $block2->getTranslationOrFallback('fr');
        self::assertSame('EN Title', $got->getTitle());
    }
}
