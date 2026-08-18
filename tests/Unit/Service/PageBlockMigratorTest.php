<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Legacy\LegacyPageContentProviderInterface;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use Nowo\PageLayoutKitBundle\Service\PageBlockMigrator;
use PHPUnit\Framework\TestCase;

final class PageBlockMigratorTest extends TestCase
{
    private int $nextId = 1;

    /** @var list<object> */
    private array $persisted = [];

    protected function setUp(): void
    {
        PageLocales::bind(new PageLocales('es', ['es', 'en']));
        $this->nextId = 1;
        $this->persisted = [];
    }

    public function testIsEmptyWhenNoLayoutEntries(): void
    {
        $migrator = new PageBlockMigrator(
            $this->entityManagerMock(),
            $this->createPageLayoutEntryRepository([
                'home' => [],
            ]),
            null,
        );

        self::assertTrue($migrator->isEmpty());
    }

    public function testIsEmptyFalseWhenLayoutExists(): void
    {
        $entry = (new PageLayoutEntry())
            ->setPageKey('home')
            ->setBlockType(PageBlockType::Hero)
            ->setBlockId(1)
            ->setPosition(0);

        $migrator = new PageBlockMigrator(
            $this->entityManagerMock(),
            $this->createPageLayoutEntryRepository([
                'home' => [$entry],
            ]),
            null,
        );

        self::assertFalse($migrator->isEmpty());
    }

    public function testMigrateReturnsFalseWhenNoLegacyProviderIsAvailable(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $migrator = new PageBlockMigrator(
            $entityManager,
            $this->createPageLayoutEntryRepository([
                'home' => [],
            ]),
            null,
        );

        self::assertFalse($migrator->migrate());
    }

    public function testMigrateReturnsFalseWhenNotEmptyAndNotForced(): void
    {
        $entry = (new PageLayoutEntry())
            ->setPageKey('home')
            ->setBlockType(PageBlockType::Hero)
            ->setBlockId(1)
            ->setPosition(0);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $migrator = new PageBlockMigrator(
            $entityManager,
            $this->createPageLayoutEntryRepository([
                'home' => [$entry],
            ]),
            new FakeMigratorLegacyProvider($this->legacyContentMap()),
        );

        self::assertFalse($migrator->migrate(false));
    }

    public function testMigrateForceClearsExistingBeforeReimporting(): void
    {
        $entry = (new PageLayoutEntry())
            ->setPageKey('home')
            ->setBlockType(PageBlockType::Hero)
            ->setBlockId(1)
            ->setPosition(0);

        $migrator = new PageBlockMigrator(
            $this->entityManagerMock(7),
            $this->createPageLayoutEntryRepository([
                'home' => [$entry],
                'contact' => [],
            ]),
            new FakeMigratorLegacyProvider($this->legacyContentMap()),
        );

        self::assertTrue($migrator->migrate(true));
    }

    public function testContentForPageReturnsEmptyArrayWhenNoLegacyProviderIsAvailable(): void
    {
        $migrator = new PageBlockMigrator(
            $this->entityManagerMock(),
            $this->createPageLayoutEntryRepository([
                'home' => [],
            ]),
            null,
        );

        $method = new \ReflectionMethod(PageBlockMigrator::class, 'contentForPage');
        $method->setAccessible(true);

        self::assertSame([], $method->invoke($migrator, 'home'));
    }

    public function testAddLayoutFlushesBlocksWithoutIdsBeforePersistingTheLayoutEntry(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->willReturnCallback(function (object $entity): void {
                $this->persisted[] = $entity;
            });
        $entityManager->expects(self::once())
            ->method('flush')
            ->willReturnCallback(function (): void {
                foreach ($this->persisted as $entity) {
                    if ($entity instanceof \Nowo\PageLayoutKitBundle\Entity\PageHeroBlock) {
                        $this->assignEntityId($entity, 99);
                    }
                }
            });

        $migrator = new PageBlockMigrator(
            $entityManager,
            $this->createPageLayoutEntryRepository([
                'home' => [],
            ]),
            new FakeMigratorLegacyProvider($this->legacyContentMap()),
        );

        $block = new \Nowo\PageLayoutKitBundle\Entity\PageHeroBlock();
        $entityManager->persist($block);

        $method = new \ReflectionMethod(PageBlockMigrator::class, 'addLayout');
        $method->setAccessible(true);
        $method->invoke($migrator, 'home', PageBlockType::Hero, $block, 4);

        $layoutEntries = array_values(array_filter(
            $this->persisted,
            static fn (object $entity): bool => $entity instanceof PageLayoutEntry,
        ));
        self::assertCount(1, $layoutEntries);
        self::assertSame(99, $layoutEntries[0]->getBlockId());
        self::assertSame(4, $layoutEntries[0]->getPosition());
    }

