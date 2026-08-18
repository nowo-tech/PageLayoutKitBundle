<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Repository\PageBlockSqlRepository;
use PHPUnit\Framework\TestCase;

final class PageBlockSqlRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        PageLocales::bind(new PageLocales('es', ['es', 'en']));
    }

    public function testLoadDataForEntriesReturnsNormalizedDataForEveryBlockType(): void
    {
        $queries = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql, array $params) use (&$queries): array {
                $queries[] = [$sql, $params];

                return match (true) {
                    str_contains($sql, 'FROM content_page_hero_block b') => [[
                        'block_id' => 10,
                        'pageTitle' => 'Hero title',
                        'pageDescription' => 'Hero description',
                        'eyebrow' => 'Eyebrow',
                        'title' => 'Main hero',
                        'subtitle' => 'Subtitle',
                        'ctaPrimary' => 'Go',
                        'ctaSecondary' => 'More',
                    ]],
                    str_contains($sql, 'FROM content_page_text_block b') => [[
                        'block_id' => 11,
                        'sectionKey' => 'problem',
                        'pageTitle' => 'Text title',
                        'pageDescription' => 'Text description',
                        'title' => 'Problem',
                        'body' => 'Problem body',
                    ]],
                    str_contains($sql, 'FROM content_page_cards_block b') => [[
                        'block_id' => 12,
                        'sectionKey' => 'value',
                        'title' => 'Cards title',
                    ]],
                    str_contains($sql, 'FROM content_page_card_item i') => [
                        ['block_id' => 12, 'position' => 0, 'title' => 'Card 1', 'body' => 'Body 1'],
                        ['block_id' => 12, 'position' => 1, 'title' => 'Card 2', 'body' => 'Body 2'],
                    ],
                    str_contains($sql, 'FROM content_page_list_block b') => [[
                        'block_id' => 13,
                        'sectionKey' => 'steps',
                        'title' => 'List title',
                    ]],
                    str_contains($sql, 'FROM content_page_list_item i') => [
                        ['block_id' => 13, 'position' => 0, 'text' => 'Step 1'],
                        ['block_id' => 13, 'position' => 1, 'text' => 'Step 2'],
                    ],
                    str_contains($sql, 'FROM content_page_cta_block b') => [[
                        'block_id' => 14,
                        'sectionKey' => 'contact_form',
                        'title' => 'CTA title',
                        'body' => 'CTA body',
                    ]],
                    str_contains($sql, 'FROM content_page_compare_block b') => [[
                        'block_id' => 15,
                        'beforeLabel' => 'Before',
                        'beforeText' => 'Old state',
                        'afterLabel' => 'After',
                        'afterText' => 'New state',
                    ]],
                    default => [],
                };
            });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $repository = new PageBlockSqlRepository($entityManager);
        $data = $repository->loadDataForEntries([
            $this->createEntry(PageBlockType::Hero, 10),
            $this->createEntry(PageBlockType::Text, 11),
            $this->createEntry(PageBlockType::Cards, 12),
            $this->createEntry(PageBlockType::List, 13),
            $this->createEntry(PageBlockType::Cta, 14),
            $this->createEntry(PageBlockType::Compare, 15),
        ], 'en');

        self::assertSame('Main hero', $data['hero:10']['title']);
        self::assertSame('problem', $data['text:11']['sectionKey']);
        self::assertSame('Card 2', $data['cards:12']['items'][1]['title']);
        self::assertSame('Step 1', $data['list:13']['items'][0]['text']);
        self::assertSame('CTA body', $data['cta:14']['body']);
        self::assertSame('New state', $data['compare:15']['afterText']);
        self::assertCount(8, $queries);
        self::assertTrue(str_starts_with(ltrim($queries[0][0]), '--'));
        self::assertSame('en', $queries[0][1]['locale']);
        self::assertSame('es', $queries[0][1]['fallback']);
    }

    private function createEntry(PageBlockType $type, int $blockId): PageLayoutEntry
    {
        return (new PageLayoutEntry())
            ->setPageKey('home')
            ->setBlockType($type)
            ->setBlockId($blockId)
            ->setPosition(0);
    }
}
