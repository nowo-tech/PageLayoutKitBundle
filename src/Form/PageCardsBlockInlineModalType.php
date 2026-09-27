<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Form;

use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PageLayoutKitBundle\Entity\PageCardItem;
use Nowo\PageLayoutKitBundle\Entity\PageCardsBlock;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Override;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function count;

/** @extends AbstractPageLayoutFormType<null> */
final class PageCardsBlockInlineModalType extends AbstractPageLayoutFormType
{
    public function __construct(
        FormOptionsMerger $formOptionsMerger,
        FormTypeMap $formTypeMap,
        private readonly ?PageLocales $pageLocales = null,
    ) {
        parent::__construct($formOptionsMerger, $formTypeMap);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var PageCardsBlock $block */
        $block       = $options['block'];
        $pageLocales = $this->pageLocales;
        $locales     = $pageLocales?->getAll() ?? PageLocales::all();

        $this->withBuilder($builder, function (): void {
            $this->addWithDefaults($this->boundBuilder(), 'translations', CollectionType::class, [
                'entry_type'   => PageBlockLocalePanelType::class,
                'allow_add'    => false,
                'allow_delete' => false,
                'label'        => false,
                'mapped'       => false,
            ]);
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $formEvent) use ($block, $pageLocales, $locales): void {
            $panels = [];

            foreach ($locales as $locale) {
                $lines = [];

                foreach ($block->getItems() as $item) {
                    $itemTranslation = $item->getTranslationOrFallback($locale, $pageLocales);
                    $lines[]         = $itemTranslation->getTitle() . ' | ' . str_replace("\n", ' ', $itemTranslation->getBody());
                }

                $panels[] = new PageBlockLocalePanelData(
                    locale: $locale,
                    title: $block->getTranslationOrFallback($locale, $pageLocales)->getTitle(),
                    items: implode("\n", $lines),
                );
            }

            $formEvent->getForm()->get('translations')->setData($panels);
        });

        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $formEvent) use ($block, $pageLocales, $locales): void {
            /** @var list<mixed> $panels */
            $panels        = $formEvent->getForm()->get('translations')->getData() ?? [];
            $existingItems = $block->getItems()->toArray();
            $maxParsed     = 0;

            foreach ($panels as $panel) {
                if (!$panel instanceof PageBlockLocalePanelData) {
                    continue;
                }

                $locale = $panel->locale;
                if ($locale === '') {
                    continue;
                }

                $blockTranslation = $block->getTranslation($locale) ?? $block->ensureTranslations($pageLocales)->getTranslation($locale);
                if ($blockTranslation === null) {
                    continue;
                }

                // @igor-ignore - Not shared worker service state.
                $blockTranslation->setTitle($panel->title);

                $rawItems    = trim($panel->items);
                $parsedItems = [];

                if ($rawItems !== '') {
                    foreach (explode("\n", $rawItems) as $line) {
                        $line = trim($line);

                        if ($line === '') {
                            continue;
                        }

                        [$title, $body] = array_pad(explode('|', $line, 2), 2, '');
                        $parsedItems[]  = [
                            'title' => trim($title),
                            'body'  => trim($body),
                        ];
                    }
                }

                $maxParsed = max($maxParsed, count($parsedItems));

                foreach ($parsedItems as $index => $itemData) {
                    $item = $existingItems[$index] ?? null;

                    if (!$item instanceof PageCardItem) {
                        $item = new PageCardItem();
                        $item->ensureTranslations($pageLocales);
                        $item->setPosition($index);
                        $block->addItem($item);
                        $existingItems[$index] = $item;
                    }

                    $itemTranslation = $item->getTranslationOrFallback($locale, $pageLocales);
                    // @igor-ignore - Not shared worker service state.
                    $itemTranslation->setLocale($locale);
                    // @igor-ignore - Not shared worker service state.
                    $itemTranslation->setTitle($itemData['title']);
                    // @igor-ignore - Not shared worker service state.
                    $itemTranslation->setBody($itemData['body']);
                }

                $counter = count($existingItems);

                for ($index = count($parsedItems); $index < $counter; ++$index) {
                    $existingItems[$index]->getTranslationOrFallback($locale, $pageLocales)->setTitle('');
                    $existingItems[$index]->getTranslationOrFallback($locale, $pageLocales)->setBody('');
                }
            }

            // Drop trailing empty items across locales when all locales cleared them.
            $existingItems = $block->getItems()->toArray();
            $counter       = count($existingItems);

            for ($index = $counter - 1; $index >= $maxParsed; --$index) {
                $item     = $existingItems[$index];
                $allEmpty = true;

                foreach ($locales as $locale) {
                    $tr = $item->getTranslationOrFallback($locale, $pageLocales);

                    if (trim($tr->getTitle()) !== '' || trim($tr->getBody()) !== '') {
                        $allEmpty = false;
                        break;
                    }
                }

                if ($allEmpty) {
                    $block->removeItem($item);
                }
            }
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => null,
        ]);
        $resolver->setRequired(['block']);
        $resolver->setAllowedTypes('block', PageCardsBlock::class);
    }
}
