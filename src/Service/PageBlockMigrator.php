<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
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
use Nowo\PageLayoutKitBundle\Event\PageBlocksMigratedEvent;
use Nowo\PageLayoutKitBundle\Exception\PageMigrationInProgressException;
use Nowo\PageLayoutKitBundle\Legacy\LegacyPageContentProviderInterface;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Persistence\PageLayoutTransaction;
use Nowo\PageLayoutKitBundle\Persistence\PageMigrationLock;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use Nowo\TransactionContract\TransientStorageFailure;

use function sprintf;

/**
 * Migrates legacy page JSON into typed page blocks and layout entries.
 *
 * 2.3 (REQ-RUNTIME-003): each page is one write unit ({@see PageLayoutTransaction::run()}): its old layout and blocks
 * (with `force`), its new blocks and its layout entries commit together or not at all, so a failure never leaves a
 * half-built page. The page's entries are locked `FOR UPDATE` first, so an admin reorder of the same page waits for
 * the unit (or the unit for the reorder). A {@see PageMigrationLock} keeps a second migration from running at the same
 * time. The legacy content is read before any lock is taken (no host I/O under a lock).
 */
final readonly class PageBlockMigrator
{
    /** The pages the migration builds, in this order (one write unit each). */
    public const array PAGES = ['home', 'contact'];

    /** Block entity of each block type. */
    private const array BLOCK_CLASSES = [
        PageBlockType::Hero->value    => PageHeroBlock::class,
        PageBlockType::Text->value    => PageTextBlock::class,
        PageBlockType::Cards->value   => PageCardsBlock::class,
        PageBlockType::List->value    => PageListBlock::class,
        PageBlockType::Cta->value     => PageCtaBlock::class,
        PageBlockType::Compare->value => PageCompareBlock::class,
    ];

    private PageLayoutTransaction $transaction;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PageLayoutEntryRepository $pageLayoutEntryRepository,
        private PageLocales $pageLocales,
        private ?LegacyPageContentProviderInterface $legacyContentProvider = null,
        ?PageLayoutTransaction $transaction = null,
        private PageMigrationLock $lock = new PageMigrationLock(),
    ) {
        $this->transaction = $transaction ?? new PageLayoutTransaction($entityManager);
    }

    public function isEmpty(): bool
    {
        return $this->pageLayoutEntryRepository->findEnabledByPageKey('home') === [];
    }

    /**
     * Builds the pages of {@see self::PAGES}, one write unit each. Without `force` a page that already has a layout is
     * left as it is (so a run stopped between two pages is resumed by running it again); with `force` its layout and
     * the blocks it references are replaced (pages outside {@see self::PAGES} are kept). When a unit fails, the pages
     * committed before it stay migrated (their event is still dispatched) and the failure is rethrown; the failed page
     * is unchanged.
     *
     * @throws PageMigrationInProgressException When another migration is running (nothing was changed)
     * @throws TransientStorageFailure When a page unit met a lock conflict (that page is unchanged)
     */
    public function migrate(bool $force = false): bool
    {
        if (!$this->legacyContentProvider instanceof LegacyPageContentProviderInterface) {
            return false;
        }

        if (!$force && $this->allPagesHaveALayout()) {
            return false;
        }

        // Rule 9: the host's content provider (files, HTTP, another database) is read before any lock is taken.
        $content = [];
        foreach (self::PAGES as $pageKey) {
            $content[$pageKey] = $this->contentForPage($pageKey);
        }

        $connection = $this->entityManager->getConnection();
        $table      = $this->entityManager->getClassMetadata(PageLayoutEntry::class)->getTableName();
        if (!$this->lock->acquire($connection, $table)) {
            throw new PageMigrationInProgressException();
        }

        $migrated = [];
        try {
            foreach ($content as $pageKey => $locales) {
                if ($this->transaction->run(fn (): bool => $this->migratePage($pageKey, $locales, $force))) {
                    $migrated[] = $pageKey;
                }
            }
        } finally {
            $this->lock->release($connection, $table);
            if ($migrated !== []) {
                // ID-free event after the commit of the pages that were built (2.2, rules 4/7).
                $this->transaction->record(new PageBlocksMigratedEvent($migrated, $force));
            }
        }

        return $migrated !== [];
    }

    private function allPagesHaveALayout(): bool
    {
        foreach (self::PAGES as $pageKey) {
            if ($this->pageLayoutEntryRepository->findEnabledByPageKey($pageKey) === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * One page: lock its entries, keep it when it has a layout and `force` is off (a concurrent migration built it),
     * else replace it. Runs inside the page's write unit.
     *
     * @param array<string, array<string, mixed>> $locales
     */
    private function migratePage(string $pageKey, array $locales, bool $force): bool
    {
        $existing = $this->pageLayoutEntryRepository->lockByPageKey($pageKey);
        if ($existing !== [] && !$force) {
            return false;
        }

        $this->clearPage($existing);

        if ($pageKey === 'home') {
            $this->migrateHome($locales);
        } else {
            $this->migrateContact($locales);
        }

        return true;
    }

    /** @param array<string, array<string, mixed>> $locales */
    private function migrateHome(array $locales): void
    {
        $pageHeroBlock = new PageHeroBlock();
        $this->fillHeroTranslations($pageHeroBlock, $locales, static fn (array $data): array => [
            'pageTitle'       => (string) ($data['page_title'] ?? ''),
            'pageDescription' => (string) ($data['page_description'] ?? ''),
            'eyebrow'         => (string) ($data['hero_eyebrow'] ?? ''),
            'title'           => (string) ($data['hero_title'] ?? ''),
            'subtitle'        => (string) ($data['hero_subtitle'] ?? ''),
            'ctaPrimary'      => (string) ($data['hero_cta_primary'] ?? ''),
            'ctaSecondary'    => (string) ($data['hero_cta_secondary'] ?? ''),
        ]);
        $this->entityManager->persist($pageHeroBlock);
        $this->addLayout('home', PageBlockType::Hero, $pageHeroBlock, 0);

        $pageTextBlock = $this->createTextBlock('problem', $locales, static fn (array $data): array => [
            'title' => (string) ($data['problem_title'] ?? ''),
            'body'  => (string) ($data['problem_text'] ?? ''),
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
            'body'  => (string) ($data['services_text'] ?? ''),
        ]);
        $this->addLayout('home', PageBlockType::Text, $services, 5);

        $profile = $this->createTextBlock('profile', $locales, static fn (array $data): array => [
            'title' => (string) ($data['profile_title'] ?? ''),
            'body'  => (string) ($data['profile_text'] ?? ''),
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
            'body'  => (string) ($data['cta_text'] ?? ''),
        ]);
        $this->addLayout('home', PageBlockType::Cta, $pageCtaBlock, 8);
    }

    /** @param array<string, array<string, mixed>> $locales */
    private function migrateContact(array $locales): void
    {
        $pageTextBlock = $this->createTextBlock('contact_header', $locales, static fn (array $data): array => [
            'pageTitle'       => (string) ($data['page_title'] ?? ''),
            'pageDescription' => (string) ($data['page_description'] ?? ''),
            'title'           => (string) ($data['h1'] ?? ''),
            'body'            => (string) ($data['intro'] ?? ''),
        ]);
        $this->addLayout('contact', PageBlockType::Text, $pageTextBlock, 0);

        $expect = $this->createTextBlock('expect', $locales, static fn (array $data): array => [
            'title' => (string) ($data['expect_title'] ?? ''),
            'body'  => (string) ($data['expect_text'] ?? ''),
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
            'beforeText'  => (string) ($data['before_text'] ?? ''),
            'afterLabel'  => (string) ($data['after_label'] ?? ''),
            'afterText'   => (string) ($data['after_text'] ?? ''),
        ]);
        $this->entityManager->persist($pageCompareBlock);
        $this->addLayout('contact', PageBlockType::Compare, $pageCompareBlock, 3);

        $pageCtaBlock = $this->createCtaBlock('contact_form', $locales, static fn (array $data): array => [
            'title' => (string) ($data['form_submit'] ?? ''),
            'body'  => (string) ($data['form_note'] ?? ''),
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

        $content       = [];
        $defaultLocale = $this->defaultLocale();
        $defaultData   = $provider->contentForPage($pageKey, $defaultLocale);

        foreach ($this->locales() as $locale) {
            $stored           = $provider->contentForPage($pageKey, $locale);
            $content[$locale] = $stored !== [] ? $stored : $defaultData;
        }

        return $content;
    }

    /** @param array<string, array<string, mixed>> $locales */
    private function createTextBlock(string $sectionKey, array $locales, callable $mapper): PageTextBlock
    {
        $pageTextBlock = (new PageTextBlock())->setSectionKey($sectionKey);

        foreach ($this->locales() as $locale) {
            $mapped      = $mapper($locales[$locale] ?? []);
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
        $reference      = $mapper($locales[$this->defaultLocale()] ?? []);

        foreach ($this->locales() as $locale) {
            $mapped = $mapper($locales[$locale] ?? []);
            $pageCardsBlock->addTranslation(
                new PageCardsBlockTranslation()
                    ->setLocale($locale)
                    ->setTitle($mapped['title']),
            );
        }

        foreach ($reference['items'] as $position => $itemData) {
            $item = (new PageCardItem())->setPosition($position);

            foreach ($this->locales() as $locale) {
                $mapped     = $mapper($locales[$locale] ?? []);
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
        $reference     = $mapper($locales[$this->defaultLocale()] ?? []);

        foreach ($this->locales() as $locale) {
            $mapped = $mapper($locales[$locale] ?? []);
            $pageListBlock->addTranslation(
                new PageListBlockTranslation()
                    ->setLocale($locale)
                    ->setTitle($mapped['title']),
            );
        }

        foreach ($reference['items'] as $position => $text) {
            $item = (new PageListItem())->setPosition($position);

            foreach ($this->locales() as $locale) {
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

        foreach ($this->locales() as $locale) {
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
        foreach ($this->locales() as $locale) {
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
        foreach ($this->locales() as $locale) {
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

    private function defaultLocale(): string
    {
        return $this->pageLocales->getDefault();
    }

    /** @return list<string> */
    private function locales(): array
    {
        return $this->pageLocales->getAll();
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
                'body'  => (string) ($data[sprintf('%s_%d_text', $prefix, $i)] ?? ''),
            ];
        }

        return $items;
    }

    private function blockId(object $block): mixed
    {
        return method_exists($block, 'getId') ? $block->getId() : null;
    }

    private function addLayout(string $pageKey, PageBlockType $pageBlockType, object $block, int $position): void
    {
        $id = $this->blockId($block);

        if ($id === null) {
            // Inside the page's write unit: the flush only assigns the id, the unit commits (2.3).
            $this->entityManager->flush();
            $id = $this->blockId($block);
        }

        $pageLayoutEntry = new PageLayoutEntry()
            ->setPageKey($pageKey)
            ->setBlockType($pageBlockType)
            ->setBlockId((int) $id)
            ->setPosition($position);
        $this->entityManager->persist($pageLayoutEntry);
    }

    /**
     * Removes the page's layout entries and the blocks they reference (child rows go with them: ON DELETE CASCADE).
     *
     * @param list<PageLayoutEntry> $entries The page's entries, locked by the unit
     */
    private function clearPage(array $entries): void
    {
        $blockIds = [];
        foreach ($entries as $entry) {
            $blockIds[$entry->getBlockType()->value][] = $entry->getBlockId();
            $this->entityManager->remove($entry);
        }

        foreach ($blockIds as $type => $ids) {
            $this->entityManager->createQuery('DELETE FROM ' . self::BLOCK_CLASSES[$type] . ' b WHERE b.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->execute();
        }
    }
}
