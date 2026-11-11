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

use function array_values;
use function count;

/**
 * Unit of work boundary of the bundle's own writes (2.2, architecture bar rules 6 and 7).
 *
 * The bundle only writes from its own admin UI and its migration command, so it owns those units of work: it flushes
 * the entity manager that maps the bundle entities (`doctrine.entity_manager`, else the manager returned by
 * `getManagerForClass()`), never the autowired default one by accident, and then dispatches the ID-only write events.
 * Events are never dispatched inside a flush nor while a transaction is open (a host transaction around the call
 * delays them to the next `postFlush` outside a transaction or to the end of the request / message / command). The
 * recorded events are request state: dispatched or discarded at every boundary and on `kernel.reset` (FrankenPHP
 * worker mode without kernel reset).
 *
 * @internal Use {@see PageLayoutPendingEventsInterface}
 */
#[AsDoctrineListener(event: Events::postFlush)]
final class PageLayoutTransaction implements PageLayoutPendingEventsInterface, EventSubscriberInterface, ResetInterface
{
    /**
     * Events of flushed writes, dispatched once the connection has no open transaction.
     *
     * @var list<object>
     */
    // @igor-ignore - Request-scoped outbox: dispatched or discarded at every request/message/command boundary and reset().
    private array $pending = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION  => ['discardPending', -1024],
            KernelEvents::TERMINATE  => ['onBoundary', 1024],
            ConsoleEvents::ERROR     => ['discardPending', -1024],
            ConsoleEvents::TERMINATE => ['onBoundary', 1024],
            // symfony/messenger is optional: event names as strings.
            'Symfony\Component\Messenger\Event\WorkerMessageHandledEvent' => ['onBoundary', 1024],
            'Symfony\Component\Messenger\Event\WorkerMessageFailedEvent'  => ['discardPending', -1024],
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
        $this->pending = [...$this->pending, ...array_values($events)];
        $this->dispatchPending();
    }

    public function dispatchPending(): int
    {
        if ($this->pending === [] || !$this->entityManager->isOpen()) {
            return 0;
        }

        if ($this->entityManager->getConnection()->isTransactionActive()) {
            return 0;
        }

        $events        = $this->pending;
        $this->pending = [];
        foreach ($events as $event) {
            $this->eventDispatcher?->dispatch($event);
        }

        return count($events);
    }

    public function countPending(): int
    {
        return count($this->pending);
    }

    /**
     * Doctrine `postFlush` (any manager): the events wait no longer once no transaction is open.
     */
    public function postFlush(): void
    {
        $this->dispatchPending();
    }

    /**
     * End of a request, message or command: dispatches what was committed, discards the rest.
     */
    public function onBoundary(): void
    {
        if ($this->pending === []) {
            return;
        }

        try {
            $this->dispatchPending();
        } finally {
            $this->discardPending();
        }
    }

    /**
     * Failed request, message or command: the recorded writes may have been rolled back.
     */
    public function discardPending(): void
    {
        $count = count($this->pending);
        if ($count === 0) {
            return;
        }

        $this->logger->warning('Page layout write events discarded: the write was not committed before the end of the request, message or command.', [
            'bundle' => 'NowoPageLayoutKitBundle',
            'events' => $count,
        ]);
        $this->reset();
    }

    public function reset(): void
    {
        $this->pending = [];
    }
}
