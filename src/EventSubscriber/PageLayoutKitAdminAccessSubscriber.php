<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\EventSubscriber;

use Nowo\PageLayoutKitBundle\Security\PageLayoutKitAccessCheckerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use function is_string;

/**
 * Enforces page layout admin access by route name prefix.
 */
final readonly class PageLayoutKitAdminAccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PageLayoutKitAccessCheckerInterface $accessChecker,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');
        if (!is_string($route)) {
            return;
        }

        if (
            str_starts_with($route, 'admin_page_layout')
            || str_starts_with($route, 'admin_page_blocks')
        ) {
            if (!$this->accessChecker->canAccess()) {
                throw new AccessDeniedException('Page layout admin requires an authorized user.');
            }
        }
    }
}
