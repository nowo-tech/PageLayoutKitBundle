<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Security;

use Nowo\PageLayoutKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageLayoutKitBundle\Security\Html\AllowlistPageLayoutHtmlSanitizer;
use Nowo\PageLayoutKitBundle\Security\Html\NullPageLayoutHtmlSanitizer;
use Nowo\PageLayoutKitBundle\Security\Html\PageLayoutHtmlSanitizerInterface;
use Nowo\PageLayoutKitBundle\Security\Html\StripPageLayoutHtmlSanitizer;

/**
 * Resolves YAML HTML sanitize strategy into a live sanitizer implementation.
 *
 * `strategy: service` without a sanitizer service is refused when the container compiles
 * (`NowoPageLayoutKitExtension`); an instance built by hand in that state fails closed to the allowlist sanitizer.
 */
final readonly class PageLayoutProtection
{
    public function __construct(
        private PageLayoutProtectionConfig $config,
        private ?PageLayoutHtmlSanitizerInterface $customSanitizer = null,
    ) {
    }

    public function htmlSanitizer(): PageLayoutHtmlSanitizerInterface
    {
        return match ($this->config->htmlSanitizeStrategy) {
            HtmlSanitizeStrategy::None      => new NullPageLayoutHtmlSanitizer(),
            HtmlSanitizeStrategy::Strip     => new StripPageLayoutHtmlSanitizer(),
            HtmlSanitizeStrategy::Allowlist => new AllowlistPageLayoutHtmlSanitizer(),
            HtmlSanitizeStrategy::Service   => $this->customSanitizer ?? new AllowlistPageLayoutHtmlSanitizer(),
        };
    }
}
