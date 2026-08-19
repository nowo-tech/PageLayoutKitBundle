<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Form;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PageLayoutKitBundle\Form\AbstractPageLayoutFormType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

final class AbstractPageLayoutFormTypeTest extends TestCase
{
    public function testAddCkeditorFallsBackToTextareaAndDropsEditorOnlyOptions(): void
    {
        $fields  = [];
        $builder = $this->createBuilder($fields);
        $type    = $this->createType();

        $type->buildCkeditorField($builder, 'body', [
            'config'     => 'simple',
            'theme'      => 'auto',
            'min_height' => '180px',
            'attr'       => ['class' => 'wide'],
        ]);

        self::assertCount(1, $fields);
        self::assertSame('body', $fields[0]['name']);
        self::assertSame(TextareaType::class, $fields[0]['type']);
        self::assertArrayNotHasKey('config', $fields[0]['options']);
        self::assertArrayNotHasKey('theme', $fields[0]['options']);
        self::assertArrayNotHasKey('min_height', $fields[0]['options']);
        self::assertSame(8, $fields[0]['options']['attr']['rows']);
        self::assertSame('wide', $fields[0]['options']['attr']['class']);
    }

    public function testAddHiddenLocaleFieldAppliesCsrfFriendlyDefaults(): void
    {
        $fields  = [];
        $builder = $this->createBuilder($fields);
        $type    = $this->createType();

        $type->buildHiddenLocaleField($builder, ['required' => false]);

        self::assertCount(1, $fields);
        self::assertSame('locale', $fields[0]['name']);
        self::assertSame(HiddenType::class, $fields[0]['type']);
    }

    public function testAddWithDefaultsAlsoAcceptsPlainTypeAliases(): void
    {
        $fields  = [];
        $builder = $this->createBuilder($fields);
        $type    = $this->createType();

        $type->buildWithDefaultsField($builder, 'body', 'textarea', []);

        self::assertSame(TextareaType::class, $fields[0]['type']);
    }

    public function testAddWithDefaultsMapsKnownTypeClassesAndSetsPlaceholderOnlyWhenMissing(): void
    {
        $fields  = [];
        $builder = $this->createBuilder($fields);
        $type    = $this->createType();

        $type->buildWithDefaultsField($builder, 'notes', TextareaType::class, []);
        $type->buildWithDefaultsField($builder, 'token', HiddenType::class, ['placeholder' => 'keep-me']);
        $type->buildWithDefaultsField($builder, 'translations', CollectionType::class, ['label' => false]);

        self::assertSame(TextareaType::class, $fields[0]['type']);
        self::assertFalse((bool) ($fields[0]['options']['placeholder'] ?? false));

        self::assertSame(HiddenType::class, $fields[1]['type']);

        self::assertSame(CollectionType::class, $fields[2]['type']);
        self::assertFalse((bool) ($fields[2]['options']['placeholder'] ?? false));
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

    private function createType(): object
    {
        return new class(new FormOptionsMerger(['page_layout_kit' => ['translation_domain' => 'form', 'defaults' => ['attr' => [], 'row_attr' => []], 'field_types' => []]], 'page_layout_kit', new ConstraintDefinitionFactory()), new FormTypeMap(['hidden' => HiddenType::class, 'textarea' => TextareaType::class, CollectionType::class => CollectionType::class])) extends AbstractPageLayoutFormType {
            public function buildCkeditorField(FormBuilderInterface $builder, string $name, array $options): void
            {
                $this->withBuilder($builder, function () use ($name, $options): void {
                    $this->addCkeditor5Field($name, $options);
                });
            }

            public function buildHiddenLocaleField(FormBuilderInterface $builder, array $options): void
            {
                $this->withBuilder($builder, function () use ($options): void {
                    $this->addHiddenLocaleField($options);
                });
            }

            public function buildWithDefaultsField(FormBuilderInterface $builder, string $name, string $type, array $options): void
            {
                $this->addWithDefaults($builder, $name, $type, $options);
            }
        };
    }
}
