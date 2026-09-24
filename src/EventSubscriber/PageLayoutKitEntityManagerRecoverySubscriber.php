<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resets the page layout entity manager at the start of a main request when a previous request
 * closed it after a failed flush, so long-running workers without `services_resetter` recover.
 *
 * It never clears an open entity manager: detaching application entities stays the host's job.
 */
final readonly class PageLayoutKitEntityManagerRecoverySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4096],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $manager = $this->managerRegistry->getManagerForClass(PageLayoutEntry::class);

        if (!$manager instanceof EntityManagerInterface || $manager->isOpen()) {
            return;
        }

        foreach (array_keys($this->managerRegistry->getManagerNames()) as $name) {
            if ($this->managerRegistry->getManager($name) === $manager) {
                $this->managerRegistry->resetManager($name);

                return;
            }
        }
    }
}
