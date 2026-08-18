<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Form;

use Nowo\PageLayoutKitBundle\Entity\PageCardItem;
use Nowo\PageLayoutKitBundle\Entity\PageCardsBlock;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Nowo\PageLayoutKitBundle\Entity\PageListBlock;
use Nowo\PageLayoutKitBundle\Entity\PageListItem;
use Nowo\PageLayoutKitBundle\Form\PageCardsBlockInlineModalType;
use Nowo\PageLayoutKitBundle\Form\PageHeroBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageListBlockInlineModalType;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use App\Tests\Unit\Support\LocaleTestSupport;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class PageBlockFormTypesTest extends KernelTestCase
{
    protected function setUp(): void
    {
        LocaleTestSupport::bindDefaults();
    }

    public function testPageHeroBlockModalTypeHasTranslationsForAllLocales(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);

        $block = new PageHeroBlock();
        $block->ensureTranslations();

        $form = $factory->create(PageHeroBlockModalType::class, $block, [
            'csrf_protection' => false,
        ]);

        self::assertTrue($form->has('translations'));
        self::assertCount(count(PageLocales::all()), $form->get('translations'));
        $locales = [];
        foreach ($form->get('translations') as $translationForm) {
            $locales[] = $translationForm->getData()->getLocale();
        }
        self::assertSame(PageLocales::all(), $locales);
    }

    public function testPageCardsBlockInlineModalTypePostSetDataAndSubmit(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);

        $block = (new PageCardsBlock())->setSectionKey('value');
        $block->ensureTranslations();
        $block->getTranslationOrFallback('es')->setTitle('Tarjetas');
        $item = (new PageCardItem())->setPosition(0);
        $item->ensureTranslations();
        $item->getTranslationOrFallback('es')->setTitle('Uno')->setBody('Cuerpo uno');
        $block->addItem($item);

        $form = $factory->create(PageCardsBlockInlineModalType::class, null, [
            'csrf_protection' => false,
            'block' => $block,
        ]);

        self::assertTrue($form->has('translations'));
        self::assertCount(count(PageLocales::all()), $form->get('translations'));
        self::assertStringContainsString('Uno | Cuerpo uno', (string) $form->get('translations')[0]->get('items')->getData());

        $payload = [];
        foreach (PageLocales::all() as $locale) {
            $payload[] = [
                'locale' => $locale,
                'title' => 'es' === $locale ? 'Nuevo titulo' : ('Title ' . $locale),
                'items' => 'es' === $locale
                    ? "Alpha | Body A\nBeta | Body B"
                    : "Alpha {$locale} | Body A {$locale}\nBeta {$locale} | Body B {$locale}",
            ];
        }

        $form->submit(['translations' => $payload]);

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isValid());
        self::assertSame('Nuevo titulo', $block->getTranslationOrFallback('es')->getTitle());
        self::assertCount(2, $block->getItems());
        self::assertStringStartsWith('Alpha', $block->getItems()->first()->getTranslationOrFallback('es')->getTitle());
        self::assertStringContainsString('Body', $block->getItems()->last()->getTranslationOrFallback('es')->getBody());
    }

    public function testPageCardsBlockInlineModalShrinksTrailingEmptyItems(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        $block = (new PageCardsBlock())->setSectionKey('value');
        $block->ensureTranslations();
        foreach ([0, 1] as $position) {
            $item = (new PageCardItem())->setPosition($position);
            $item->ensureTranslations();
            $item->getTranslationOrFallback('es')->setTitle('T' . $position)->setBody('B' . $position);
            $block->addItem($item);
        }

        $form = $factory->create(PageCardsBlockInlineModalType::class, null, [
            'csrf_protection' => false,
            'block' => $block,
        ]);
        $shrink = [];
        foreach (PageLocales::all() as $locale) {
            $shrink[] = [
                'locale' => $locale,
                'title' => 'Only one',
                'items' => 'Solo | Uno',
            ];
        }
        $form->submit(['translations' => $shrink]);
        self::assertCount(1, $block->getItems());
    }

    public function testPageListBlockInlineModalTypePostSetDataAndSubmit(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FormFactoryInterface::class);

        $block = (new PageListBlock())->setSectionKey('steps');
        $block->ensureTranslations();
        $block->getTranslationOrFallback('es')->setTitle('Pasos');
        $item = (new PageListItem())->setPosition(0);
        $item->ensureTranslations();
        $item->getTranslationOrFallback('es')->setText('Primero');
        $block->addItem($item);

        $form = $factory->create(PageListBlockInlineModalType::class, null, [
            'csrf_protection' => false,
            'block' => $block,
        ]);

        self::assertSame('Primero', $form->get('translations')[0]->get('items')->getData());

        $payload = [];
        foreach (PageLocales::all() as $locale) {
            $payload[] = [
                'locale' => $locale,
                'title' => 'es' === $locale ? 'Lista actualizada' : ('List ' . $locale),
                'items' => 'es' === $locale ? "Uno\nDos\n\nTres" : "One\nTwo\nThree",
            ];
        }

        $form->submit(['translations' => $payload]);

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isValid());
        self::assertSame('Lista actualizada', $block->getTranslation('es')?->getTitle());
        self::assertCount(3, $block->getItems());
        self::assertSame('Tres', $block->getItems()->last()->getTranslationOrFallback('es')->getText());
    }
}
