<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Security\Html;

/**
 * Sanitizes editor-authored block HTML before persist and public render.
 */
interface PageLayoutHtmlSanitizerInterface
{
    public function sanitize(string $html): string;
}
