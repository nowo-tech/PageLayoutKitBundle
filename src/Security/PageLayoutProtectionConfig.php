<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Security;

use Nowo\PageLayoutKitBundle\Enum\HtmlSanitizeStrategy;

/**
 * YAML-backed defaults for CMS block HTML sanitizing.
 */
final readonly class PageLayoutProtectionConfig
{
    public function __construct(
        public HtmlSanitizeStrategy $htmlSanitizeStrategy,
        public ?string $htmlSanitizeService,
    ) {
    }
}
