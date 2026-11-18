<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * "Only one migration at a time" (2.3, REQ-RUNTIME-003 rule 4): a non-blocking, session-scoped named lock of the
 * bundle's connection, taken before the first page unit of a migration and released after the last one.
 *
 * Two migrations started at once on empty pages would otherwise both insert a layout (no row exists yet to lock):
 * the second one now fails fast with nothing changed instead of duplicating the pages.
 *
 * - MySQL / MariaDB: `GET_LOCK(name, 0)` / `RELEASE_LOCK(name)`; the name is per database and per layout table, so
 *   two applications sharing one server never block each other.
 * - PostgreSQL: `pg_try_advisory_lock(hashtext(name))` / `pg_advisory_unlock(...)` (advisory locks are per database).
 * - Other platforms (SQLite: writers are serialised by the database lock): no lock; each page unit still re-checks
 *   the page under its own transaction.
 *
 * Never waits (no lock wait to bound), and is released by the server if the connection dies. Stateless (worker-safe).
 *
 * Not final so tests can double it.
 *
 * @internal
 */
readonly class PageMigrationLock
{
    /** Prefix of the lock name; the layout table name (with the host's table prefix) follows. */
    public const string NAME_PREFIX = 'nowo_page_layout_kit_migrate:';

    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Takes the lock without waiting: false when another session holds it.
     */
    public function acquire(Connection $connection, string $layoutTable): bool
    {
        $platform = $connection->getDatabasePlatform();
        $name     = self::NAME_PREFIX . $layoutTable;

        if ($platform instanceof AbstractMySQLPlatform) {
            return (int) $connection->fetchOne(
                "SELECT GET_LOCK(CONCAT('nowo_plk_migrate_', SHA1(CONCAT(DATABASE(), ':', ?))), 0)",
                [$name],
            ) === 1;
        }

        if ($platform instanceof PostgreSQLPlatform) {
            return (bool) $connection->fetchOne('SELECT pg_try_advisory_lock(hashtext(?))', [$name]);
        }

        return true;
    }

    /**
     * Releases the lock. A failed release (lost connection) is logged and ignored: the server already dropped a
     * session lock with its connection, and the migration's own outcome (or failure) is what the caller reports.
     */
    public function release(Connection $connection, string $layoutTable): void
    {
        $platform = $connection->getDatabasePlatform();
        $name     = self::NAME_PREFIX . $layoutTable;

        try {
            if ($platform instanceof AbstractMySQLPlatform) {
                $connection->fetchOne("SELECT RELEASE_LOCK(CONCAT('nowo_plk_migrate_', SHA1(CONCAT(DATABASE(), ':', ?))))", [$name]);
            } elseif ($platform instanceof PostgreSQLPlatform) {
                $connection->fetchOne('SELECT pg_advisory_unlock(hashtext(?))', [$name]);
            }
        } catch (Throwable $e) {
            $this->logger->warning('Page layout: the migration lock could not be released; the server drops it with the connection.', [
                'bundle'    => 'NowoPageLayoutKitBundle',
                'exception' => $e::class,
            ]);
        }
    }
}
