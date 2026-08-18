<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Form;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PageLayoutKitBundle\Entity\PageCardItem;
use Nowo\PageLayoutKitBundle\Entity\PageCardsBlock;
use Nowo\PageLayoutKitBundle\Entity\PageListBlock;
use Nowo\PageLayoutKitBundle\Entity\PageListItem;
use Nowo\PageLayoutKitBundle\Form\PageBlockLocalePanelData;
use Nowo\PageLayoutKitBundle\Form\PageBlockLocalePanelType;
use Nowo\PageLayoutKitBundle\Form\PageCardsBlockInlineModalType;
use Nowo\PageLayoutKitBundle\Form\PageListBlockInlineModalType;
use Nowo\PageLayoutKitBundle\Tests\Support\LocaleTestSupport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;

final class PageBlockInlineModalTypeTest extends TestCase
{
    protected function setUp(): void
    {
        LocaleTestSupport::bindDefaults();
    }

    public function testCardsInlineModalBuildsCollectionAndHydratesLocalePanels(): void
    {
        $fields = [];
        $listeners = [];
        $builder = $this->createBuilder($fields, $listeners);
        $block = $this->createCardsBlockWithOneItem();

        $this->createCardsType()->buildForm($builder, ['block' => $block]);

        self::assertCount(1, $fields);
        self::assertSame('translations', $fields[0]['name']);
        self::assertSame(CollectionType::class, $fields[0]['type']);
        self::assertSame(PageBlockLocalePanelType::class, $fields[0]['options']['entry_type']);
        self::assertFalse($fields[0]['options']['mapped']);
        self::assertCount(2, $listeners);
        self::assertArrayHasKey(FormEvents::POST_SET_DATA, $listeners);
        self::assertArrayHasKey(FormEvents::SUBMIT, $listeners);

        $translationsField = $this->createMock(FormInterface::class);
        $translationsField->expects(self::once())
            ->method('setData')
            ->with(self::callback(static function (array $panels): bool {
                if (2 !== count($panels)) {
                    return false;
                }

                return $panels[0] instanceof PageBlockLocalePanelData
                    && 'es' === $panels[0]->locale
                    && 'Tarjetas' === $panels[0]->title
                    && "Uno | Linea uno Linea dos" === $panels[0]->items
                    && $panels[1] instanceof PageBlockLocalePanelData
                    && 'en' === $panels[1]->locale
                    && 'Cards' === $panels[1]->title
                    && "One | Line one Line two" === $panels[1]->items;
            }));

        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('get')->with('translations')->willReturn($translationsField);

        $event = $this->createMock(FormEvent::class);
        $event->expects(self::once())->method('getForm')->willReturn($form);

        $listeners[FormEvents::POST_SET_DATA]($event);
    }

    public function testCardsInlineModalSubmitUpdatesExistingItemsAndPrunesTrailingEmptyOnes(): void
    {
        $fields = [];
        $listeners = [];
        $builder = $this->createBuilder($fields, $listeners);
        $block = $this->createCardsBlockWithTwoItems();

        $this->createCardsType()->buildForm($builder, ['block' => $block]);

        $translationsField = $this->createConfiguredMock(FormInterface::class, [
            'getData' => [
                'ignore-me',
                new PageBlockLocalePanelData(locale: 'fr', title: 'Ignored', items: 'Ghost | Body'),
                new PageBlockLocalePanelData(locale: '', title: 'Ignored too', items: 'Skip | Me'),
                new PageBlockLocalePanelData(locale: 'es', title: 'Tarjetas nuevas', items: "Primera | Cuerpo uno\n\n"),
                new PageBlockLocalePanelData(locale: 'en', title: 'New cards', items: 'First | Body one'),
            ],
        ]);
        $form = $this->createConfiguredMock(FormInterface::class, [
            'get' => $translationsField,
        ]);
        $event = $this->createConfiguredMock(FormEvent::class, [
            'getForm' => $form,
        ]);

        $listeners[FormEvents::SUBMIT]($event);

        self::assertCount(1, $block->getItems());
        self::assertSame('Tarjetas nuevas', $block->getTranslationOrFallback('es')->getTitle());
        self::assertSame('New cards', $block->getTranslationOrFallback('en')->getTitle());
        self::assertSame('Primera', $block->getItems()->first()->getTranslationOrFallback('es')->getTitle());
        self::assertSame('Cuerpo uno', $block->getItems()->first()->getTranslationOrFallback('es')->getBody());
        self::assertSame('First', $block->getItems()->first()->getTranslationOrFallback('en')->getTitle());
        self::assertSame('Body one', $block->getItems()->first()->getTranslationOrFallback('en')->getBody());
    }

