<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Security\Html;

/**
 * Leaves HTML unchanged (`none` strategy). Trusted editors only.
 */
final class NullPageLayoutHtmlSanitizer implements PageLayoutHtmlSanitizerInterface
{
    public function sanitize(string $html): string
    {
        return $html;
    }
}
