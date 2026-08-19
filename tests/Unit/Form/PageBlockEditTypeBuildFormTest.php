<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Form;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PageLayoutKitBundle\Form\PageBlockLocalePanelType;
use Nowo\PageLayoutKitBundle\Form\PageCompareBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageCtaBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageHeroBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageTextBlockEditType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class PageBlockEditTypeBuildFormTest extends TestCase
{
    public function testLocalePanelBuildsExpectedFields(): void
    {
        $fields  = [];
        $builder = $this->createBuilder($fields);

        $this->createType(PageBlockLocalePanelType::class)->buildForm($builder, []);

        self::assertSame(
            [
                ['name' => 'locale', 'type' => HiddenType::class],
                ['name' => 'title', 'type' => TextType::class],
                ['name' => 'items', 'type' => TextareaType::class],
            ],
            array_map(static fn (array $field): array => ['name' => $field['name'], 'type' => $field['type']], $fields),
        );
        self::assertSame(10, $fields[2]['options']['attr']['rows']);
    }

    public function testHeroEditTypeBuildsSeoAndCtaFields(): void
    {
        $fields  = [];
        $builder = $this->createBuilder($fields);

        $this->createType(PageHeroBlockEditType::class)->buildForm($builder, []);

        self::assertSame(
            ['locale', 'pageTitle', 'pageDescription', 'eyebrow', 'title', 'subtitle', 'ctaPrimary', 'ctaSecondary'],
            array_column($fields, 'name'),
        );
        self::assertSame(TextareaType::class, $fields[2]['type']);
        self::assertSame(TextareaType::class, $fields[5]['type']);
    }

    public function testTextEditTypeCanIncludeMetaFields(): void
    {
        $withoutMeta = [];
        $withMeta    = [];

        $this->createType(PageTextBlockEditType::class)->buildForm($this->createBuilder($withoutMeta), [
            'include_meta' => false,
        ]);
        $this->createType(PageTextBlockEditType::class)->buildForm($this->createBuilder($withMeta), [
            'include_meta' => true,
        ]);

        self::assertSame(['locale', 'title', 'body'], array_column($withoutMeta, 'name'));
        self::assertSame(['locale', 'pageTitle', 'pageDescription', 'title', 'body'], array_column($withMeta, 'name'));
        self::assertSame(TextareaType::class, $withMeta[2]['type']);
        self::assertFalse($withMeta[1]['options']['required']);
        self::assertFalse($withMeta[2]['options']['required']);
        self::assertSame(TextareaType::class, $withMeta[4]['type']);
        self::assertSame(8, $withMeta[4]['options']['attr']['rows']);
        self::assertArrayNotHasKey('config', $withMeta[4]['options']);
    }

    public function testCtaEditTypeFallsBackToTextareaForBody(): void
    {
        $fields = [];

        $this->createType(PageCtaBlockEditType::class)->buildForm($this->createBuilder($fields), []);

        self::assertSame(['locale', 'title', 'body'], array_column($fields, 'name'));
        self::assertSame(TextareaType::class, $fields[2]['type']);
        self::assertSame(8, $fields[2]['options']['attr']['rows']);
        self::assertArrayNotHasKey('theme', $fields[2]['options']);
        self::assertArrayNotHasKey('min_height', $fields[2]['options']);
    }

    public function testCompareEditTypeBuildsBeforeAndAfterFields(): void
    {
        $fields = [];

        $this->createType(PageCompareBlockEditType::class)->buildForm($this->createBuilder($fields), []);

        self::assertSame(
            ['locale', 'beforeLabel', 'beforeText', 'afterLabel', 'afterText'],
            array_column($fields, 'name'),
        );
        self::assertSame(TextareaType::class, $fields[2]['type']);
        self::assertSame(TextareaType::class, $fields[4]['type']);
        self::assertSame(8, $fields[2]['options']['attr']['rows']);
        self::assertSame(8, $fields[4]['options']['attr']['rows']);
    }

    /** @param list<array{name: string, type: string, options: array<string, mixed>}> $fields */
    private function createBuilder(array &$fields): FormBuilderInterface
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')
            ->willReturnCallback(static function (string $name, string $type, array $options) use (&$fields, $builder): FormBuilderInterface {
                $fields[] = [
                    'name'    => $name,
                    'type'    => $type,
                    'options' => $options,
                ];

                return $builder;
            });

        return $builder;
    }

    /** @param class-string<object> $typeClass */
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
            new FormTypeMap([
                'hidden'   => HiddenType::class,
                'text'     => TextType::class,
                'textarea' => TextareaType::class,
            ]),
        );
    }
}