    public function testCardsInlineModalSubmitCreatesMissingItems(): void
    {
        $fields = [];
        $listeners = [];
        $builder = $this->createBuilder($fields, $listeners);
        $block = new PageCardsBlock();
        $block->ensureTranslations();

        $this->createCardsType()->buildForm($builder, ['block' => $block]);

        $translationsField = $this->createConfiguredMock(FormInterface::class, [
            'getData' => [
                new PageBlockLocalePanelData(locale: 'es', title: 'Tarjetas', items: "Uno | Body uno\nDos | Body dos"),
                new PageBlockLocalePanelData(locale: 'en', title: 'Cards', items: "One | Body one\nTwo | Body two"),
            ],
        ]);
        $form = $this->createConfiguredMock(FormInterface::class, [
            'get' => $translationsField,
        ]);
        $event = $this->createConfiguredMock(FormEvent::class, [
            'getForm' => $form,
        ]);

        $listeners[FormEvents::SUBMIT]($event);

        self::assertCount(2, $block->getItems());
        self::assertSame(0, $block->getItems()->get(0)->getPosition());
        self::assertSame(1, $block->getItems()->get(1)->getPosition());
        self::assertSame('Dos', $block->getItems()->get(1)->getTranslationOrFallback('es')->getTitle());
        self::assertSame('Body two', $block->getItems()->get(1)->getTranslationOrFallback('en')->getBody());
    }

    public function testCardsInlineModalSkipsBlankParsedLinesAndKeepsTrailingNonEmptyItems(): void
    {
        $fields = [];
        $listeners = [];
        $builder = $this->createBuilder($fields, $listeners);
        $block = $this->createCardsBlockWithTwoItems();

        $this->createCardsType()->buildForm($builder, ['block' => $block]);

        $translationsField = $this->createConfiguredMock(FormInterface::class, [
            'getData' => [
                new PageBlockLocalePanelData(locale: 'es', title: 'Tarjetas', items: 'Primera | Cuerpo uno'),
                new PageBlockLocalePanelData(locale: 'fr', title: 'Ignored', items: 'Ghost | Body'),
            ],
        ]);
        $form = $this->createConfiguredMock(FormInterface::class, [
            'get' => $translationsField,
        ]);
        $event = $this->createConfiguredMock(FormEvent::class, [
            'getForm' => $form,
        ]);

        $listeners[FormEvents::SUBMIT]($event);

        self::assertCount(2, $block->getItems());
    }

    public function testCardsInlineModalIgnoresBlankLinesWhileParsing(): void
    {
        $fields = [];
        $listeners = [];
        $builder = $this->createBuilder($fields, $listeners);
        $block = new PageCardsBlock();
        $block->ensureTranslations();

        $this->createCardsType()->buildForm($builder, ['block' => $block]);

        $translationsField = $this->createConfiguredMock(FormInterface::class, [
            'getData' => [
                new PageBlockLocalePanelData(locale: 'es', title: 'Tarjetas', items: "Uno | Body uno\n \nDos | Body dos"),
                new PageBlockLocalePanelData(locale: 'en', title: 'Cards', items: "One | Body one\nTwo | Body two"),
            ],
        ]);
        $form = $this->createConfiguredMock(FormInterface::class, [
            'get' => $translationsField,
        ]);
        $event = $this->createConfiguredMock(FormEvent::class, [
            'getForm' => $form,
        ]);

        $listeners[FormEvents::SUBMIT]($event);

        self::assertCount(2, $block->getItems());
    }

