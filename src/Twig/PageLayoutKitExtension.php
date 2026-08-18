<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Twig;

use Nowo\PageLayoutKitBundle\Security\PageLayoutKitAccessCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Twig globals for page layout kit admin + CMS pencils.
 */
final class PageLayoutKitExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @param list<string> $pages
     */
    public function __construct(
        private readonly string $layoutTemplate,
        private readonly string $cssFramework,
        private readonly array $pages,
        private readonly string $defaultLocale,
        private readonly PageLayoutKitAccessCheckerInterface $accessChecker,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'nowo_page_layout_kit_layout'         => $this->layoutTemplate,
            'nowo_page_layout_kit_css_framework'  => $this->cssFramework,
            'nowo_page_layout_kit_pages'          => $this->pages,
            'nowo_page_layout_kit_default_locale' => $this->defaultLocale,
            'nowo_page_layout_kit_can_edit'       => $this->accessChecker->canAccess(),
        ];
    }
}
