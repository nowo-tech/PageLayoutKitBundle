<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Persistence;

use Nowo\PageLayoutKitBundle\Event\PageBlockUpdatedEvent;
use Nowo\PageLayoutKitBundle\Persistence\PageLayoutOutbox;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(PageLayoutOutbox::class)]
final class PageLayoutOutboxTest extends TestCase
{
    #[Test]
    public function withoutATransactionEventsAreCommittedAtOnce(): void
    {
        $outbox = new PageLayoutOutbox();
        $outbox->hold();
        $outbox->hold($event = new PageBlockUpdatedEvent('a', 1, 'en'));

        self::assertSame(1, $outbox->countCommitted());
        self::assertSame([$event], $outbox->takeCommitted());
        self::assertSame(0, $outbox->countCommitted());
    }

    #[Test]
    public function savepointsCommitIntoTheirParentOrDropTheirEvents(): void
    {
        $outbox = new PageLayoutOutbox();
        $driver = new stdClass();
        $outbox->savepointCreated($driver, 'IGNORED'); // no transaction: ignored

        $outbox->transactionStarted($driver);
        $outbox->hold($outer = new PageBlockUpdatedEvent('outer', 1, 'en'));
        $outbox->savepointCreated($driver, 'DOCTRINE_2');
        $outbox->hold($released = new PageBlockUpdatedEvent('released', 1, 'en'));
        $outbox->savepointReleased($driver, 'doctrine_2');
        $outbox->savepointCreated($driver, 'DOCTRINE_2');
        $outbox->hold(new PageBlockUpdatedEvent('rolled-back', 1, 'en'));
        $outbox->savepointCreated($driver, 'DOCTRINE_3');
        $outbox->hold(new PageBlockUpdatedEvent('inner-of-rolled-back', 1, 'en'));
        $outbox->savepointRolledBack($driver, 'DOCTRINE_2');
        $outbox->savepointRolledBack($driver, 'UNKNOWN');
        self::assertSame(2, $outbox->countHeld());

        $outbox->transactionEnded($driver, true);
        self::assertSame(0, $outbox->countHeld());
        self::assertSame([$outer, $released], $outbox->takeCommitted());
    }

    #[Test]
    public function aReusedSavepointNameClosesTheOpenOneFirst(): void
    {
        $outbox = new PageLayoutOutbox();
        $driver = new stdClass();
        $outbox->transactionStarted($driver);
        $outbox->savepointCreated($driver, 'SP');
        $outbox->hold($first = new PageBlockUpdatedEvent('first', 1, 'en'));
        $outbox->savepointCreated($driver, 'SP');
        $outbox->hold(new PageBlockUpdatedEvent('second', 1, 'en'));
        $outbox->savepointRolledBack($driver, 'SP');
        $outbox->transactionEnded($driver, true);

        self::assertSame([$first], $outbox->takeCommitted());
    }

    #[Test]
    public function rollbackAndResetDropEverything(): void
    {
        $outbox = new PageLayoutOutbox();
        $driver = new stdClass();
        $outbox->transactionStarted($driver);
        $outbox->hold(new PageBlockUpdatedEvent('a', 1, 'en'));
        $outbox->transactionEnded($driver, false);
        self::assertSame([], $outbox->takeCommitted());

        $outbox->hold(new PageBlockUpdatedEvent('b', 1, 'en'));
        $outbox->transactionStarted($driver);
        $outbox->hold(new PageBlockUpdatedEvent('c', 1, 'en'));
        $outbox->reset();
        self::assertSame(0, $outbox->countCommitted() + $outbox->countHeld());
        $outbox->transactionEnded($driver, true);
        self::assertSame([], $outbox->takeCommitted());
    }
}