    public function testListInlineModalBuildsCollectionAndHydratesLocalePanels(): void
    {
        $fields = [];
        $listeners = [];
        $builder = $this->createBuilder($fields, $listeners);
        $block = $this->createListBlockWithOneItem();

        $this->createListType()->buildForm($builder, ['block' => $block]);

        self::assertCount(1, $fields);
        self::assertSame('translations', $fields[0]['name']);
        self::assertSame(CollectionType::class, $fields[0]['type']);
        self::assertSame(PageBlockLocalePanelType::class, $fields[0]['options']['entry_type']);
        self::assertCount(2, $listeners);

        $translationsField = $this->createMock(FormInterface::class);
        $translationsField->expects(self::once())
            ->method('setData')
            ->with(self::callback(static function (array $panels): bool {
                if (2 !== count($panels)) {
                    return false;
                }

                return $panels[0] instanceof PageBlockLocalePanelData
                    && 'Puntos' === $panels[0]->title
                    && 'Primer punto' === $panels[0]->items
                    && $panels[1] instanceof PageBlockLocalePanelData
                    && 'Points' === $panels[1]->title
                    && 'First point' === $panels[1]->items;
            }));

        $form = $this->createConfiguredMock(FormInterface::class, [
            'get' => $translationsField,
        ]);
        $event = $this->createConfiguredMock(FormEvent::class, [
            'getForm' => $form,
        ]);

        $listeners[FormEvents::POST_SET_DATA]($event);
    }

    public function testListInlineModalSubmitUpdatesExistingItemsAndPrunesTrailingEmptyOnes(): void
    {
        $fields = [];
        $listeners = [];
        $builder = $this->createBuilder($fields, $listeners);
        $block = $this->createListBlockWithTwoItems();

        $this->createListType()->buildForm($builder, ['block' => $block]);

        $translationsField = $this->createConfiguredMock(FormInterface::class, [
            'getData' => [
                'ignore-me',
                new PageBlockLocalePanelData(locale: 'fr', title: 'Ignored', items: 'Ghost'),
                new PageBlockLocalePanelData(locale: '', title: 'Ignored too', items: 'Skip'),
                new PageBlockLocalePanelData(locale: 'es', title: 'Lista nueva', items: "Paso uno\n\n"),
                new PageBlockLocalePanelData(locale: 'en', title: 'New list', items: 'Step one'),
            ],
        ]);
        $form = $this->createConfiguredMock(FormInterface::class, [
            'get' => $translationsField,
        ]);
        $event = $this->createConfiguredMock(FormEvent::class, [
            'getForm' => $form,
        ]);

        $listeners[FormEvents::SUBMIT]($event);

        self::assertCount(1, $block->getItems());
        self::assertSame('Lista nueva', $block->getTranslationOrFallback('es')->getTitle());
        self::assertSame('New list', $block->getTranslationOrFallback('en')->getTitle());
        self::assertSame('Paso uno', $block->getItems()->first()->getTranslationOrFallback('es')->getText());
        self::assertSame('Step one', $block->getItems()->first()->getTranslationOrFallback('en')->getText());
    }

    public function testListInlineModalKeepsTrailingItemsWhenAnotherLocaleStillHasContent(): void
    {
        $fields = [];
        $listeners = [];
        $builder = $this->createBuilder($fields, $listeners);
        $block = $this->createListBlockWithTwoItems();

        $this->createListType()->buildForm($builder, ['block' => $block]);

        $translationsField = $this->createConfiguredMock(FormInterface::class, [
            'getData' => [
                new PageBlockLocalePanelData(locale: 'es', title: 'Lista', items: '   '),
                new PageBlockLocalePanelData(locale: 'fr', title: 'Ignored', items: 'Ghost'),
            ],
        ]);
        $form = $this->createConfiguredMock(FormInterface::class, [
            'get' => $translationsField,
        ]);
        $event = $this->createConfiguredMock(FormEvent::class, [
            'getForm' => $form,
        ]);

        $listeners[FormEvents::SUBMIT]($event);

        self::assertCount(2, $block->getItems());
    }

