<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps the bundle's OWN entity manager (`doctrine.entity_manager`) usable in a kept-alive kernel.
 *
 * 2.2 (rule 6): only that manager is touched. It maps the bundle entities only, so the bundle resets it at the start
 * of the next main request or Messenger message when a failed write closed it, and clears it at the end of each
 * request, message and command so no managed block survives into the next one. Without `doctrine.entity_manager`
 * the bundle entities live on a host manager and this subscriber does nothing: resetting or clearing the host's
 * managers is the host's job (DoctrineBundle's `kernel.reset`). Before 2.2 it reset whatever manager mapped
 * `PageLayoutEntry`, i.e. the host default one.
 */
final readonly class PageLayoutKitEntityManagerRecoverySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ?string $managerName = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4096],
            // symfony/messenger is optional: event names as strings.
            'Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent' => ['recover', 4096],
            KernelEvents::TERMINATE                                        => ['clear', -1024],
            ConsoleEvents::TERMINATE                                       => ['clear', -1024],
            'Symfony\Component\Messenger\Event\WorkerMessageHandledEvent'  => ['clear', -1024],
            'Symfony\Component\Messenger\Event\WorkerMessageFailedEvent'   => ['clear', -1024],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->recover();
        }
    }

    /**
     * Resets the bundle manager when a failed write closed it (the lazy manager is re-created in place).
     */
    public function recover(): void
    {
        if ($this->managerName === null) {
            return;
        }

        $manager = $this->managerRegistry->getManager($this->managerName);
        if ($manager instanceof EntityManagerInterface && !$manager->isOpen()) {
            $this->managerRegistry->resetManager($this->managerName);
        }
    }

    /**
     * Detaches every managed bundle entity at the end of the request, message or command.
     */
    public function clear(): void
    {
        if ($this->managerName === null) {
            return;
        }

        $manager = $this->managerRegistry->getManager($this->managerName);
        if (!$manager instanceof EntityManagerInterface || $manager->isOpen()) {
            // @igor-ignore - This IS the request-boundary reset of the bundle's own manager (no state kept).
            $manager->clear();
        }
    }
}