    public function testMigrateReturnsTrueAndPersistsTypedBlocksWithLegacyContent(): void
    {
        $entityManager = $this->entityManagerMock();
        $migrator = new PageBlockMigrator(
            $entityManager,
            $this->createPageLayoutEntryRepository([
                'home'    => [],
                'contact' => [],
            ]),
            new FakeMigratorLegacyProvider($this->legacyContentMap()),
        );

        self::assertTrue($migrator->migrate(false));

        $layoutEntries = array_values(array_filter(
            $this->persisted,
            static fn (object $entity): bool => $entity instanceof PageLayoutEntry,
        ));
        self::assertCount(14, $layoutEntries);
        self::assertSame('home', $layoutEntries[0]->getPageKey());
        self::assertSame(PageBlockType::Hero, $layoutEntries[0]->getBlockType());
        self::assertSame('contact', $layoutEntries[13]->getPageKey());
        self::assertSame(PageBlockType::Cta, $layoutEntries[13]->getBlockType());
        self::assertGreaterThan(0, $layoutEntries[0]->getBlockId());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function legacyContentMap(): array
    {
        return [
            'home:es'    => [
                'page_title' => 'Inicio',
                'page_description' => 'Meta inicio',
                'hero_eyebrow' => 'Hola',
                'hero_title' => 'Hero',
                'hero_subtitle' => 'Sub',
                'hero_cta_primary' => 'Ir',
                'hero_cta_secondary' => 'Mas',
                'problem_title' => 'Problema',
                'problem_text' => 'Texto problema',
                'value_title' => 'Valor',
                'value_1_title' => 'V1',
                'value_1_text' => 'VT1',
                'value_2_title' => 'V2',
                'value_2_text' => 'VT2',
                'value_3_title' => 'V3',
                'value_3_text' => 'VT3',
                'value_4_title' => 'V4',
                'value_4_text' => 'VT4',
                'pain_title' => 'Dolor',
                'pain_1_title' => 'P1',
                'pain_1_text' => 'PT1',
                'pain_2_title' => 'P2',
                'pain_2_text' => 'PT2',
                'pain_3_title' => 'P3',
                'pain_3_text' => 'PT3',
                'detect_title' => 'Detectar',
                'detect_items' => ['Uno', 'Dos'],
                'services_title' => 'Servicios',
                'services_text' => 'Texto servicios',
                'profile_title' => 'Perfil',
                'profile_text' => 'Texto perfil',
                'process_title' => 'Proceso',
                'process_items' => ['Paso 1', 'Paso 2'],
                'cta_title' => 'CTA',
                'cta_text' => 'Texto CTA',
            ],
            'home:en'    => [],
            'contact:es' => [
                'page_title' => 'Contacto',
                'page_description' => 'Meta contacto',
                'h1' => 'Hablemos',
                'intro' => 'Intro contacto',
                'expect_title' => 'Que esperar',
                'expect_text' => 'Texto expect',
                'expect_items' => ['Item A', 'Item B'],
                'before_label' => 'Antes',
                'before_text' => 'Texto antes',
                'after_label' => 'Despues',
                'after_text' => 'Texto despues',
                'form_submit' => 'Enviar',
                'form_note' => 'Nota form',
            ],
            'contact:en' => [],
        ];
    }

    private function entityManagerMock(int $withDeleteQueries = 0): EntityManagerInterface
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;

            if (method_exists($entity, 'getId') && null === $entity->getId()) {
                $this->assignEntityId($entity, $this->nextId++);
            }
        });
        $entityManager->method('flush')->willReturnCallback(function (): void {
            foreach ($this->persisted as $entity) {
                if (method_exists($entity, 'getId') && null === $entity->getId()) {
                    $this->assignEntityId($entity, $this->nextId++);
                }
            }
        });

        if ($withDeleteQueries > 0) {
            $deleteQuery = $this->createMock(Query::class);
            $deleteQuery->expects(self::exactly($withDeleteQueries))->method('execute');
            $entityManager->expects(self::exactly($withDeleteQueries))
                ->method('createQuery')
                ->willReturn($deleteQuery);
        }

        return $entityManager;
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
                $query = $this->createMock(Query::class);
                $query->method('getResult')
                    ->willReturnCallback(function () use (&$params, $resultsByPageKey): array {
                        return $resultsByPageKey[$params['pageKey'] ?? ''] ?? [];
                    });

                $queryBuilder = $this->createMock(QueryBuilder::class);
                $queryBuilder->method('select')->willReturnSelf();
                $queryBuilder->method('from')->willReturnSelf();
                $queryBuilder->method('andWhere')->willReturnSelf();
                $queryBuilder->method('orderBy')->willReturnSelf();
                $queryBuilder->method('setParameter')
                    ->willReturnCallback(function (string $key, mixed $value) use (&$params, $queryBuilder): QueryBuilder {
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

    private function assignEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionObject($entity);

        do {
            if ($reflection->hasProperty('id')) {
                $property = $reflection->getProperty('id');
                $property->setAccessible(true);
                $property->setValue($entity, $id);

                return;
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);
    }
}

final class FakeMigratorLegacyProvider implements LegacyPageContentProviderInterface
{
    /**
     * @param array<string, array<string, mixed>> $contentByPageAndLocale
     */
    public function __construct(
        private readonly array $contentByPageAndLocale,
        private readonly string $defaultLocale = 'es',
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
