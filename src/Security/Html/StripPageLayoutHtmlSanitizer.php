<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Security\Html;

use const ENT_HTML5;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Strips every HTML tag (`strip` strategy) and returns HTML-safe text.
 *
 * Block bodies are rendered with `|raw`, so the result must never contain live markup: after `strip_tags()` the text
 * is escaped (existing entities are kept as entities, never decoded). Decoding after stripping would turn
 * `&lt;img onerror=…&gt;` back into a tag. The output is idempotent, so render-time re-sanitizing is stable.
 */
final class StripPageLayoutHtmlSanitizer implements PageLayoutHtmlSanitizerInterface
{
    public function sanitize(string $html): string
    {
        return trim(htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false));
    }
}
