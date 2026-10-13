<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Repository;

use Doctrine\DBAL\Connection;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Repository\PageBlockSqlRepository;
use Nowo\PageLayoutKitBundle\Tests\Support\SqliteEntityManagerFactory;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Runs the batch loader against a real SQLite schema created with `doctrine.table_prefix`
 * (regression: native SQL used to hard-code the unprefixed `content_page_*` tables).
 */
#[RequiresPhpExtension('pdo_sqlite')]
final class PageBlockSqlRepositoryTablePrefixTest extends TestCase
{
    public function testLoadsEveryBlockTypeFromPrefixedTables(): void
    {
        $entityManager = SqliteEntityManagerFactory::create('acme_');
        $connection    = $entityManager->getConnection();

        self::assertSame('acme_content_page_hero_block', $entityManager->getClassMetadata(PageHeroBlock::class)->getTableName());

        $this->seed($connection, 'acme_');

        $repository = new PageBlockSqlRepository($entityManager, new PageLocales('es', ['es', 'en']));
        $data       = $repository->loadDataForEntries([
            $this->entry(PageBlockType::Hero, 1),
            $this->entry(PageBlockType::Text, 1),
            $this->entry(PageBlockType::Cards, 1),
            $this->entry(PageBlockType::List, 1),
            $this->entry(PageBlockType::Cta, 1),
            $this->entry(PageBlockType::Compare, 1),
        ], 'en');

        self::assertSame('Hero EN', $data['hero:1']['title']);
        self::assertSame('Text EN', $data['text:1']['title']);
        self::assertSame('intro', $data['text:1']['sectionKey']);
        self::assertSame('Cards ES', $data['cards:1']['title']);
        self::assertSame([['title' => 'Card EN', 'body' => 'Card body']], $data['cards:1']['items']);
        self::assertSame('List EN', $data['list:1']['title']);
        self::assertSame([['text' => 'Item EN']], $data['list:1']['items']);
        self::assertSame('CTA EN', $data['cta:1']['title']);
        self::assertSame('After EN', $data['compare:1']['afterText']);
    }

    private function seed(Connection $connection, string $p): void
    {
        $connection->insert($p . 'content_page_hero_block', ['id' => 1]);
        $hero = ['page_title' => '', 'page_description' => '', 'subtitle' => '', 'cta_primary' => '', 'cta_secondary' => '', 'translatable_id' => 1];
        $connection->insert($p . 'content_page_hero_block_translation', $hero + ['locale' => 'es', 'eyebrow' => 'Hero ES eyebrow', 'title' => 'Hero ES']);
        $connection->insert($p . 'content_page_hero_block_translation', $hero + ['locale' => 'en', 'eyebrow' => '', 'title' => 'Hero EN']);

        $connection->insert($p . 'content_page_text_block', ['id' => 1, 'section_key' => 'intro']);
        $connection->insert($p . 'content_page_text_block_translation', ['translatable_id' => 1, 'locale' => 'en', 'title' => 'Text EN', 'body' => 'Body']);

        $connection->insert($p . 'content_page_cards_block', ['id' => 1, 'section_key' => 'cards']);
        $connection->insert($p . 'content_page_cards_block_translation', ['translatable_id' => 1, 'locale' => 'es', 'title' => 'Cards ES']);
        $connection->insert($p . 'content_page_card_item', ['id' => 1, 'block_id' => 1, 'position' => 0]);
        $connection->insert($p . 'content_page_card_item_translation', ['translatable_id' => 1, 'locale' => 'en', 'title' => 'Card EN', 'body' => 'Card body']);

        $connection->insert($p . 'content_page_list_block', ['id' => 1, 'section_key' => 'steps']);
        $connection->insert($p . 'content_page_list_block_translation', ['translatable_id' => 1, 'locale' => 'en', 'title' => 'List EN']);
        $connection->insert($p . 'content_page_list_item', ['id' => 1, 'block_id' => 1, 'position' => 0]);
        $connection->insert($p . 'content_page_list_item_translation', ['translatable_id' => 1, 'locale' => 'en', 'text' => 'Item EN']);

        $connection->insert($p . 'content_page_cta_block', ['id' => 1, 'section_key' => 'contact']);
        $connection->insert($p . 'content_page_cta_block_translation', ['translatable_id' => 1, 'locale' => 'en', 'title' => 'CTA EN', 'body' => 'CTA body']);

        $connection->insert($p . 'content_page_compare_block', ['id' => 1]);
        $connection->insert($p . 'content_page_compare_block_translation', ['translatable_id' => 1, 'locale' => 'en', 'before_label' => 'B', 'before_text' => 'Before EN', 'after_label' => 'A', 'after_text' => 'After EN']);
    }

    private function entry(PageBlockType $type, int $blockId): PageLayoutEntry
    {
        return (new PageLayoutEntry())->setPageKey('home')->setBlockType($type)->setBlockId($blockId)->setPosition(0);
    }
}
