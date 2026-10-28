<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\EventSubscriber;

use Nowo\PageLayoutKitBundle\Security\PageLayoutKitAccessCheckerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use function is_array;
use function is_object;
use function is_string;
use function str_starts_with;

/**
 * Enforces page layout admin access on the bundle's admin controllers (by class) and on its route-name prefixes.
 */
final readonly class PageLayoutKitAdminAccessSubscriber implements EventSubscriberInterface
{
    private const ADMIN_CONTROLLER_NAMESPACE = 'Nowo\\PageLayoutKitBundle\\Controller\\Admin\\';

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
        if (!$this->isAdminRequest($event)) {
            return;
        }

        if (!$this->accessChecker->canAccess()) {
            throw new AccessDeniedException('Page layout admin requires an authorized user.');
        }
    }

    /**
     * The bundle's admin controllers are guarded by class, whatever the route names are: a host that imports the
     * routes with a `name_prefix` (or names its own routes for these controllers) must not bypass the check. The
     * route-name prefixes still cover custom host controllers that reuse the bundle's route names.
     */
    private function isAdminRequest(ControllerEvent $event): bool
    {
        $controller = $event->getController();
        $object     = is_array($controller) ? $controller[0] : $controller;
        if (is_object($object) && str_starts_with($object::class, self::ADMIN_CONTROLLER_NAMESPACE)) {
            return true;
        }

        $route = $event->getRequest()->attributes->get('_route');

        return is_string($route)
            && (str_starts_with($route, 'admin_page_layout') || str_starts_with($route, 'admin_page_blocks'));
    }
}
