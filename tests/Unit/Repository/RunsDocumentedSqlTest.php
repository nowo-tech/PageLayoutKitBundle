<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\PageLayoutKitBundle\Tests\Support\RunsDocumentedSqlProbe;
use PHPUnit\Framework\TestCase;

final class RunsDocumentedSqlTest extends TestCase
{
    public function testFetchHelpersDelegateToConnectionWhenSqlIsDocumented(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(self::stringContains('returns rows'), ['id' => 1])
            ->willReturn([2 => ['value' => 'row']]);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->with(self::stringContains('returns one scalar'), ['id' => 2])
            ->willReturn('scalar');
        $connection->expects(self::once())
            ->method('fetchAssociative')
            ->with(self::stringContains('returns one row'), ['id' => 3])
            ->willReturn(['value' => 'assoc']);

        $helper = $this->createHelper($connection);

        self::assertSame([['value' => 'row']], $helper->fetchAll("-- loads rows\n-- returns rows", ['id' => 1]));
        self::assertSame('scalar', $helper->fetchOne("-- loads scalar\n-- returns one scalar", ['id' => 2]));
        self::assertSame(['value' => 'assoc'], $helper->fetchAssociative("-- loads row\n-- returns one row", ['id' => 3]));
    }

    public function testFetchHelpersRejectUndocumentedSql(): void
    {
        $helper = $this->createHelper($this->createMock(Connection::class));

        $this->expectException(InvalidArgumentException::class);
        $helper->fetchAll('SELECT 1');
    }

    private function createHelper(Connection $connection): RunsDocumentedSqlProbe
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return new RunsDocumentedSqlProbe($entityManager);
    }
}
