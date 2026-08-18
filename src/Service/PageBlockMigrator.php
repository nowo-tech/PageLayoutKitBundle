<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Service;

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
use Nowo\PageLayoutKitBundle\Legacy\LegacyPageContentProviderInterface;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Migrates legacy page JSON into typed page blocks and layout entries.
 */
final readonly class PageBlockMigrator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PageLayoutEntryRepository $pageLayoutEntryRepository,
        private ?LegacyPageContentProviderInterface $legacyContentProvider = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->pageLayoutEntryRepository->findEnabledByPageKey('home');
    }

    public function migrate(bool $force = false): bool
    {
        if (!$this->legacyContentProvider instanceof LegacyPageContentProviderInterface) {
            return false;
        }

        if (!$force && !$this->isEmpty()) {
            return false;
        }

        if ($force) {
            $this->clearExisting();
        }

        $this->migrateHome($this->contentForPage('home'));
        $this->migrateContact($this->contentForPage('contact'));
        $this->entityManager->flush();

        return true;
    }

    /** @param array<string, array<string, mixed>> $locales */
    private function migrateHome(array $locales): void
    {
        $pageHeroBlock = new PageHeroBlock();
        $this->fillHeroTranslations($pageHeroBlock, $locales, static fn (array $data): array => [
            'pageTitle' => (string) ($data['page_title'] ?? ''),
            'pageDescription' => (string) ($data['page_description'] ?? ''),
            'eyebrow' => (string) ($data['hero_eyebrow'] ?? ''),
            'title' => (string) ($data['hero_title'] ?? ''),
            'subtitle' => (string) ($data['hero_subtitle'] ?? ''),
            'ctaPrimary' => (string) ($data['hero_cta_primary'] ?? ''),
            'ctaSecondary' => (string) ($data['hero_cta_secondary'] ?? ''),
        ]);
        $this->entityManager->persist($pageHeroBlock);
        $this->addLayout('home', PageBlockType::Hero, $pageHeroBlock, 0);

        $pageTextBlock = $this->createTextBlock('problem', $locales, static fn (array $data): array => [
            'title' => (string) ($data['problem_title'] ?? ''),
            'body' => (string) ($data['problem_text'] ?? ''),
        ]);
        $this->addLayout('home', PageBlockType::Text, $pageTextBlock, 1);

        $pageCardsBlock = $this->createCardsBlock('value', $locales, static fn (array $data): array => [
            'title' => (string) ($data['value_title'] ?? ''),
            'items' => self::cardItemsFromPrefix($data, 'value', 4),
        ]);
        $this->addLayout('home', PageBlockType::Cards, $pageCardsBlock, 2);

        $pain = $this->createCardsBlock('pain', $locales, static fn (array $data): array => [
            'title' => (string) ($data['pain_title'] ?? ''),
            'items' => self::cardItemsFromPrefix($data, 'pain', 3),
        ]);
        $this->addLayout('home', PageBlockType::Cards, $pain, 3);

        $pageListBlock = $this->createListBlock('detect', $locales, static fn (array $data): array => [
            'title' => (string) ($data['detect_title'] ?? ''),
            'items' => array_values(array_map(
                static fn (string $text): string => $text,
                $data['detect_items'] ?? [],
            )),
        ]);
        $this->addLayout('home', PageBlockType::List, $pageListBlock, 4);

        $services = $this->createTextBlock('services', $locales, static fn (array $data): array => [
            'title' => (string) ($data['services_title'] ?? ''),
            'body' => (string) ($data['services_text'] ?? ''),
        ]);
        $this->addLayout('home', PageBlockType::Text, $services, 5);

        $profile = $this->createTextBlock('profile', $locales, static fn (array $data): array => [
            'title' => (string) ($data['profile_title'] ?? ''),
            'body' => (string) ($data['profile_text'] ?? ''),
        ]);
        $this->addLayout('home', PageBlockType::Text, $profile, 6);

        $process = $this->createListBlock('process', $locales, static fn (array $data): array => [
            'title' => (string) ($data['process_title'] ?? ''),
            'items' => array_values(array_map(
                static fn (string $text): string => $text,
                $data['process_items'] ?? [],
            )),
        ]);
        $this->addLayout('home', PageBlockType::List, $process, 7);

        $pageCtaBlock = $this->createCtaBlock(null, $locales, static fn (array $data): array => [
            'title' => (string) ($data['cta_title'] ?? ''),
            'body' => (string) ($data['cta_text'] ?? ''),
        ]);
        $this->addLayout('home', PageBlockType::Cta, $pageCtaBlock, 8);
    }

    /** @param array<string, array<string, mixed>> $locales */
    private function migrateContact(array $locales): void
    {
        $pageTextBlock = $this->createTextBlock('contact_header', $locales, static fn (array $data): array => [
            'pageTitle' => (string) ($data['page_title'] ?? ''),
            'pageDescription' => (string) ($data['page_description'] ?? ''),
            'title' => (string) ($data['h1'] ?? ''),
            'body' => (string) ($data['intro'] ?? ''),
        ]);
        $this->addLayout('contact', PageBlockType::Text, $pageTextBlock, 0);

        $expect = $this->createTextBlock('expect', $locales, static fn (array $data): array => [
            'title' => (string) ($data['expect_title'] ?? ''),
            'body' => (string) ($data['expect_text'] ?? ''),
        ]);
        $this->addLayout('contact', PageBlockType::Text, $expect, 1);

        $pageListBlock = $this->createListBlock('expect_items', $locales, static fn (array $data): array => [
            'title' => '',
            'items' => array_values(array_map(
                static fn (string $text): string => $text,
                $data['expect_items'] ?? [],
            )),
        ]);
        $this->addLayout('contact', PageBlockType::List, $pageListBlock, 2);

        $pageCompareBlock = new PageCompareBlock();
        $this->fillCompareTranslations($pageCompareBlock, $locales, static fn (array $data): array => [
            'beforeLabel' => (string) ($data['before_label'] ?? ''),
            'beforeText' => (string) ($data['before_text'] ?? ''),
            'afterLabel' => (string) ($data['after_label'] ?? ''),
            'afterText' => (string) ($data['after_text'] ?? ''),
        ]);
        $this->entityManager->persist($pageCompareBlock);
        $this->addLayout('contact', PageBlockType::Compare, $pageCompareBlock, 3);

        $pageCtaBlock = $this->createCtaBlock('contact_form', $locales, static fn (array $data): array => [
            'title' => (string) ($data['form_submit'] ?? ''),
            'body' => (string) ($data['form_note'] ?? ''),
        ]);
        $this->addLayout('contact', PageBlockType::Cta, $pageCtaBlock, 4);
    }

    /** @return array<string, array<string, mixed>> */
    private function contentForPage(string $pageKey): array
    {
        $provider = $this->legacyContentProvider;
        if (!$provider instanceof LegacyPageContentProviderInterface) {
            return [];
        }

        $content = [];
        $defaultLocale = PageLocales::default();
        $defaultData = $provider->contentForPage($pageKey, $defaultLocale);

        foreach (PageLocales::all() as $locale) {
            $stored = $provider->contentForPage($pageKey, $locale);
            $content[$locale] = $stored !== [] ? $stored : $defaultData;
        }

        return $content;
    }

    /** @param array<string, array<string, mixed>> $locales */
    private function createTextBlock(string $sectionKey, array $locales, callable $mapper): PageTextBlock
    {
        $pageTextBlock = (new PageTextBlock())->setSectionKey($sectionKey);

        foreach (PageLocales::all() as $locale) {
            $mapped = $mapper($locales[$locale] ?? []);
            $translation = new PageTextBlockTranslation()
                ->setLocale($locale)
                ->setPageTitle($mapped['pageTitle'] ?? null)
                ->setPageDescription($mapped['pageDescription'] ?? null)
                ->setTitle((string) ($mapped['title'] ?? ''))
                ->setBody((string) ($mapped['body'] ?? ''));
            $pageTextBlock->addTranslation($translation);
        }

        $this->entityManager->persist($pageTextBlock);

        return $pageTextBlock;
    }

    /**
     * @param array<string, array<string, mixed>> $locales
     * @param callable(array<string, mixed>): array{title: string, items: list<array{title: string, body: string}>} $mapper
     */
    private function createCardsBlock(string $sectionKey, array $locales, callable $mapper): PageCardsBlock
    {
        $pageCardsBlock = (new PageCardsBlock())->setSectionKey($sectionKey);
        $reference = $mapper($locales[PageLocales::default()] ?? []);

        foreach (PageLocales::all() as $locale) {
            $mapped = $mapper($locales[$locale] ?? []);
            $pageCardsBlock->addTranslation(
                new PageCardsBlockTranslation()
                    ->setLocale($locale)
                    ->setTitle($mapped['title']),
            );
        }

        foreach ($reference['items'] as $position => $itemData) {
            $item = (new PageCardItem())->setPosition($position);

            foreach (PageLocales::all() as $locale) {
                $mapped = $mapper($locales[$locale] ?? []);
                $localeItem = $mapped['items'][$position] ?? ['title' => '', 'body' => ''];
                $item->addTranslation(
                    new PageCardItemTranslation()
                        ->setLocale($locale)
                        ->setTitle($localeItem['title'])
                        ->setBody($localeItem['body']),
                );
            }

            $pageCardsBlock->addItem($item);
        }

        $this->entityManager->persist($pageCardsBlock);

        return $pageCardsBlock;
    }

    /**
     * @param array<string, array<string, mixed>> $locales
     * @param callable(array<string, mixed>): array{title: string, items: list<string>} $mapper
     */
    private function createListBlock(string $sectionKey, array $locales, callable $mapper): PageListBlock
    {
        $pageListBlock = (new PageListBlock())->setSectionKey($sectionKey);
        $reference = $mapper($locales[PageLocales::default()] ?? []);

        foreach (PageLocales::all() as $locale) {
            $mapped = $mapper($locales[$locale] ?? []);
            $pageListBlock->addTranslation(
                new PageListBlockTranslation()
                    ->setLocale($locale)
                    ->setTitle($mapped['title']),
            );
        }

        foreach ($reference['items'] as $position => $text) {
            $item = (new PageListItem())->setPosition($position);

            foreach (PageLocales::all() as $locale) {
                $mapped = $mapper($locales[$locale] ?? []);
                $item->addTranslation(
                    new PageListItemTranslation()
                        ->setLocale($locale)
                        ->setText($mapped['items'][$position] ?? ''),
                );
            }

            $pageListBlock->addItem($item);
        }

        $this->entityManager->persist($pageListBlock);

        return $pageListBlock;
    }

    /** @param array<string, array<string, mixed>> $locales */
    private function createCtaBlock(?string $sectionKey, array $locales, callable $mapper): PageCtaBlock
    {
        $pageCtaBlock = (new PageCtaBlock())->setSectionKey($sectionKey);

        foreach (PageLocales::all() as $locale) {
            $mapped = $mapper($locales[$locale] ?? []);
            $pageCtaBlock->addTranslation(
                new PageCtaBlockTranslation()
                    ->setLocale($locale)
                    ->setTitle((string) ($mapped['title'] ?? ''))
                    ->setBody((string) ($mapped['body'] ?? '')),
            );
        }

        $this->entityManager->persist($pageCtaBlock);

        return $pageCtaBlock;
    }

    /**
     * @param array<string, array<string, mixed>> $locales
     * @param callable(array<string, mixed>): array<string, string> $mapper
     */
    private function fillHeroTranslations(PageHeroBlock $pageHeroBlock, array $locales, callable $mapper): void
    {
        foreach (PageLocales::all() as $locale) {
            $mapped = $mapper($locales[$locale] ?? []);
            $pageHeroBlock->addTranslation(
                new PageHeroBlockTranslation()
                    ->setLocale($locale)
                    ->setPageTitle($mapped['pageTitle'])
                    ->setPageDescription($mapped['pageDescription'])
                    ->setEyebrow($mapped['eyebrow'])
                    ->setTitle($mapped['title'])
                    ->setSubtitle($mapped['subtitle'])
                    ->setCtaPrimary($mapped['ctaPrimary'])
                    ->setCtaSecondary($mapped['ctaSecondary']),
            );
        }
    }

    /**
     * @param array<string, array<string, mixed>> $locales
     * @param callable(array<string, mixed>): array<string, string> $mapper
     */
    private function fillCompareTranslations(PageCompareBlock $pageCompareBlock, array $locales, callable $mapper): void
    {
        foreach (PageLocales::all() as $locale) {
            $mapped = $mapper($locales[$locale] ?? []);
            $pageCompareBlock->addTranslation(
                new PageCompareBlockTranslation()
                    ->setLocale($locale)
                    ->setBeforeLabel($mapped['beforeLabel'])
                    ->setBeforeText($mapped['beforeText'])
                    ->setAfterLabel($mapped['afterLabel'])
                    ->setAfterText($mapped['afterText']),
            );
        }
    }

    /** @param array<string, mixed> $data
     * @return list<array{title: string, body: string}>
     */
    private static function cardItemsFromPrefix(array $data, string $prefix, int $count): array
    {
        $items = [];

        for ($i = 1; $i <= $count; ++$i) {
            $items[] = [
                'title' => (string) ($data[sprintf('%s_%d_title', $prefix, $i)] ?? ''),
                'body' => (string) ($data[sprintf('%s_%d_text', $prefix, $i)] ?? ''),
            ];
        }

        return $items;
    }

    private function addLayout(string $pageKey, PageBlockType $pageBlockType, object $block, int $position): void
    {
        $id = method_exists($block, 'getId') ? $block->getId() : null;

        if (null === $id) {
            $this->entityManager->flush();
            $id = $block->getId();
        }

        $pageLayoutEntry = new PageLayoutEntry()
            ->setPageKey($pageKey)
            ->setBlockType($pageBlockType)
            ->setBlockId((int) $id)
            ->setPosition($position);
        $this->entityManager->persist($pageLayoutEntry);
    }

    private function clearExisting(): void
    {
        $this->entityManager->createQuery('DELETE FROM Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry e')->execute();

        foreach ([
            PageHeroBlock::class,
            PageTextBlock::class,
            PageCardsBlock::class,
            PageListBlock::class,
            PageCtaBlock::class,
            PageCompareBlock::class,
        ] as $class) {
            $this->entityManager->createQuery('DELETE FROM ' . $class . ' b')->execute();
        }
    }
}
