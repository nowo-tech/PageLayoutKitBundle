<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Twig;

use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * The block edit stub renders inside the configured admin layout with translated strings only.
 */
final class EditStubTemplateTest extends TestCase
{
    public function testStubIsTranslatedAndUsesTheResolvedLinks(): void
    {
        $html = $this->render('de', [
            'back_url'      => '/admin/pages/contact/layout',
            'view_page_url' => '/kontakt',
        ]);

        self::assertStringContainsString('LAYOUT[Block bearbeiten]', $html);
        self::assertStringContainsString('Die vollständige Bearbeitung von cards-Blöcken ist bald verfügbar.', $html);
        self::assertStringContainsString('href="/admin/pages/contact/layout"', $html);
        self::assertStringContainsString('Zurück zum Layout', $html);
        self::assertStringContainsString('href="/kontakt"', $html);
        self::assertStringNotContainsString('próximamente', $html);
        self::assertStringNotContainsString('Volver al layout', $html);
    }

    public function testStubOmitsLinksThatCouldNotBeResolved(): void
    {
        $html = $this->render('en', ['back_url' => null, 'view_page_url' => null]);

        self::assertStringContainsString('Full editing of cards blocks is coming soon.', $html);
        self::assertStringNotContainsString('href=', $html);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $locale, array $context): string
    {
        $views = new FilesystemLoader();
        $views->addPath(__DIR__ . '/../../../src/Resources/views', 'NowoPageLayoutKitBundle');
        $views->addPath(__DIR__ . '/../../../vendor/nowo-tech/ui-kit-bundle/src/Resources/views', 'NowoUiKitBundle');

        $twig = new Environment(new ChainLoader([
            new ArrayLoader(['host_layout.html.twig' => 'LAYOUT[{% block page_title %}{% endblock %}]{% block admin %}{% endblock %}']),
            $views,
        ]), ['strict_variables' => true]);

        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource(
            'yaml',
            __DIR__ . '/../../../src/Resources/translations/NowoPageLayoutKitBundle.' . $locale . '.yaml',
            $locale,
            'NowoPageLayoutKitBundle',
        );
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addGlobal('nowo_page_layout_kit_layout', 'host_layout.html.twig');
        $twig->addGlobal('nowo_page_layout_kit_css_framework', 'tailwind');

        return $twig->render('@NowoPageLayoutKitBundle/admin/blocks/edit_stub.html.twig', $context + [
            'page_title' => $translator->trans('admin.blocks.edit.title', [], 'NowoPageLayoutKitBundle'),
            'block_type' => PageBlockType::Cards,
            'block_id'   => 3,
        ]);
    }
}
