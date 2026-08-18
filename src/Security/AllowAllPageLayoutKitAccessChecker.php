<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Security;

final class AllowAllPageLayoutKitAccessChecker implements PageLayoutKitAccessCheckerInterface
{
    public function canAccess(): bool
    {
        return true;
    }
}
