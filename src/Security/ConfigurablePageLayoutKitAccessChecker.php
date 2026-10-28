<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Security;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Role-based access driven by nowo_page_layout_kit.security.access_roles.
 *
 * An empty role list grants nothing (fail closed); the extension refuses to compile that configuration.
 */
final readonly class ConfigurablePageLayoutKitAccessChecker implements PageLayoutKitAccessCheckerInterface
{
    /**
     * @param list<string> $accessRoles
     */
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private array $accessRoles,
    ) {
    }

    public function canAccess(): bool
    {
        return array_any($this->accessRoles, fn (string $role): bool => $this->authorizationChecker->isGranted($role));
    }
}
