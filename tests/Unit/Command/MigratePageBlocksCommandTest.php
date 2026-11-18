<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Command\MigratePageBlocksCommand;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Legacy\LegacyPageContentProviderInterface;
use Nowo\PageLayoutKitBundle\Persistence\PageLayoutTransaction;
use Nowo\PageLayoutKitBundle\Persistence\PageMigrationLock;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use Nowo\PageLayoutKitBundle\Service\PageBlockMigrator;
use Nowo\PageLayoutKitBundle\Tests\Support\LocaleTestSupport;
use Nowo\PageLayoutKitBundle\Tests\Support\SqliteEntityManagerFactory;
use Nowo\TransactionContract\TransactionRunnerInterface;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function array_column;
use function array_key_last;

final class MigratePageBlocksCommandTest extends TestCase
{
    public function testCommandNotesWhenIfEmptyFlagFindsExistingLayout(): void
    {
        $manager = SqliteEntityManagerFactory::create();
        $manager->persist(new PageLayoutEntry()->setPageKey('home')->setBlockType(PageBlockType::Hero)->setBlockId(1)->setPosition(0));
        $manager->flush();

        $tester = new CommandTester(new MigratePageBlocksCommand($this->migrator($manager)));
        self::assertSame(0, $tester->execute(['--if-empty' => true]));
        self::assertStringContainsString('Page blocks layout already exists.', $tester->getDisplay());
    }

    public function testCommandWarnsWhenMigrationDoesNotRun(): void
    {
        $tester = new CommandTester(new MigratePageBlocksCommand($this->migrator(SqliteEntityManagerFactory::create(), false)));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Use --force to replace.', $tester->getDisplay());
    }

    public function testCommandReportsSuccessWhenMigrationRuns(): void
    {
        $tester = new CommandTester(new MigratePageBlocksCommand($this->migrator(SqliteEntityManagerFactory::create())));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Page blocks migrated from legacy content.', $tester->getDisplay());
    }

    public function testCommandFailsWithNothingChangedWhileAnotherMigrationRuns(): void
    {
        $manager = SqliteEntityManagerFactory::create();
        $lock    = $this->createStub(PageMigrationLock::class);
        $lock->method('acquire')->willReturn(false);
        $logger = new RecordingMigrationLogger();

        $tester = new CommandTester(new MigratePageBlocksCommand($this->migrator($manager, lock: $lock), $logger));
        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Another page block migration is running', $tester->getDisplay());
        self::assertSame(['info', 'warning'], $logger->levels());
        self::assertSame(0, (int) $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM content_page_layout_entry'));
    }

    public function testCommandFailsAndSaysToRunAgainOnALockConflict(): void
    {
        $runner = $this->createStub(TransactionRunnerInterface::class);
        $runner->method('run')->willThrowException(TransientStorageFailure::fromPrevious(new RuntimeException('lock wait'), 1));
        $manager = SqliteEntityManagerFactory::create();
        $logger  = new RecordingMigrationLogger();

        $tester = new CommandTester(new MigratePageBlocksCommand($this->migrator($manager, transaction: new PageLayoutTransaction($manager, runner: $runner)), $logger));
        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Run the command again.', $tester->getDisplay());
        self::assertSame(['info', 'error'], $logger->levels());
        self::assertSame(TransientStorageFailure::class, $logger->lastContext()['exception']);
    }

    public function testCommandLogsEveryOutcome(): void
    {
        $logger = new RecordingMigrationLogger();
        (new CommandTester(new MigratePageBlocksCommand($this->migrator(SqliteEntityManagerFactory::create()), $logger)))->execute([]);
        self::assertSame(['info', 'info'], $logger->levels());

        $logger = new RecordingMigrationLogger();
        (new CommandTester(new MigratePageBlocksCommand($this->migrator(SqliteEntityManagerFactory::create(), false), $logger)))->execute([]);
        self::assertSame(['info', 'warning'], $logger->levels());

        $logger  = new RecordingMigrationLogger();
        $manager = SqliteEntityManagerFactory::create();
        $runner  = $this->createStub(TransactionRunnerInterface::class);
        $runner->method('run')->willThrowException(new RuntimeException('db down'));
        $failing = $this->migrator($manager, transaction: new PageLayoutTransaction($manager, runner: $runner));
        try {
            (new CommandTester(new MigratePageBlocksCommand($failing, $logger)))->execute([]);
            self::fail('The migration failure must be rethrown.');
        } catch (RuntimeException) {
        }
        self::assertSame(['info', 'error'], $logger->levels());
        self::assertSame(['bundle' => 'page_layout_kit', 'action' => 'migrate_page_blocks', 'force' => false, 'if_empty' => false, 'exception' => RuntimeException::class], $logger->lastContext());
    }

    private function migrator(EntityManagerInterface $manager, bool $withLegacy = true, ?PageLayoutTransaction $transaction = null, ?PageMigrationLock $lock = null): PageBlockMigrator
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        return new PageBlockMigrator(
            $manager,
            new PageLayoutEntryRepository($registry),
            LocaleTestSupport::defaults(),
            $withLegacy ? new FakeCommandLegacyProvider(['home:es' => ['hero_title' => 'Hero', 'detect_items' => [], 'process_items' => []], 'home:en' => [], 'contact:es' => ['h1' => 'Contact', 'expect_items' => []], 'contact:en' => []]) : null,
            $transaction,
            $lock ?? new PageMigrationLock(),
        );
    }
}

final readonly class FakeCommandLegacyProvider implements LegacyPageContentProviderInterface
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

/**
 * In-memory PSR-3 logger for the migration command tests.
 */
final class RecordingMigrationLogger extends AbstractLogger
{
    /** @var list<array{level: string, context: array<mixed>}> */
    private array $records = [];

    /** @param array<mixed> $context */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'context' => $context];
    }

    /** @return list<string> */
    public function levels(): array
    {
        return array_column($this->records, 'level');
    }

    /** @return array<mixed> */
    public function lastContext(): array
    {
        return $this->records === [] ? [] : $this->records[array_key_last($this->records)]['context'];
    }
}
