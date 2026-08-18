<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Security;

interface PageLayoutKitAccessCheckerInterface
{
    public function canAccess(): bool;
}
