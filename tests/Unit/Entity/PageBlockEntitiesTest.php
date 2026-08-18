<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Entity;

use App\Tests\Unit\Support\LocaleTestSupport;
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
use PHPUnit\Framework\TestCase;

final class PageBlockEntitiesTest extends TestCase
{
    protected function setUp(): void
    {
        LocaleTestSupport::bindDefaults();
    }

    public function testHeroTextAndCompareBlocks(): void
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

        $text = (new PageTextBlock())->setSectionKey('intro');
        $text->addTranslation((new PageTextBlockTranslation())->setLocale('es')->setTitle('T')->setBody('B'));
        $payload = $text->toArray('es');
        self::assertSame('intro', $payload['sectionKey']);
        self::assertSame('T', $payload['title']);

        $compare = new PageCompareBlock();
        $compare->addTranslation((new PageCompareBlockTranslation())
            ->setLocale('es')
            ->setBeforeLabel('Before')
            ->setBeforeText('Old')
            ->setAfterLabel('After')
            ->setAfterText('New'));
        self::assertSame('Before', $compare->toArray('es')['beforeLabel']);
    }

    public function testCardsListCtaBlocksAndItems(): void
    {
        $cards = (new PageCardsBlock())->setSectionKey('value');
        $cards->addTranslation((new PageCardsBlockTranslation())->setLocale('es')->setTitle('Cards'));
        $card = (new PageCardItem())->setPosition(2);
        $card->addTranslation((new PageCardItemTranslation())->setLocale('es')->setTitle('Card')->setBody('Body'));
        $cards->addItem($card)->addItem($card);
        self::assertCount(1, $cards->getItems());
        self::assertSame($cards, $card->getBlock());
        $cards->removeItem($card);
        self::assertCount(0, $cards->getItems());
        $cards->addItem($card);
        self::assertSame('Cards', $cards->toArray('es')['title']);
        self::assertSame('Card', $cards->toArray('es')['items'][0]['title']);

        $list = (new PageListBlock())->setSectionKey('steps');
        $list->addTranslation((new PageListBlockTranslation())->setLocale('es')->setTitle('Steps'));
        $item = (new PageListItem())->setPosition(1);
        $item->addTranslation((new PageListItemTranslation())->setLocale('es')->setText('Step one'));
        $list->addItem($item);
        self::assertSame('Step one', $list->toArray('es')['items'][0]['text']);

        $cta = (new PageCtaBlock())->setSectionKey('footer');
        $cta->addTranslation((new PageCtaBlockTranslation())->setLocale('es')->setTitle('CTA')->setBody('Text'));
        self::assertSame('footer', $cta->toArray('es')['sectionKey']);
        self::assertSame('CTA', $cta->toArray('es')['title']);
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

    public function testTranslatableBlockTraitFallbacks(): void
    {
        // No translations at all → getTranslationOrFallback returns an empty translation object
        $block    = new PageTextBlock();
        $fallback = $block->getTranslationOrFallback('es');
        self::assertInstanceOf(PageTextBlockTranslation::class, $fallback);
        self::assertSame('', $fallback->getTitle());

        // Has 'en' translation but not 'es' or default ('es') → falls back to first
        $enTr   = (new PageTextBlockTranslation())->setLocale('en')->setTitle('EN Title');
        $block2 = new PageTextBlock();
        $block2->addTranslation($enTr);
        // getTranslation('fr') → tries 'fr', tries default 'es', falls back to first 'en'
        $got = $block2->getTranslationOrFallback('fr');
        self::assertSame('EN Title', $got->getTitle());
    }
}
