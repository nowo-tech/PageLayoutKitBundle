<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\PageLayoutKitBundle\Event\PageBlockUpdatedEvent;
use Nowo\PageLayoutKitBundle\Persistence\PageLayoutOutbox;
use Nowo\PageLayoutKitBundle\Persistence\PageLayoutTransaction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(PageLayoutTransaction::class)]
final class PageLayoutTransactionTest extends TestCase
{
    /** @var list<object> */
    private array $dispatched = [];

    private bool $transactionActive = false;

    #[Test]
    public function flushesThenDispatchesAfterCommit(): void
    {
        $transaction = new PageLayoutTransaction($this->manager(flushes: true), $this->dispatcher());
        $transaction->commit($event = new PageBlockUpdatedEvent('hero', 1, 'en'));

        self::assertSame([$event], $this->dispatched);
        self::assertSame(0, $transaction->countPending());
    }

    #[Test]
    public function aHostTransactionDecidesCommitOrRollback(): void
    {
        $outbox      = new PageLayoutOutbox();
        $driver      = new stdClass();
        $transaction = new PageLayoutTransaction($this->manager(), $this->dispatcher(), outbox: $outbox);

        // Rolled back: dropped, also at later safe points (LH-PLK-M1).
        $outbox->transactionStarted($driver);
        $this->transactionActive = true;
        $transaction->record(new PageBlockUpdatedEvent('hero', 1, 'en'));
        $transaction->postFlush();
        self::assertSame(1, $transaction->countPending());
        $outbox->transactionEnded($driver, false);
        $this->transactionActive = false;
        $transaction->postFlush();
        self::assertSame([], $this->dispatched);
        self::assertSame(0, $transaction->countPending());

        // Committed: dispatched once DBAL reports no open transaction.
        $outbox->transactionStarted($driver);
        $this->transactionActive = true;
        $transaction->record($event = new PageBlockUpdatedEvent('hero', 2, 'en'));
        $outbox->transactionEnded($driver, true);
        self::assertSame(0, $transaction->dispatchPending(), 'DBAL still inside its commit');
        $this->transactionActive = false;
        self::assertSame(1, $transaction->dispatchPending());
        self::assertSame([$event], $this->dispatched);
    }

    #[Test]
    public function aThrowingListenerIsLoggedAndTheOtherEventsAreStillDispatched(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event): object {
            if ($event instanceof PageBlockUpdatedEvent && $event->blockId === 1) {
                throw new RuntimeException('listener');
            }
            $this->dispatched[] = $event;

            return $event;
        });
        $transaction = new PageLayoutTransaction($this->manager(), $dispatcher, $logger);

        $transaction->record(new PageBlockUpdatedEvent('hero', 1, 'en'), $ok = new PageBlockUpdatedEvent('hero', 2, 'en'));
        self::assertSame([$ok], $this->dispatched);
    }

    #[Test]
    public function resetKeepsNothing(): void
    {
        $this->transactionActive = true;
        $transaction             = new PageLayoutTransaction($this->manager());
        $transaction->record(new PageBlockUpdatedEvent('hero', 1, 'en'));
        self::assertSame(1, $transaction->countPending());

        $transaction->reset();
        self::assertSame(0, $transaction->countPending());

        $this->transactionActive = false;
        $transaction->record(new PageBlockUpdatedEvent('hero', 1, 'en'));
        self::assertSame(0, $transaction->countPending(), 'no dispatcher: dispatched to nobody');
    }

    #[Test]
    public function subscribesToEveryBoundary(): void
    {
        $events = PageLayoutTransaction::getSubscribedEvents();
        self::assertSame(['dispatchPending', 1024], $events[KernelEvents::TERMINATE]);
        self::assertSame(['dispatchPending', -1024], $events[KernelEvents::EXCEPTION], 'a committed write keeps its event');
        self::assertCount(6, $events);
    }

    private function manager(bool $flushes = false): EntityManagerInterface
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isTransactionActive')->willReturnCallback(fn (): bool => $this->transactionActive);
        if ($flushes) {
            $em = $this->createMock(EntityManagerInterface::class);
            $em->expects(self::once())->method('flush');
        } else {
            $em = $this->createStub(EntityManagerInterface::class);
        }
        $em->method('getConnection')->willReturn($connection);

        return $em;
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event): object {
            $this->dispatched[] = $event;

            return $event;
        });

        return $dispatcher;
    }
}
