<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Persistence;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

use function count;

/**
 * Unit of work boundary of the bundle's own writes (2.2, architecture bar rules 6 and 7).
 *
 * The bundle only writes from its own admin UI and its migration command, so it owns those units of work: it flushes
 * the entity manager that maps the bundle entities (`doctrine.entity_manager`, else the manager returned by
 * `getManagerForClass()`), never the autowired default one by accident.
 *
 * The ID-only write events follow the **real** commit (LH-PLK-M1): once flushed they go to the {@see PageLayoutOutbox},
 * which {@see AfterCommitMiddleware} tells about the transactions of the bundle's connection. A rollback (also of a
 * host transaction around the call, or of a savepoint) drops them; the outermost commit makes them committed.
 * Committed events are dispatched at the next safe point where DBAL reports no open transaction: right after the
 * bundle flush, on the next `postFlush`, {@see dispatchPending()}, and at the end of the request / message / command
 * (also a failed one). Never inside a flush nor inside a transaction. Everything is dropped on `kernel.reset`
 * (FrankenPHP worker mode without kernel reset).
 *
 * @internal Use {@see PageLayoutPendingEventsInterface}
 */
#[AsDoctrineListener(event: Events::postFlush)]
final readonly class PageLayoutTransaction implements PageLayoutPendingEventsInterface, EventSubscriberInterface, ResetInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private LoggerInterface $logger = new NullLogger(),
        private PageLayoutOutbox $outbox = new PageLayoutOutbox(),
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION  => ['dispatchPending', -1024],
            KernelEvents::TERMINATE  => ['dispatchPending', 1024],
            ConsoleEvents::ERROR     => ['dispatchPending', -1024],
            ConsoleEvents::TERMINATE => ['dispatchPending', 1024],
            // symfony/messenger is optional: event names as strings.
            'Symfony\Component\Messenger\Event\WorkerMessageHandledEvent' => ['dispatchPending', 1024],
            'Symfony\Component\Messenger\Event\WorkerMessageFailedEvent'  => ['dispatchPending', -1024],
        ];
    }

    /**
     * Flushes the bundle entity manager and records the write events, dispatched once the write is committed.
     */
    public function commit(object ...$events): void
    {
        $this->entityManager->flush();
        $this->record(...$events);
    }

    /**
     * Records the events of writes the caller already flushed on the bundle entity manager.
     */
    public function record(object ...$events): void
    {
        $this->outbox->hold(...$events);
        $this->dispatchPending();
    }

    /**
     * Dispatches the events of committed writes when DBAL reports no open transaction on the bundle's connection.
     * A listener that throws cannot undo the commit: it is logged and the other events are still dispatched.
     */
    public function dispatchPending(): int
    {
        if ($this->outbox->countCommitted() === 0 || $this->entityManager->getConnection()->isTransactionActive()) {
            return 0;
        }

        $events = $this->outbox->takeCommitted();
        foreach ($events as $event) {
            try {
                $this->eventDispatcher?->dispatch($event);
            } catch (Throwable $e) {
                $this->logger->error('Page layout: a listener failed after the commit.', [
                    'bundle'    => 'NowoPageLayoutKitBundle',
                    'event'     => $event::class,
                    'exception' => $e::class,
                ]);
            }
        }

        return count($events);
    }

    public function countPending(): int
    {
        return $this->outbox->countHeld() + $this->outbox->countCommitted();
    }

    /**
     * Doctrine `postFlush` (any manager): committed events wait no longer once no transaction is open.
     */
    public function postFlush(): void
    {
        $this->dispatchPending();
    }

    public function reset(): void
    {
        $this->outbox->reset();
    }
}
