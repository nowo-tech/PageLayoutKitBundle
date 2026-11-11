<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Persistence;

/**
 * Write events (`Nowo\PageLayoutKitBundle\Event\*`) recorded by the bundle and not dispatched yet (2.2).
 *
 * Events are dispatched only once the write is committed: right after the bundle's flush, or, when the host wrapped
 * the call in its own transaction, on the next `postFlush` outside a transaction or at the end of the request /
 * Messenger message / console command. A host that commits in the middle of a long process can call
 * {@see dispatchPending()} right after its commit.
 */
interface PageLayoutPendingEventsInterface
{
    /**
     * Dispatches the recorded events when the bundle's connection has no open transaction (the write is committed).
     *
     * @return int The number of events dispatched (0 while a transaction is still open or nothing is pending)
     */
    public function dispatchPending(): int;

    /**
     * Number of recorded events not dispatched yet.
     */
    public function countPending(): int;
}
