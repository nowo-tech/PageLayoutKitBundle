<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\EventSubscriber;

use Nowo\PageLayoutKitBundle\EventSubscriber\PageLayoutKitAdminAccessSubscriber;
use Nowo\PageLayoutKitBundle\Security\PageLayoutKitAccessCheckerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PageLayoutKitAdminAccessSubscriberTest extends TestCase
{
    public function testSubscribedEventsPointToKernelController(): void
    {
        self::assertSame(
            ['kernel.controller' => ['onKernelController', 0]],
            PageLayoutKitAdminAccessSubscriber::getSubscribedEvents(),
        );
    }

    public function testIgnoresRoutesOutsideBundleAdminArea(): void
    {
        $checker = $this->createMock(PageLayoutKitAccessCheckerInterface::class);
        $checker->expects(self::never())->method('canAccess');

        $subscriber = new PageLayoutKitAdminAccessSubscriber($checker);
        $subscriber->onKernelController($this->createEvent('app_home'));

        self::assertTrue(true);
    }

    public function testIgnoresNonStringRoutes(): void
    {
        $checker = $this->createMock(PageLayoutKitAccessCheckerInterface::class);
        $checker->expects(self::never())->method('canAccess');

        $subscriber = new PageLayoutKitAdminAccessSubscriber($checker);
        $subscriber->onKernelController($this->createEvent(123));

        self::assertTrue(true);
    }

    public function testAllowsAuthorizedAdminRoutes(): void
    {
        $checker = $this->createMock(PageLayoutKitAccessCheckerInterface::class);
        $checker->expects(self::once())->method('canAccess')->willReturn(true);

        $subscriber = new PageLayoutKitAdminAccessSubscriber($checker);
        $subscriber->onKernelController($this->createEvent('admin_page_layout_index'));

        self::assertTrue(true);
    }

    public function testDeniesUnauthorizedAdminRoutes(): void
    {
        $checker = $this->createMock(PageLayoutKitAccessCheckerInterface::class);
        $checker->expects(self::once())->method('canAccess')->willReturn(false);

        $subscriber = new PageLayoutKitAdminAccessSubscriber($checker);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Page layout admin requires an authorized user.');

        $subscriber->onKernelController($this->createEvent('admin_page_blocks_edit'));
    }

    private function createEvent(string|int|null $route): ControllerEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn (): null => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
