<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\PageLayoutKitBundle\Event\PageBlockUpdatedEvent;
use Nowo\PageLayoutKitBundle\Persistence\PageLayoutTransaction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelEvents;

use function count;

#[CoversClass(PageLayoutTransaction::class)]
final class PageLayoutTransactionTest extends TestCase
{
    /** @var list<object> */
    private array $dispatched = [];

    private bool $transactionActive = false;

    private bool $open = true;

    #[Test]
    public function flushesThenDispatchesAfterCommit(): void
    {
        $transaction = new PageLayoutTransaction($this->manager(flushes: true), $this->dispatcher());
        $transaction->commit($event = new PageBlockUpdatedEvent('hero', 1, 'en'));

        self::assertSame([$event], $this->dispatched);
        self::assertSame(0, $transaction->countPending());
    }

    #[Test]
    public function waitsForAnOpenHostTransaction(): void
    {
        $this->transactionActive = true;
        $transaction             = new PageLayoutTransaction($this->manager(), $this->dispatcher());
        $transaction->record(new PageBlockUpdatedEvent('hero', 1, 'en'));
        $transaction->postFlush();

        self::assertSame([], $this->dispatched);
        self::assertSame(1, $transaction->countPending());

        $this->transactionActive = false;
        $transaction->postFlush();
        self::assertSame(1, $this->dispatchedCount());
        self::assertSame(0, $transaction->dispatchPending());
    }

    #[Test]
    public function boundaryDispatchesCommittedAndDiscardsTheRestWithAWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $transaction = new PageLayoutTransaction($this->manager(), $this->dispatcher(), $logger);

        $transaction->onBoundary();
        $transaction->discardPending();

        $this->transactionActive = true;
        $transaction->record(new PageBlockUpdatedEvent('hero', 1, 'en'));
        $transaction->onBoundary();

        self::assertSame([], $this->dispatched);
        self::assertSame(0, $transaction->countPending());

        $this->transactionActive = false;
        $transaction->record(new PageBlockUpdatedEvent('hero', 2, 'en'));
        $transaction->onBoundary();
        self::assertSame(1, $this->dispatchedCount());
    }

    #[Test]
    public function closedManagerAndResetKeepNothing(): void
    {
        $this->open  = false;
        $transaction = new PageLayoutTransaction($this->manager());
        $transaction->record(new PageBlockUpdatedEvent('hero', 1, 'en'));
        self::assertSame(1, $transaction->countPending());

        $transaction->reset();
        self::assertSame(0, $transaction->countPending());

        $this->open = true;
        $transaction->record(new PageBlockUpdatedEvent('hero', 1, 'en'));
        self::assertSame(0, $transaction->countPending(), 'no dispatcher: dispatched to nobody');
    }

    #[Test]
    public function subscribesToEveryBoundary(): void
    {
        $events = PageLayoutTransaction::getSubscribedEvents();
        self::assertSame(['onBoundary', 1024], $events[KernelEvents::TERMINATE]);
        self::assertSame(['discardPending', -1024], $events[KernelEvents::EXCEPTION]);
        self::assertCount(6, $events);
    }

    private function manager(bool $flushes = false): EntityManagerInterface
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isTransactionActive')->willReturnCallback(fn (): bool => $this->transactionActive);
        $em = $flushes ? $this->createMock(EntityManagerInterface::class) : $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->method('isOpen')->willReturnCallback(fn (): bool => $this->open);
        if ($em instanceof MockObject) {
            $em->expects(self::once())->method('flush');
        }

        return $em;
    }

    private function dispatchedCount(): int
    {
        return count($this->dispatched);
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