    public function testListInlineModalSubmitCreatesMissingItems(): void
    {
        $fields = [];
        $listeners = [];
        $builder = $this->createBuilder($fields, $listeners);
        $block = new PageListBlock();
        $block->ensureTranslations();

        $this->createListType()->buildForm($builder, ['block' => $block]);

        $translationsField = $this->createConfiguredMock(FormInterface::class, [
            'getData' => [
                new PageBlockLocalePanelData(locale: 'es', title: 'Lista', items: "Uno\nDos"),
                new PageBlockLocalePanelData(locale: 'en', title: 'List', items: "One\nTwo"),
            ],
        ]);
        $form = $this->createConfiguredMock(FormInterface::class, [
            'get' => $translationsField,
        ]);
        $event = $this->createConfiguredMock(FormEvent::class, [
            'getForm' => $form,
        ]);

        $listeners[FormEvents::SUBMIT]($event);

        self::assertCount(2, $block->getItems());
        self::assertSame(0, $block->getItems()->get(0)->getPosition());
        self::assertSame(1, $block->getItems()->get(1)->getPosition());
        self::assertSame('Dos', $block->getItems()->get(1)->getTranslationOrFallback('es')->getText());
        self::assertSame('Two', $block->getItems()->get(1)->getTranslationOrFallback('en')->getText());
    }

    /**
     * @param list<array{name: string, type: string, options: array<string, mixed>}>                  $fields
     * @param array<string, callable(FormEvent): void> $listeners
     */
    private function createBuilder(array &$fields, array &$listeners): FormBuilderInterface
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
        $builder->method('addEventListener')
            ->willReturnCallback(function (string $eventName, callable $listener) use (&$listeners, $builder): FormBuilderInterface {
                $listeners[$eventName] = $listener;

                return $builder;
            });

        return $builder;
    }

    private function createCardsType(): PageCardsBlockInlineModalType
    {
        return new PageCardsBlockInlineModalType(
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

    private function createListType(): PageListBlockInlineModalType
    {
        return new PageListBlockInlineModalType(
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

    private function createCardsBlockWithOneItem(): PageCardsBlock
    {
        $block = new PageCardsBlock();
        $block->ensureTranslations();
        $block->getTranslationOrFallback('es')->setTitle('Tarjetas');
        $block->getTranslationOrFallback('en')->setTitle('Cards');

        $item = new PageCardItem();
        $item->ensureTranslations();
        $item->getTranslationOrFallback('es')->setTitle('Uno')->setBody("Linea uno\nLinea dos");
        $item->getTranslationOrFallback('en')->setTitle('One')->setBody("Line one\nLine two");
        $block->addItem($item);

        return $block;
    }

    private function createCardsBlockWithTwoItems(): PageCardsBlock
    {
        $block = $this->createCardsBlockWithOneItem();

        $second = new PageCardItem();
        $second->ensureTranslations();
        $second->getTranslationOrFallback('es')->setTitle('Eliminar')->setBody('Quitar');
        $second->getTranslationOrFallback('en')->setTitle('Delete')->setBody('Remove');
        $block->addItem($second);

        return $block;
    }

    private function createListBlockWithOneItem(): PageListBlock
    {
        $block = new PageListBlock();
        $block->ensureTranslations();
        $block->getTranslationOrFallback('es')->setTitle('Puntos');
        $block->getTranslationOrFallback('en')->setTitle('Points');

        $item = new PageListItem();
        $item->ensureTranslations();
        $item->getTranslationOrFallback('es')->setText('Primer punto');
        $item->getTranslationOrFallback('en')->setText('First point');
        $block->addItem($item);

        return $block;
    }

    private function createListBlockWithTwoItems(): PageListBlock
    {
        $block = $this->createListBlockWithOneItem();

        $second = new PageListItem();
        $second->ensureTranslations();
        $second->getTranslationOrFallback('es')->setText('Eliminar');
        $second->getTranslationOrFallback('en')->setText('Delete');
        $block->addItem($second);

        return $block;
    }
}
