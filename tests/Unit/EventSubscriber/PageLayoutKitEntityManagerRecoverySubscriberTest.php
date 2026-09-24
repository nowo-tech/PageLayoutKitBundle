<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\EventSubscriber\PageLayoutKitEntityManagerRecoverySubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class PageLayoutKitEntityManagerRecoverySubscriberTest extends TestCase
{
    public function testSubscribesToMainRequestBeforeRoutingAndSecurity(): void
    {
        self::assertSame(
            ['kernel.request' => ['onKernelRequest', 4096]],
            PageLayoutKitEntityManagerRecoverySubscriber::getSubscribedEvents(),
        );
    }

    public function testManagerClosedByAFailedFlushInRequestOneIsResetBeforeRequestTwo(): void
    {
        $open    = true;
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('isOpen')->willReturnCallback(static function () use (&$open): bool {
            return $open;
        });

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::atLeastOnce())
            ->method('getManagerForClass')
            ->with(PageLayoutEntry::class)
            ->willReturn($manager);
        $registry->method('getManagerNames')->willReturn([
            'audit'   => 'doctrine.orm.audit_entity_manager',
            'content' => 'doctrine.orm.content_entity_manager',
        ]);
        $registry->method('getManager')->willReturnCallback(
            fn (?string $name): ObjectManager => $name === 'content' ? $manager : $this->createStub(ObjectManager::class),
        );
        $registry->expects(self::once())->method('resetManager')->with('content');

        $subscriber = new PageLayoutKitEntityManagerRecoverySubscriber($registry);

        $subscriber->onKernelRequest($this->createEvent());

        $open = false;

        $subscriber->onKernelRequest($this->createEvent(HttpKernelInterface::SUB_REQUEST));
        $subscriber->onKernelRequest($this->createEvent());
    }

    public function testUnknownOrUnnamedManagersAreLeftUntouched(): void
    {
        $closed = $this->createStub(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);

        foreach ([null, $closed] as $manager) {
            $registry = $this->createMock(ManagerRegistry::class);
            $registry->method('getManagerForClass')->willReturn($manager);
            $registry->method('getManagerNames')->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
            $registry->method('getManager')->willReturn($this->createStub(ObjectManager::class));
            $registry->expects(self::never())->method('resetManager');

            (new PageLayoutKitEntityManagerRecoverySubscriber($registry))->onKernelRequest($this->createEvent());
        }
    }

    private function createEvent(int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create('/'), $type);
    }
}
