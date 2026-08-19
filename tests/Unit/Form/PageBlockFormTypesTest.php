<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Form;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PageLayoutKitBundle\Entity\PageCardsBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCompareBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCompareBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageListBlock;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlock;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlockTranslation;
use Nowo\PageLayoutKitBundle\Form\PageBlockLocalePanelData;
use Nowo\PageLayoutKitBundle\Form\PageBlockLocalePanelType;
use Nowo\PageLayoutKitBundle\Form\PageCardsBlockInlineModalType;
use Nowo\PageLayoutKitBundle\Form\PageCompareBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageCompareBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageCtaBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageCtaBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageHeroBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageHeroBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageListBlockInlineModalType;
use Nowo\PageLayoutKitBundle\Form\PageTextBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageTextBlockModalType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PageBlockFormTypesTest extends TestCase
{
    public function testModalAndEditTypesExposeExpectedOptionDefaults(): void
    {
        $heroModal = $this->resolveOptions($this->createType(PageHeroBlockModalType::class));
        self::assertSame('form', $heroModal['translation_domain']);
        self::assertSame(PageHeroBlock::class, $heroModal['data_class']);

        $heroEdit = $this->resolveOptions($this->createType(PageHeroBlockEditType::class));
        self::assertSame('form', $heroEdit['translation_domain']);
        self::assertSame(PageHeroBlockTranslation::class, $heroEdit['data_class']);

        $textModal = $this->resolveOptions($this->createType(PageTextBlockModalType::class));
        self::assertSame('form', $textModal['translation_domain']);
        self::assertSame(PageTextBlock::class, $textModal['data_class']);
        self::assertFalse($textModal['include_meta']);

        $textEdit = $this->resolveOptions($this->createType(PageTextBlockEditType::class));
        self::assertSame('form', $textEdit['translation_domain']);
        self::assertSame(PageTextBlockTranslation::class, $textEdit['data_class']);
        self::assertFalse($textEdit['include_meta']);

        $ctaModal = $this->resolveOptions($this->createType(PageCtaBlockModalType::class));
        self::assertSame('form', $ctaModal['translation_domain']);
        self::assertSame(PageCtaBlock::class, $ctaModal['data_class']);

        $ctaEdit = $this->resolveOptions($this->createType(PageCtaBlockEditType::class));
        self::assertSame('form', $ctaEdit['translation_domain']);
        self::assertSame(PageCtaBlockTranslation::class, $ctaEdit['data_class']);

        $compareModal = $this->resolveOptions($this->createType(PageCompareBlockModalType::class));
        self::assertSame('form', $compareModal['translation_domain']);
        self::assertSame(PageCompareBlock::class, $compareModal['data_class']);

        $compareEdit = $this->resolveOptions($this->createType(PageCompareBlockEditType::class));
        self::assertSame('form', $compareEdit['translation_domain']);
        self::assertSame(PageCompareBlockTranslation::class, $compareEdit['data_class']);

        $localePanel = $this->resolveOptions($this->createType(PageBlockLocalePanelType::class));
        self::assertSame('form', $localePanel['translation_domain']);
        self::assertSame(PageBlockLocalePanelData::class, $localePanel['data_class']);
    }

    public function testInlineModalTypesRequireTheExpectedBlockOption(): void
    {
        $cardsBlock   = new PageCardsBlock();
        $cardsOptions = $this->resolveOptions($this->createType(PageCardsBlockInlineModalType::class), [
            'block' => $cardsBlock,
        ]);
        self::assertSame('form', $cardsOptions['translation_domain']);
        self::assertNull($cardsOptions['data_class']);
        self::assertSame($cardsBlock, $cardsOptions['block']);

        $listBlock   = new PageListBlock();
        $listOptions = $this->resolveOptions($this->createType(PageListBlockInlineModalType::class), [
            'block' => $listBlock,
        ]);
        self::assertSame('form', $listOptions['translation_domain']);
        self::assertNull($listOptions['data_class']);
        self::assertSame($listBlock, $listOptions['block']);
    }

    public function testInlineModalTypesRejectWrongBlockInstances(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->resolveOptions($this->createType(PageCardsBlockInlineModalType::class), [
            'block' => new PageListBlock(),
        ]);
    }

    public function testListInlineModalTypeRejectsWrongBlockInstance(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->resolveOptions($this->createType(PageListBlockInlineModalType::class), [
            'block' => new PageCardsBlock(),
        ]);
    }

    public function testLocalePanelDataReturnsLocale(): void
    {
        $data = new PageBlockLocalePanelData(locale: 'en', title: 'Title', items: 'One');
        self::assertSame('en', $data->getLocale());
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function resolveOptions(object $type, array $input = []): array
    {
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        return $resolver->resolve($input);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $typeClass
     *
     * @return T
     */
    private function createType(string $typeClass): object
    {
        return new $typeClass(
            new FormOptionsMerger([
                'page_layout_kit' => [
                    'translation_domain' => 'form',
                    'defaults'           => [
                        'attr'     => [],
                        'row_attr' => [],
                    ],
                    'field_types' => [],
                ],
            ], 'page_layout_kit', new ConstraintDefinitionFactory()),
            new FormTypeMap(),
        );
    }
}
