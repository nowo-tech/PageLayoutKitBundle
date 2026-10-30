<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Security\Html;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

use function in_array;
use function is_string;

use const PHP_URL_HOST;

/**
 * Bundle URL policy on top of symfony/html-sanitizer's URL checks (which run first):
 *
 * - `a[href]`: `http(s):`, `mailto:` or a root-relative path (`/…`, never protocol-relative `//…`);
 * - `img[src]`: `http(s)://` or a root-relative path;
 * - `iframe[src]`: `https://` on an allowlisted video host only;
 * - `iframe[allow]`: only the permissions an embedded player needs, without origin lists.
 *
 * @internal
 */
final readonly class PageLayoutUrlAttributeSanitizer implements AttributeSanitizerInterface
{
    /** @var list<string> */
    public const array ALLOWED_EMBED_HOSTS = [
        'www.youtube.com',
        'youtube.com',
        'www.youtube-nocookie.com',
        'player.vimeo.com',
    ];

    /** @var list<string> */
    public const array ALLOWED_IFRAME_PERMISSIONS = [
        'accelerometer',
        'autoplay',
        'clipboard-write',
        'encrypted-media',
        'fullscreen',
        'gyroscope',
        'picture-in-picture',
        'web-share',
    ];

    public function getSupportedElements(): array
    {
        return ['a', 'img', 'iframe'];
    }

    public function getSupportedAttributes(): array
    {
        return ['href', 'src', 'allow'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $value = trim($value);

        if ($attribute !== 'allow' && $this->isAmbiguousUrl($value)) {
            return null;
        }

        return match (true) {
            $element === 'a' && $attribute === 'href'       => $this->isRootRelative($value) || preg_match('#^(https?|mailto):#i', $value) === 1 ? $value : null,
            $element === 'img' && $attribute === 'src'      => $this->isRootRelative($value) || preg_match('#^https?://#i', $value) === 1 ? $value : null,
            $element === 'iframe' && $attribute === 'src'   => $this->isAllowedEmbed($value) ? $value : null,
            $element === 'iframe' && $attribute === 'allow' => $this->allowedPermissions($value),
            default                                         => $value,
        };
    }

    /**
     * Refuses URLs that parsers disagree on, whatever symfony/html-sanitizer version is installed (CVE-2026-45066):
     * a backslash (browsers read `\` as `/`, so `https://evil.tld\@www.youtube.com/` loads evil.tld while
     * `parse_url()` sees youtube, and `/\evil.tld` is protocol-relative), ASCII control characters or whitespace,
     * and userinfo (`@`) in the authority of a hierarchical URL.
     */
    private function isAmbiguousUrl(string $url): bool
    {
        if (preg_match('#[\\\\\x00-\x20\x7f]#', $url) === 1) {
            return true;
        }

        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//([^/?\#]*)#i', $url, $match) === 1) {
            return str_contains($match[1], '@');
        }

        return false;
    }

    private function isRootRelative(string $url): bool
    {
        return str_starts_with($url, '/') && !str_starts_with($url, '//');
    }

    private function isAllowedEmbed(string $url): bool
    {
        if (preg_match('#^https://#i', $url) !== 1) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && in_array(strtolower($host), self::ALLOWED_EMBED_HOSTS, true);
    }

    private function allowedPermissions(string $allow): ?string
    {
        $kept = [];

        foreach (explode(';', $allow) as $directive) {
            // Keep the feature name only: an origin list (`autoplay *`) is dropped, the default is the frame's own origin.
            $feature = strtolower(preg_split('/\s+/', trim($directive))[0] ?? '');

            if (in_array($feature, self::ALLOWED_IFRAME_PERMISSIONS, true) && !in_array($feature, $kept, true)) {
                $kept[] = $feature;
            }
        }

        return $kept === [] ? null : implode('; ', $kept);
    }
}
