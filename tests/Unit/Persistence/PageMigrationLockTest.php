<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Nowo\PageLayoutKitBundle\Persistence\PageMigrationLock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class PageMigrationLockTest extends TestCase
{
    /**
     * @return iterable<string, array{0: AbstractPlatform, 1: string, 2: string}>
     */
    public static function platforms(): iterable
    {
        $mysqlAcquire = "SELECT GET_LOCK(CONCAT('nowo_plk_migrate_', SHA1(CONCAT(DATABASE(), ':', ?))), 0)";
        $mysqlRelease = "SELECT RELEASE_LOCK(CONCAT('nowo_plk_migrate_', SHA1(CONCAT(DATABASE(), ':', ?))))";

        yield 'mysql' => [new MySQLPlatform(), $mysqlAcquire, $mysqlRelease];
        yield 'mariadb' => [new MariaDBPlatform(), $mysqlAcquire, $mysqlRelease];
        yield 'postgres' => [new PostgreSQLPlatform(), 'SELECT pg_try_advisory_lock(hashtext(?))', 'SELECT pg_advisory_unlock(hashtext(?))'];
    }

    #[DataProvider('platforms')]
    public function testANamedLockPerDatabaseAndLayoutTableIsTakenWithoutWaitingAndReleased(AbstractPlatform $platform, string $acquire, string $release): void
    {
        $name       = PageMigrationLock::NAME_PREFIX . 'p_content_page_layout_entry';
        $results    = [1, 0];
        $sql        = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('fetchOne')->willReturnCallback(static function (string $query, array $params) use (&$results, &$sql, $name): mixed {
            self::assertSame([$name], $params);
            $sql[] = $query;

            return $query === 'SELECT pg_try_advisory_lock(hashtext(?))' ? (bool) array_shift($results) : array_shift($results);
        });

        $lock = new PageMigrationLock();
        self::assertTrue($lock->acquire($connection, 'p_content_page_layout_entry'));
        self::assertFalse($lock->acquire($connection, 'p_content_page_layout_entry'), 'held by another session');
        $lock->release($connection, 'p_content_page_layout_entry');

        self::assertSame([$acquire, $acquire, $release], $sql);
    }

    public function testOtherPlatformsTakeNoLock(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new SQLitePlatform());
        $connection->expects(self::never())->method('fetchOne');

        $lock = new PageMigrationLock();
        self::assertTrue($lock->acquire($connection, 't'));
        $lock->release($connection, 't');
    }

    public function testAFailedReleaseIsLoggedNotThrown(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $connection->method('fetchOne')->willThrowException(new RuntimeException('gone'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(self::stringContains('could not be released'), ['bundle' => 'NowoPageLayoutKitBundle', 'exception' => RuntimeException::class]);

        new PageMigrationLock($logger)->release($connection, 't');
    }
}
