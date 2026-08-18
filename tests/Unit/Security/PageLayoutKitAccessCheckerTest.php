<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Security;

use Nowo\PageLayoutKitBundle\Security\AllowAllPageLayoutKitAccessChecker;
use Nowo\PageLayoutKitBundle\Security\ConfigurablePageLayoutKitAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class PageLayoutKitAccessCheckerTest extends TestCase
{
    public function testAllowAllCheckerAlwaysReturnsTrue(): void
    {
        self::assertTrue((new AllowAllPageLayoutKitAccessChecker())->canAccess());
    }

    public function testConfigurableCheckerAllowsWhenNoRolesConfigured(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::never())->method('isGranted');

        $checker = new ConfigurablePageLayoutKitAccessChecker($authorizationChecker, []);

        self::assertTrue($checker->canAccess());
    }

    public function testConfigurableCheckerReturnsTrueWhenAnyRoleIsGranted(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::exactly(2))
            ->method('isGranted')
            ->willReturnCallback(static fn (string $role): bool => $role === 'ROLE_ADMIN');

        $checker = new ConfigurablePageLayoutKitAccessChecker(
            $authorizationChecker,
            ['ROLE_EDITOR', 'ROLE_ADMIN'],
        );

        self::assertTrue($checker->canAccess());
    }

    public function testConfigurableCheckerReturnsFalseWhenNoRolesMatch(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::exactly(2))
            ->method('isGranted')
            ->willReturn(false);

        $checker = new ConfigurablePageLayoutKitAccessChecker(
            $authorizationChecker,
            ['ROLE_EDITOR', 'ROLE_ADMIN'],
        );

        self::assertFalse($checker->canAccess());
    }
}
