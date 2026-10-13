<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Service;

use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Resolves the public URL of a layout page key for admin "view page" links and redirects.
 *
 * The route comes from `web_ui.page_routes` (pageKey => route name); unmapped keys try a route named
 * like the page key. Returns null when the host has no such route, so the admin never fails with a 500.
 */
final readonly class PublicPageUrlResolver
{
    /**
     * @param array<string, string> $pageRoutes
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private array $pageRoutes = [],
    ) {
    }

    public function resolve(string $pageKey): ?string
    {
        $route = $this->pageRoutes[$pageKey] ?? $pageKey;
        if ($route === '') {
            return null;
        }

        try {
            return $this->urlGenerator->generate($route);
        } catch (RoutingException) {
            return null;
        }
    }

    /**
     * First resolvable URL among the given page keys.
     *
     * @param list<string> $pageKeys
     */
    public function resolveFirst(array $pageKeys): ?string
    {
        foreach ($pageKeys as $pageKey) {
            $url = $this->resolve($pageKey);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }
}
