<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Legacy\LegacyPageContentProviderInterface;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Repository\PageBlockSqlRepository;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use Nowo\PageLayoutKitBundle\Security\PageLayoutProtection;
use Nowo\PageLayoutKitBundle\Security\PageLayoutProtectionConfig;
use Nowo\PageLayoutKitBundle\Service\PageBlockProvider;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class PageBlockProviderTest extends TestCase
{
    protected function setUp(): void
    {
        PageLocales::bind(new PageLocales('es', ['es', 'en']));
    }

    public function testReturnsLegacyLayoutsAndMetaWhenNoStoredLayoutExists(): void
    {
        $provider = new PageBlockProvider(
            $this->createPageLayoutEntryRepository([
                'home'    => [],
                'contact' => [],
            ]),
            $this->createPageBlockSqlRepository([], new stdClass()),
            $this->createRequestStack('es'),
            new PageLocales('es', ['es', 'en']),
            $this->createProtection(),
            new FakeLegacyPageContentProvider([
                'home:es' => [
                    'page_title'         => 'Inicio',
                    'page_description'   => 'Meta inicio',
                    'hero_title'         => 'Hero ES',
                    'hero_subtitle'      => 'Sub ES',
                    'hero_cta_primary'   => 'Go',
                    'hero_cta_secondary' => 'More',
                    'problem_title'      => 'Problem',
                    'problem_text'       => 'Body',
                    'detect_items'       => ['Uno', 'Dos'],
                    'process_items'      => ['Paso 1'],
                    'cta_title'          => 'CTA',
                    'cta_text'           => 'CTA body',
                ],
                'contact:es' => [
                    'page_title'       => 'Contacto',
                    'page_description' => 'Meta contacto',
                    'h1'               => 'Hablemos',
                    'intro'            => 'Intro',
                    'expect_title'     => 'Expect',
                    'expect_text'      => 'Expect body',
                    'expect_items'     => ['Alpha', 'Beta'],
                    'before_label'     => 'Antes',
                    'before_text'      => 'Old',
                    'after_label'      => 'Despues',
                    'after_text'       => 'New',
                    'form_submit'      => 'Send',
                    'form_note'        => 'Note',
                ],
            ]),
        );

        $layout = $provider->getLayout('home', 'es');
        self::assertNotEmpty($layout);
        self::assertSame(PageBlockType::Hero, $layout[0]->type);
        self::assertSame('Hero ES', $layout[0]->data['title']);
        self::assertCount(9, $layout);

        $meta = $provider->pageMeta('home', 'es');
        self::assertSame('Inicio', $meta['title']);
        self::assertSame('Meta inicio', $meta['description']);

        self::assertSame($layout, $provider->getLayout('home', 'es'));
        $provider->reset();
        self::assertFalse($provider->hasLayout('home'));

        $contactLayout = $provider->getLayout('contact', 'es');
        self::assertSame(PageBlockType::Compare, $contactLayout[3]->type);
        self::assertSame('Antes', $contactLayout[3]->data['beforeLabel']);
        self::assertSame(
            ['title' => 'Contacto', 'description' => 'Meta contacto'],
            $provider->pageMeta('contact', 'es'),
        );
    }

    public function testPageMetaFallsBackToLegacyWhenStoredLayoutHasNoSeoBlock(): void
    {
        $ctaEntry       = $this->createLayoutEntry('landing', PageBlockType::Cta, 12, 0, 701);
        $state          = new stdClass();
        $state->queries = 0;

        $provider = new PageBlockProvider(
            $this->createPageLayoutEntryRepository([
                'landing' => [$ctaEntry],
            ]),
            $this->createPageBlockSqlRepository([
                'FROM content_page_cta_block b' => [[
                    'block_id'   => 12,
                    'sectionKey' => 'footer',
                    'title'      => 'CTA',
                    'body'       => 'Body',
                ]],
            ], $state),
            $this->createRequestStack('en'),
            new PageLocales('es', ['es', 'en']),
            $this->createProtection(),
            new FakeLegacyPageContentProvider([
                'landing:en' => [
                    'page_title'       => 'Landing title',
                    'page_description' => 'Landing description',
                ],
            ]),
        );

        self::assertSame(
            ['title' => 'Landing title', 'description' => 'Landing description'],
            $provider->pageMeta('landing', 'en'),
        );
    }

    public function testUnknownLegacyPageKeysProduceAnEmptyLayout(): void
    {
        $provider = new PageBlockProvider(
            $this->createPageLayoutEntryRepository([
                'custom' => [],
            ]),
            $this->createPageBlockSqlRepository([], new stdClass()),
            $this->createRequestStack('es'),
            new PageLocales('es', ['es', 'en']),
            $this->createProtection(),
            new FakeLegacyPageContentProvider([]),
        );

        self::assertSame([], $provider->getLayout('custom', 'es'));
    }

    public function testPageMetaFallsBackToLegacyDefaultLocaleAndHandlesMissingProvider(): void
    {
        $withFallback = new PageBlockProvider(
            $this->createPageLayoutEntryRepository([
                'home' => [],
            ]),
            $this->createPageBlockSqlRepository([], new stdClass()),
            $this->createRequestStack('en'),
            new PageLocales('es', ['es', 'en']),
            $this->createProtection(),
            new FakeLegacyPageContentProvider([
                'home:es' => [
                    'page_title'       => 'Inicio',
                    'page_description' => 'Descripcion',
                ],
                'home:en' => [],
            ], 'es'),
        );

        self::assertSame(
            ['title' => 'Inicio', 'description' => 'Descripcion'],
            $withFallback->pageMeta('home', 'en'),
        );

        $withoutProvider = new PageBlockProvider(
            $this->createPageLayoutEntryRepository([
                'home' => [],
            ]),
            $this->createPageBlockSqlRepository([], new stdClass()),
            $this->createRequestStack('es'),
            new PageLocales('es', ['es', 'en']),
            $this->createProtection(),
        );

        self::assertSame(
            ['title' => '', 'description' => ''],
            $withoutProvider->pageMeta('home', 'es'),
        );
    }

    public function testBuildsStoredLayoutViewsCachesAndSkipsEntriesWithoutLoadedData(): void
    {
        $heroEntry      = $this->createLayoutEntry('home', PageBlockType::Hero, 10, 0, 501);
        $textEntry      = $this->createLayoutEntry('home', PageBlockType::Text, 11, 1, 502);
        $state          = new stdClass();
        $state->queries = 0;

        $provider = new PageBlockProvider(
            $this->createPageLayoutEntryRepository([
                'home' => [$heroEntry, $textEntry],
            ]),
            $this->createPageBlockSqlRepository([
                'FROM content_page_hero_block b' => [[
                    'block_id'        => 10,
                    'pageTitle'       => 'Stored title',
                    'pageDescription' => 'Stored description',
                    'eyebrow'         => 'Eyebrow',
                    'title'           => 'Stored hero',
                    'subtitle'        => 'Stored subtitle',
                    'ctaPrimary'      => 'Primary',
                    'ctaSecondary'    => 'Secondary',
                ]],
                'FROM content_page_text_block b' => [],
            ], $state),
            $this->createRequestStack('en'),
            new PageLocales('es', ['es', 'en']),
            $this->createProtection(),
            new FakeLegacyPageContentProvider([]),
        );

        $layout = $provider->getLayout('home');
        self::assertCount(1, $layout);
        self::assertSame(501, $layout[0]->layoutId);
        self::assertSame('Stored hero', $layout[0]->data['title']);
        self::assertNull($layout[0]->sectionKey);
        self::assertSame(2, $state->queries);

        self::assertSame(
            ['title' => 'Stored title', 'description' => 'Stored description'],
            $provider->pageMeta('home'),
        );
        self::assertSame(2, $state->queries);
        self::assertTrue($provider->hasLayout('home'));

        $provider->reset();
        $provider->getLayout('home', 'en');
        self::assertSame(4, $state->queries);
    }

    public function testAllowlistSanitizerStripsScriptFromStoredBlockBody(): void
    {
        $textEntry      = $this->createLayoutEntry('home', PageBlockType::Text, 11, 0, 502);
        $state          = new stdClass();
        $state->queries = 0;

        $provider = new PageBlockProvider(
            $this->createPageLayoutEntryRepository([
                'home' => [$textEntry],
            ]),
            $this->createPageBlockSqlRepository([
                'FROM content_page_text_block b' => [[
                    'block_id' => 11,
                    'title'    => 'Title',
                    'body'     => '<p>Safe</p><script>alert(1)</script>',
                ]],
            ], $state),
            $this->createRequestStack('es'),
            new PageLocales('es', ['es', 'en']),
            $this->createProtection(HtmlSanitizeStrategy::Allowlist),
        );

        $layout = $provider->getLayout('home', 'es');
        self::assertCount(1, $layout);
        self::assertStringContainsString('<p>Safe</p>', $layout[0]->data['body']);
        self::assertStringNotContainsString('script', $layout[0]->data['body']);
    }

    private function createProtection(HtmlSanitizeStrategy $strategy = HtmlSanitizeStrategy::None): PageLayoutProtection
    {
        return new PageLayoutProtection(new PageLayoutProtectionConfig($strategy, null));
    }

    private function createLayoutEntry(
        string $pageKey,
        PageBlockType $type,
        int $blockId,
        int $position,
        int $id,
    ): PageLayoutEntry {
        $entry = (new PageLayoutEntry())
            ->setPageKey($pageKey)
            ->setBlockType($type)
            ->setBlockId($blockId)
            ->setPosition($position);

        $this->assignEntityId($entry, $id);

        return $entry;
    }

    /**
     * @param array<string, list<PageLayoutEntry>> $resultsByPageKey
     */
    private function createPageLayoutEntryRepository(array $resultsByPageKey): PageLayoutEntryRepository
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')
            ->with(PageLayoutEntry::class)
            ->willReturn(new ClassMetadata(PageLayoutEntry::class));
        $entityManager->method('createQueryBuilder')
            ->willReturnCallback(function () use ($resultsByPageKey): QueryBuilder {
                $params = [];
                $query  = $this->createMock(Query::class);
                $query->method('getResult')
                    ->willReturnCallback(static function () use (&$params, $resultsByPageKey): array {
                        return $resultsByPageKey[$params['pageKey'] ?? ''] ?? [];
                    });

                $queryBuilder = $this->createMock(QueryBuilder::class);
                $queryBuilder->method('select')->willReturnSelf();
                $queryBuilder->method('from')->willReturnSelf();
                $queryBuilder->method('andWhere')->willReturnSelf();
                $queryBuilder->method('orderBy')->willReturnSelf();
                $queryBuilder->method('setParameter')
                    ->willReturnCallback(static function (string $key, mixed $value) use (&$params, $queryBuilder): QueryBuilder {
                        $params[$key] = $value;

                        return $queryBuilder;
                    });
                $queryBuilder->method('getQuery')->willReturn($query);

                return $queryBuilder;
            });

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')
            ->with(PageLayoutEntry::class)
            ->willReturn($entityManager);

        return new PageLayoutEntryRepository($registry);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $rowsBySqlFragment
     */
    private function createPageBlockSqlRepository(array $rowsBySqlFragment, stdClass $state): PageBlockSqlRepository
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturnCallback(static function (string $sql) use ($rowsBySqlFragment, $state): array {
                ++$state->queries;

                foreach ($rowsBySqlFragment as $fragment => $rows) {
                    if (str_contains($sql, $fragment)) {
                        return $rows;
                    }
                }

                return [];
            });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return new PageBlockSqlRepository($entityManager);
    }

    private function createRequestStack(string $locale): RequestStack
    {
        $request = new Request();
        $request->setLocale($locale);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }

    private function assignEntityId(object $entity, int $id): void
    {
        $reflection = new ReflectionObject($entity);

        do {
            if ($reflection->hasProperty('id')) {
                $property = $reflection->getProperty('id');
                $property->setValue($entity, $id);

                return;
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);
    }
}

final readonly class FakeLegacyPageContentProvider implements LegacyPageContentProviderInterface
{
    /**
     * @param array<string, array<string, mixed>> $contentByPageAndLocale
     */
    public function __construct(
        private array $contentByPageAndLocale,
        private string $defaultLocale = 'es',
    ) {
    }

    public function contentForPage(string $pageKey, string $locale): array
    {
        return $this->contentByPageAndLocale[$pageKey . ':' . $locale] ?? [];
    }

    public function defaultLocale(): string
    {
        return $this->defaultLocale;
    }
}
