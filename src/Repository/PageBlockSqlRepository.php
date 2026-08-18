<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Repository;

use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Repository\Concerns\RunsDocumentedSql;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Batch SQL loader for page blocks to avoid per-block ORM N+1 queries.
 */
final readonly class PageBlockSqlRepository
{
    use RunsDocumentedSql;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<PageLayoutEntry> $entries
     *
     * @return array<string, array<string, mixed>>
     */
    public function loadDataForEntries(array $entries, string $locale): array
    {
        /** @var array<string, list<int>> $idsByType */
        $idsByType = [];

        foreach ($entries as $entry) {
            $idsByType[$entry->getBlockType()->value][] = $entry->getBlockId();
        }

        $data = [];

        foreach ($idsByType as $type => $ids) {
            $ids = array_values(array_unique(array_map(intval(...), $ids)));
            $loaded = match ($type) {
                PageBlockType::Hero->value => $this->loadHeroBlocks($ids, $locale),
                PageBlockType::Text->value => $this->loadTextBlocks($ids, $locale),
                PageBlockType::Cards->value => $this->loadCardsBlocks($ids, $locale),
                PageBlockType::List->value => $this->loadListBlocks($ids, $locale),
                PageBlockType::Cta->value => $this->loadCtaBlocks($ids, $locale),
                PageBlockType::Compare->value => $this->loadCompareBlocks($ids, $locale),
            };

            foreach ($loaded as $blockId => $blockData) {
                $data[$type . ':' . $blockId] = $blockData;
            }
        }

        return $data;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadHeroBlocks(array $ids, string $locale): array
    {
        if ([] === $ids) {
            return [];
        }

        [$inClause, $params] = $this->namedInClause('heroId', $ids, [
            'locale' => $locale,
            'fallback' => PageLocales::default(),
        ]);

        $sql = <<<SQL
            -- Loads hero blocks by id with active-locale translation or fallback
            -- Returns rows with block_id and translated SEO/hero fields ready to render
            SELECT
                b.id AS block_id,
                COALESCE(t.page_title, t_fb.page_title, '') AS pageTitle,
                COALESCE(t.page_description, t_fb.page_description, '') AS pageDescription,
                COALESCE(t.eyebrow, t_fb.eyebrow, '') AS eyebrow,
                COALESCE(t.title, t_fb.title, '') AS title,
                COALESCE(t.subtitle, t_fb.subtitle, '') AS subtitle,
                COALESCE(t.cta_primary, t_fb.cta_primary, '') AS ctaPrimary,
                COALESCE(t.cta_secondary, t_fb.cta_secondary, '') AS ctaSecondary
            FROM content_page_hero_block b
            LEFT JOIN content_page_hero_block_translation t
                ON t.translatable_id = b.id AND t.locale = :locale
            LEFT JOIN content_page_hero_block_translation t_fb
                ON t_fb.translatable_id = b.id AND t_fb.locale = :fallback
            WHERE b.id IN ({$inClause})
            SQL;

        return $this->indexByBlockId($this->fetchAllDocumentedSql($sql, $params));
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadTextBlocks(array $ids, string $locale): array
    {
        if ([] === $ids) {
            return [];
        }

        [$inClause, $params] = $this->namedInClause('textId', $ids, [
            'locale' => $locale,
            'fallback' => PageLocales::default(),
        ]);

        $sql = <<<SQL
            -- Loads text blocks by id with active-locale translation or fallback
            -- Returns rows with block_id, section_key, and translated title/body fields
            SELECT
                b.id AS block_id,
                b.section_key AS sectionKey,
                COALESCE(t.page_title, t_fb.page_title, '') AS pageTitle,
                COALESCE(t.page_description, t_fb.page_description, '') AS pageDescription,
                COALESCE(t.title, t_fb.title, '') AS title,
                COALESCE(t.body, t_fb.body, '') AS body
            FROM content_page_text_block b
            LEFT JOIN content_page_text_block_translation t
                ON t.translatable_id = b.id AND t.locale = :locale
            LEFT JOIN content_page_text_block_translation t_fb
                ON t_fb.translatable_id = b.id AND t_fb.locale = :fallback
            WHERE b.id IN ({$inClause})
            SQL;

        return $this->indexByBlockId($this->fetchAllDocumentedSql($sql, $params));
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadCardsBlocks(array $ids, string $locale): array
    {
        if ([] === $ids) {
            return [];
        }

        [$inClause, $params] = $this->namedInClause('cardsId', $ids, [
            'locale' => $locale,
            'fallback' => PageLocales::default(),
        ]);

        $sql = <<<SQL
            -- Loads card blocks by id with translated title
            -- Returns rows with block_id, section_key, and block title
            SELECT
                b.id AS block_id,
                b.section_key AS sectionKey,
                COALESCE(t.title, t_fb.title, '') AS title
            FROM content_page_cards_block b
            LEFT JOIN content_page_cards_block_translation t
                ON t.translatable_id = b.id AND t.locale = :locale
            LEFT JOIN content_page_cards_block_translation t_fb
                ON t_fb.translatable_id = b.id AND t_fb.locale = :fallback
            WHERE b.id IN ({$inClause})
            SQL;

        $blocks = $this->indexByBlockId($this->fetchAllDocumentedSql($sql, $params));
        $itemsByBlock = $this->loadCardItems($ids, $locale);

        foreach (array_keys($blocks) as $blockId) {
            $blocks[$blockId]['items'] = $itemsByBlock[$blockId] ?? [];
        }

        return $blocks;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadListBlocks(array $ids, string $locale): array
    {
        if ([] === $ids) {
            return [];
        }

        [$inClause, $params] = $this->namedInClause('listId', $ids, [
            'locale' => $locale,
            'fallback' => PageLocales::default(),
        ]);

        $sql = <<<SQL
            -- Loads list blocks by id with translated title
            -- Returns rows with block_id, section_key, and block title
            SELECT
                b.id AS block_id,
                b.section_key AS sectionKey,
                COALESCE(t.title, t_fb.title, '') AS title
            FROM content_page_list_block b
            LEFT JOIN content_page_list_block_translation t
                ON t.translatable_id = b.id AND t.locale = :locale
            LEFT JOIN content_page_list_block_translation t_fb
                ON t_fb.translatable_id = b.id AND t_fb.locale = :fallback
            WHERE b.id IN ({$inClause})
            SQL;

        $blocks = $this->indexByBlockId($this->fetchAllDocumentedSql($sql, $params));
        $itemsByBlock = $this->loadListItems($ids, $locale);

        foreach (array_keys($blocks) as $blockId) {
            $blocks[$blockId]['items'] = $itemsByBlock[$blockId] ?? [];
        }

        return $blocks;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadCtaBlocks(array $ids, string $locale): array
    {
        if ([] === $ids) {
            return [];
        }

        [$inClause, $params] = $this->namedInClause('ctaId', $ids, [
            'locale' => $locale,
            'fallback' => PageLocales::default(),
        ]);

        $sql = <<<SQL
            -- Loads CTA blocks by id with active-locale translation or fallback
            -- Returns rows with block_id, section_key, and translated title/body
            SELECT
                b.id AS block_id,
                b.section_key AS sectionKey,
                COALESCE(t.title, t_fb.title, '') AS title,
                COALESCE(t.body, t_fb.body, '') AS body
            FROM content_page_cta_block b
            LEFT JOIN content_page_cta_block_translation t
                ON t.translatable_id = b.id AND t.locale = :locale
            LEFT JOIN content_page_cta_block_translation t_fb
                ON t_fb.translatable_id = b.id AND t_fb.locale = :fallback
            WHERE b.id IN ({$inClause})
            SQL;

        return $this->indexByBlockId($this->fetchAllDocumentedSql($sql, $params));
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadCompareBlocks(array $ids, string $locale): array
    {
        if ([] === $ids) {
            return [];
        }

        [$inClause, $params] = $this->namedInClause('compareId', $ids, [
            'locale' => $locale,
            'fallback' => PageLocales::default(),
        ]);

        $sql = <<<SQL
            -- Loads compare blocks by id with active-locale translation or fallback
            -- Returns rows with block_id and translated before/after texts
            SELECT
                b.id AS block_id,
                COALESCE(t.before_label, t_fb.before_label, '') AS beforeLabel,
                COALESCE(t.before_text, t_fb.before_text, '') AS beforeText,
                COALESCE(t.after_label, t_fb.after_label, '') AS afterLabel,
                COALESCE(t.after_text, t_fb.after_text, '') AS afterText
            FROM content_page_compare_block b
            LEFT JOIN content_page_compare_block_translation t
                ON t.translatable_id = b.id AND t.locale = :locale
            LEFT JOIN content_page_compare_block_translation t_fb
                ON t_fb.translatable_id = b.id AND t_fb.locale = :fallback
            WHERE b.id IN ({$inClause})
            SQL;

        return $this->indexByBlockId($this->fetchAllDocumentedSql($sql, $params));
    }

    /**
     * @param list<int> $blockIds
     *
     * @return array<int, list<array{title: string, body: string}>>
     */
    private function loadCardItems(array $blockIds, string $locale): array
    {
        [$inClause, $params] = $this->namedInClause('cardBlockId', $blockIds, [
            'locale' => $locale,
            'fallback' => PageLocales::default(),
        ]);

        $sql = <<<SQL
            -- Loads card items for card blocks with active-locale translation or fallback
            -- Returns rows with block_id, position, and each card title/body ordered
            SELECT
                i.block_id AS block_id,
                i.position,
                COALESCE(t.title, t_fb.title, '') AS title,
                COALESCE(t.body, t_fb.body, '') AS body
            FROM content_page_card_item i
            LEFT JOIN content_page_card_item_translation t
                ON t.translatable_id = i.id AND t.locale = :locale
            LEFT JOIN content_page_card_item_translation t_fb
                ON t_fb.translatable_id = i.id AND t_fb.locale = :fallback
            WHERE i.block_id IN ({$inClause})
            ORDER BY i.block_id ASC, i.position ASC, i.id ASC
            SQL;

        $grouped = [];

        foreach ($this->fetchAllDocumentedSql($sql, $params) as $row) {
            $blockId = (int) $row['block_id'];
            $grouped[$blockId][] = [
                'title' => (string) $row['title'],
                'body' => (string) $row['body'],
            ];
        }

        return $grouped;
    }

    /**
     * @param list<int> $blockIds
     *
     * @return array<int, list<array{text: string}>>
     */
    private function loadListItems(array $blockIds, string $locale): array
    {
        [$inClause, $params] = $this->namedInClause('listBlockId', $blockIds, [
            'locale' => $locale,
            'fallback' => PageLocales::default(),
        ]);

        $sql = <<<SQL
            -- Loads list items for list blocks with active-locale translation or fallback
            -- Returns rows with block_id, position, and each item text ordered
            SELECT
                i.block_id AS block_id,
                i.position,
                COALESCE(t.text, t_fb.text, '') AS text
            FROM content_page_list_item i
            LEFT JOIN content_page_list_item_translation t
                ON t.translatable_id = i.id AND t.locale = :locale
            LEFT JOIN content_page_list_item_translation t_fb
                ON t_fb.translatable_id = i.id AND t_fb.locale = :fallback
            WHERE i.block_id IN ({$inClause})
            ORDER BY i.block_id ASC, i.position ASC, i.id ASC
            SQL;

        $grouped = [];

        foreach ($this->fetchAllDocumentedSql($sql, $params) as $row) {
            $blockId = (int) $row['block_id'];
            $grouped[$blockId][] = [
                'text' => (string) $row['text'],
            ];
        }

        return $grouped;
    }

    /**
     * @param list<int> $ids
     * @param array<string, mixed> $baseParams
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function namedInClause(string $prefix, array $ids, array $baseParams): array
    {
        $placeholders = [];
        $params = $baseParams;

        foreach ($ids as $index => $id) {
            $key = $prefix . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        return [implode(', ', $placeholders), $params];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    private function indexByBlockId(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $blockId = (int) $row['block_id'];
            unset($row['block_id']);
            $indexed[$blockId] = $row;
        }

        return $indexed;
    }
}
