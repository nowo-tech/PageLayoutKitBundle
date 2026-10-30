<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function dirname;

/**
 * Regression (Appendix BJ N2): every field is encoded exactly once in the public block templates — plain-text item
 * bodies through auto-escaping, sanitized HTML bodies through `|raw`.
 */
final class BlockTemplatesEscapingTest extends TestCase
{
    public function testCardItemBodiesAreEscapedOnce(): void
    {
        $html = $this->render('blocks/cards.html.twig', [
            'block' => ['sectionKey' => 'pain'],
            'data'  => ['title' => 'T', 'items' => [['title' => 'Card', 'body' => 'Don\'t "Q&A" <b>']]],
        ]);

        self::assertStringContainsString('<p class="card__text">Don&#039;t &quot;Q&amp;A&quot; &lt;b&gt;</p>', $html);
    }

    public function testSanitizedBodyIsPrintedRaw(): void
    {
        $html = $this->render('blocks/cards.html.twig', [
            'block' => ['sectionKey' => 'expect'],
            'data'  => ['title' => 'T', 'body' => '<p>Don&#039;t</p>', 'items' => []],
        ]);

        self::assertStringContainsString('<div class="text-muted legal-content"><p>Don&#039;t</p></div>', $html);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 3) . '/src/Resources/views', 'NowoPageLayoutKitBundle');

        return new Environment($loader, ['strict_variables' => true])->render('@NowoPageLayoutKitBundle/' . $template, $context);
    }
}
