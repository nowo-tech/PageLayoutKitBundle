<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\EventSubscriber\PageLayoutKitEntityManagerRecoverySubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * 2.2 (rule 6): only the bundle's own manager (`doctrine.entity_manager`) is ever reset or cleared.
 */
#[CoversClass(PageLayoutKitEntityManagerRecoverySubscriber::class)]
final class PageLayoutKitEntityManagerRecoverySubscriberTest extends TestCase
{
    #[Test]
    public function subscribesToRequestAndBoundaries(): void
    {
        $events = PageLayoutKitEntityManagerRecoverySubscriber::getSubscribedEvents();
        self::assertSame(['onKernelRequest', 4096], $events[KernelEvents::REQUEST]);
        self::assertSame(['clear', -1024], $events[KernelEvents::TERMINATE]);
        self::assertCount(6, $events);
    }

    #[Test]
    public function neverTouchesAHostManager(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManager');
        $registry->expects(self::never())->method('resetManager');

        $subscriber = new PageLayoutKitEntityManagerRecoverySubscriber($registry);
        $subscriber->onKernelRequest($this->event(HttpKernelInterface::MAIN_REQUEST));
        $subscriber->recover();
        $subscriber->clear();
    }

    #[Test]
    public function resetsTheClosedOwnManagerOnTheMainRequestOnly(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('isOpen')->willReturnOnConsecutiveCalls(false, true);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::exactly(2))->method('getManager')->with('page_layout')->willReturn($em);
        $registry->expects(self::once())->method('resetManager')->with('page_layout');

        $subscriber = new PageLayoutKitEntityManagerRecoverySubscriber($registry, 'page_layout');
        $subscriber->onKernelRequest($this->event(HttpKernelInterface::SUB_REQUEST));
        $subscriber->onKernelRequest($this->event(HttpKernelInterface::MAIN_REQUEST));
        $subscriber->recover();
    }

    #[Test]
    public function clearsTheOpenOwnManagerOnly(): void
    {
        $open = $this->createMock(EntityManagerInterface::class);
        $open->method('isOpen')->willReturn(true);
        $open->expects(self::once())->method('clear');
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($open);
        new PageLayoutKitEntityManagerRecoverySubscriber($registry, 'page_layout')->clear();

        $closed = $this->createMock(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);
        $closed->expects(self::never())->method('clear');
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($closed);
        new PageLayoutKitEntityManagerRecoverySubscriber($registry, 'page_layout')->clear();
    }

    private function event(int $type): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create('/'), $type);
    }
}
