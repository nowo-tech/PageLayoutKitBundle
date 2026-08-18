<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Form;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PageLayoutKitBundle\Form\PageCompareBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageCompareBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageCtaBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageCtaBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageHeroBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageHeroBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageTextBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageTextBlockModalType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

final class PageBlockModalTypeBuildFormTest extends TestCase
{
    public function testHeroCtaAndCompareModalTypesBuildTranslationsCollection(): void
    {
        $expectations = [
            [PageHeroBlockModalType::class, PageHeroBlockEditType::class],
            [PageCtaBlockModalType::class, PageCtaBlockEditType::class],
            [PageCompareBlockModalType::class, PageCompareBlockEditType::class],
        ];

        foreach ($expectations as [$typeClass, $entryType]) {
            $fields = [];
            $this->createType($typeClass)->buildForm($this->createBuilder($fields), []);

            self::assertCount(1, $fields);
            self::assertSame('translations', $fields[0]['name']);
            self::assertSame(CollectionType::class, $fields[0]['type']);
            self::assertSame($entryType, $fields[0]['options']['entry_type']);
            self::assertFalse((bool) ($fields[0]['options']['allow_add'] ?? false));
            self::assertFalse((bool) ($fields[0]['options']['allow_delete'] ?? false));
        }
    }

    public function testTextModalTypePassesIncludeMetaIntoEntryOptions(): void
    {
        $fields = [];

        $this->createType(PageTextBlockModalType::class)->buildForm($this->createBuilder($fields), [
            'include_meta' => true,
        ]);

        self::assertCount(1, $fields);
        self::assertSame('translations', $fields[0]['name']);
        self::assertSame(CollectionType::class, $fields[0]['type']);
        self::assertSame(PageTextBlockEditType::class, $fields[0]['options']['entry_type']);
        self::assertTrue($fields[0]['options']['entry_options']['include_meta']);
    }

    /** @param list<array{name: string, type: string, options: array<string, mixed>}> $fields */
    private function createBuilder(array &$fields): FormBuilderInterface
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')
            ->willReturnCallback(function (string $name, string $type, array $options) use (&$fields, $builder): FormBuilderInterface {
                $fields[] = [
                    'name' => $name,
                    'type' => $type,
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
                    'defaults' => [
                        'attr' => [],
                        'row_attr' => [],
                    ],
                    'field_types' => [],
                ],
            ], 'page_layout_kit', new ConstraintDefinitionFactory()),
            new FormTypeMap([
                CollectionType::class => CollectionType::class,
            ]),
        );
    }
}
