<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PageLayoutKitBundle\Repository\Concerns\RunsDocumentedSql;

/**
 * Exposes the protected helpers of {@see RunsDocumentedSql} to tests.
 */
final readonly class RunsDocumentedSqlProbe
{
    use RunsDocumentedSql;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->fetchAllDocumentedSql($sql, $params);
    }

    /** @param array<string, mixed> $params */
    public function fetchOne(string $sql, array $params = []): mixed
    {
        return $this->fetchOneDocumentedSql($sql, $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>|false
     */
    public function fetchAssociative(string $sql, array $params = []): array|false
    {
        return $this->fetchAssociativeDocumentedSql($sql, $params);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}
