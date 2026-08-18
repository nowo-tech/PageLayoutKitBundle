<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Command\MigratePageBlocksCommand;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Legacy\LegacyPageContentProviderInterface;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use Nowo\PageLayoutKitBundle\Service\PageBlockMigrator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MigratePageBlocksCommandTest extends TestCase
{
    /** @var list<object> */
    private array $persisted = [];

    private int $nextId = 1;

    protected function setUp(): void
    {
        PageLocales::bind(new PageLocales('es', ['es', 'en']));
        $this->persisted = [];
        $this->nextId = 1;
    }

    public function testCommandNotesWhenIfEmptyFlagFindsExistingLayout(): void
    {
        $entry = (new PageLayoutEntry())
            ->setPageKey('home')
            ->setBlockType(PageBlockType::Hero)
            ->setBlockId(1)
            ->setPosition(0);

        $command = new MigratePageBlocksCommand(new PageBlockMigrator(
            $this->entityManagerMock(),
            $this->createPageLayoutEntryRepository([
                'home' => [$entry],
            ]),
            null,
        ));

        $tester = new CommandTester($command);
        self::assertSame(0, $tester->execute(['--if-empty' => true]));
        self::assertStringContainsString('Page blocks layout already exists.', $tester->getDisplay());
    }

    public function testCommandWarnsWhenMigrationDoesNotRun(): void
    {
        $command = new MigratePageBlocksCommand(new PageBlockMigrator(
            $this->entityManagerMock(),
            $this->createPageLayoutEntryRepository([
                'home' => [],
            ]),
            null,
        ));

        $tester = new CommandTester($command);
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Use --force to replace.', $tester->getDisplay());
    }

    public function testCommandReportsSuccessWhenMigrationRuns(): void
    {
        $command = new MigratePageBlocksCommand(new PageBlockMigrator(
            $this->entityManagerMock(),
            $this->createPageLayoutEntryRepository([
                'home' => [],
                'contact' => [],
            ]),
            new FakeCommandLegacyProvider([
                'home:es'    => [
                    'hero_title' => 'Hero',
                    'detect_items' => [],
                    'process_items' => [],
                ],
                'home:en'    => [],
                'contact:es' => [
                    'h1' => 'Contact',
                    'expect_items' => [],
                ],
                'contact:en' => [],
            ]),
        ));

        $tester = new CommandTester($command);
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Page blocks migrated from legacy content.', $tester->getDisplay());
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

    private function entityManagerMock(): EntityManagerInterface
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

        return $entityManager;
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

final class FakeCommandLegacyProvider implements LegacyPageContentProviderInterface
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
