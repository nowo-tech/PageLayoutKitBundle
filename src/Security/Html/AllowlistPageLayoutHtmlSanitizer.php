<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Security\Html;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Allowlist sanitizer for CMS block bodies (`allowlist` strategy).
 *
 * Built on symfony/html-sanitizer, which parses with the HTML5 parser (the browser's tree, not libxml's HTML4 one),
 * keeps only element and text nodes (comments, processing instructions and CDATA are dropped) and re-serializes
 * every text node and attribute value encoded. Common structural / phrasing elements outside the allowlist are
 * unwrapped (their sanitized children are kept); every other element (raw text, script-capable, foreign content,
 * document-level, custom elements) is dropped with its content.
 */
final readonly class AllowlistPageLayoutHtmlSanitizer implements PageLayoutHtmlSanitizerInterface
{
    /** @var array<string, list<string>> */
    public const array ALLOWED_ELEMENTS = [
        'p'          => ['class'],
        'br'         => [],
        'strong'     => ['class'],
        'b'          => ['class'],
        'em'         => ['class'],
        'i'          => ['class'],
        'u'          => ['class'],
        's'          => ['class'],
        'del'        => ['class'],
        'h2'         => ['class'],
        'h3'         => ['class'],
        'h4'         => ['class'],
        'ul'         => ['class'],
        'ol'         => ['class'],
        'li'         => ['class'],
        'blockquote' => ['class'],
        'code'       => ['class'],
        'pre'        => ['class'],
        'a'          => ['href', 'title', 'target', 'rel', 'class'],
        'img'        => ['src', 'alt', 'title', 'width', 'height', 'loading', 'class'],
        'table'      => ['class'],
        'thead'      => ['class'],
        'tbody'      => ['class'],
        'tr'         => ['class'],
        'th'         => ['class', 'colspan', 'rowspan'],
        'td'         => ['class', 'colspan', 'rowspan'],
        'hr'         => ['class'],
        'span'       => ['class'],
        'div'        => ['class'],
        'figure'     => ['class'],
        'figcaption' => ['class'],
        'iframe'     => ['src', 'title', 'allow', 'allowfullscreen', 'frameborder', 'width', 'height', 'class'],
    ];

    /**
     * Elements outside the allowlist that are unwrapped: the element goes, its sanitized children stay.
     * Any element in neither list is dropped together with its content.
     *
     * @var list<string>
     */
    public const array UNWRAPPED_ELEMENTS = [
        'abbr', 'acronym', 'address', 'article', 'aside', 'audio', 'bdi', 'bdo', 'big', 'body', 'caption', 'center',
        'cite', 'colgroup', 'data', 'dd', 'details', 'dfn', 'dialog', 'dir', 'dl', 'dt', 'fieldset', 'font', 'footer',
        'h1', 'h5', 'h6', 'header', 'hgroup', 'html', 'ins', 'kbd', 'label', 'legend', 'listing', 'main', 'map', 'mark',
        'marquee', 'menu', 'meter', 'nav', 'nobr', 'output', 'picture', 'progress', 'q', 'rb', 'rp', 'rt', 'rtc', 'ruby',
        'samp', 'search', 'section', 'small', 'strike', 'sub', 'summary', 'sup', 'tfoot', 'time', 'tt', 'var', 'video',
    ];

    private HtmlSanitizerInterface $sanitizer;

    public function __construct()
    {
        $config = new HtmlSanitizerConfig()
            ->defaultAction(HtmlSanitizerAction::Drop)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withAttributeSanitizer(new PageLayoutUrlAttributeSanitizer())
            ->withMaxInputLength(-1);

        foreach (self::ALLOWED_ELEMENTS as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        foreach (self::UNWRAPPED_ELEMENTS as $element) {
            $config = $config->blockElement($element);
        }

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        // An embed whose src was refused (scheme, host) is removed entirely rather than left as an empty frame.
        // The input is the sanitizer's own canonical output: attribute values and text are entity-encoded, so a
        // literal `>` or `"` can only delimit markup.
        return trim((string) preg_replace_callback(
            '#<iframe\b([^>]*)>(.*?)</iframe>#s',
            static fn (array $match): string => str_contains($match[1], ' src="') ? $match[0] : '',
            $this->sanitizer->sanitize($html),
        ));
    }
}
