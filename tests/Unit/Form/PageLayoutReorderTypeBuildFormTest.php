<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Form;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderRowType;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;

final class PageLayoutReorderTypeBuildFormTest extends TestCase
{
    public function testReorderTypeBuildsRowsCollection(): void
    {
        $fields  = [];
        $builder = $this->createBuilder($fields);

        $this->createType(PageLayoutReorderType::class)->buildForm($builder, []);

        self::assertCount(1, $fields);
        self::assertSame('rows', $fields[0]['name']);
        self::assertSame(CollectionType::class, $fields[0]['type']);
        self::assertSame(PageLayoutReorderRowType::class, $fields[0]['options']['entry_type']);
        self::assertFalse($fields[0]['options']['allow_add']);
        self::assertFalse($fields[0]['options']['allow_delete']);
    }

    public function testReorderRowTypeBuildsHiddenIdAndPosition(): void
    {
        $fields  = [];
        $builder = $this->createBuilder($fields);

        $this->createType(PageLayoutReorderRowType::class)->buildForm($builder, []);

        self::assertSame(
            [
                ['name' => 'id', 'type' => HiddenType::class],
                ['name' => 'position', 'type' => IntegerType::class],
            ],
            array_map(static fn (array $field): array => ['name' => $field['name'], 'type' => $field['type']], $fields),
        );
        self::assertSame(0, $fields[1]['options']['attr']['min']);
        self::assertSame('width:5rem', $fields[1]['options']['attr']['style']);
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
                'hidden'              => HiddenType::class,
                'integer'             => IntegerType::class,
                CollectionType::class => CollectionType::class,
            ]),
        );
    }
}
