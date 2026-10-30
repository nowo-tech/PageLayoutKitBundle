<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Integration\Contract;

use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Security\PageLayoutKitAccessCheckerInterface;
use Nowo\PageLayoutKitBundle\Tests\Support\PageFixtures;
use Nowo\PageLayoutKitBundle\Twig\PageLayoutKitExtension;
use Nowo\PageLayoutKitBundle\Ui\PageLayoutKitPages;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Twig\Environment;

/**
 * REQ-UI-003 page contract: the admin pages extend UiKit scaffolds (reorder screen: form, block page: show); no
 * <html>, CDN, inline handler or confirm(); framework classes only through theme slots; the stub theme is reflected;
 * no raw or missing translation keys (strict rule). Also renders the reorder table, the deprecated 2.0 layout and
 * bridge (BC for 2.0 page copies and 2.0 host layouts) and the inline CMS modal shell.
 */
final class PageContractTest extends PageContractTestCase
{
    use PageLayoutKitContractEnvironment;

    /** A copy of a 2.0 page: extends the deprecated layout global and fills `page_title` / `admin`. */
    private const string PAGE_20 = '{% extends nowo_page_layout_kit_layout %}{% block page_title %}Old title{% endblock %}{% block admin %}<p id="old-content">Old content</p>{% endblock %}';

    /** A 2.0 host layout (renders `{% block admin %}`, no canonical blocks). */
    private const string HOST_20 = '<div id="host20"><title>{% block title %}{% endblock %}</title>{% block stylesheets %}<link id="host-css">{% endblock %}<h1 id="host-title">{% block page_title %}{% endblock %}</h1>{% block admin %}{% endblock %}{% block javascripts %}<script id="host-js"></script>{% endblock %}</div>';

    /** A copy of the 2.0 bundle layout: renders `admin` and the canonical flashes / content blocks itself. */
    private const string HOST_20_COPY = '<div id="host20copy"><h1>{% block page_title %}{% endblock %}</h1><div id="host-flashes">{% block nowo_ui_flashes %}{% endblock %}</div>{% block admin %}{% endblock %}<div id="host-content">{% block nowo_ui_content %}{% endblock %}</div></div>';

    public static function pageFixtures(): iterable
    {
        yield 'layout' => new PageFixture('@NowoPageLayoutKitBundle/admin/layout/index.html.twig', PageFixtures::layoutIndex());
        yield 'layout (no public route)' => new PageFixture('@NowoPageLayoutKitBundle/admin/layout/index.html.twig', PageFixtures::layoutIndex(false, null));
        yield 'layout (empty)' => new PageFixture('@NowoPageLayoutKitBundle/admin/layout/index.html.twig', PageFixtures::layoutIndex(true));
        yield 'block' => new PageFixture('@NowoPageLayoutKitBundle/admin/blocks/edit_stub.html.twig', PageFixtures::blockEdit());
        yield 'block (not on a page)' => new PageFixture('@NowoPageLayoutKitBundle/admin/blocks/edit_stub.html.twig', PageFixtures::blockEdit(true));
    }

    public function testTheReorderScreenRendersOnePositionFieldAndEditLinkPerEntry(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoPageLayoutKitBundle/admin/layout/index.html.twig', PageFixtures::layoutIndex()))->html;

        self::assertSame(3, substr_count($html, 'name="page_layout_reorder[rows][0][position]"') + substr_count($html, 'name="page_layout_reorder[rows][1][position]"') + substr_count($html, 'name="page_layout_reorder[rows][2][position]"'));
        self::assertStringContainsString('name="page_layout_reorder[rows][0][id]"', $html);
        self::assertStringContainsString('name="page_layout_reorder[_token]" value="REDACTED-TEST-page_layout_reorder"', $html);
        self::assertStringContainsString('href="/admin/page-blocks/text/2/edit"', $html);
        self::assertStringContainsString('#3', $html);
        self::assertStringContainsString('Save order', $html);
        self::assertStringContainsString('target="_blank"', $html);
        self::assertStringNotContainsString('form-control', $html);

        $empty = $this->renderPage(new PageFixture('@NowoPageLayoutKitBundle/admin/layout/index.html.twig', PageFixtures::layoutIndex(true)))->html;
        self::assertStringContainsString('No migrated blocks. Run nowo:page-layout:migrate.', $empty);
        self::assertStringNotContainsString('<form', $empty);
        self::assertStringNotContainsString('Save order', $empty);
    }

    public function testTheBlockPageShowsTheBlockAndItsPage(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoPageLayoutKitBundle/admin/blocks/edit_stub.html.twig', PageFixtures::blockEdit()))->html;

        self::assertMatchesRegularExpression('#Type</dt>\s*<dd[^>]*>cards</dd>#', $html);
        self::assertMatchesRegularExpression('#Page key</dt>\s*<dd[^>]*>home</dd>#', $html);

        $orphan = $this->renderPage(new PageFixture('@NowoPageLayoutKitBundle/admin/blocks/edit_stub.html.twig', PageFixtures::blockEdit(true)))->html;
        self::assertMatchesRegularExpression('#Page key</dt>\s*<dd[^>]*><span[^>]*>—</span></dd>#', $orphan);
    }

    public function testThe20PageCopiesStillRenderInsideTheUiKitLayoutWithoutACdn(): void
    {
        $html = $this->twigWithLegacyTemplates()->render('page_20.html.twig');

        self::assertStringContainsString('data-nowo-ui-contract-layout="admin"', $html);
        self::assertStringContainsString('Old title', $html);
        self::assertStringContainsString('<p id="old-content">Old content</p>', $html);
        self::assertStringNotContainsString('cdn.jsdelivr.net', $html);
        self::assertStringNotContainsString('<html', $html);
    }

    #[IgnoreDeprecations]
    public function testA20HostLayoutIsBridged(): void
    {
        $twig    = $this->twigWithLegacyTemplates();
        $pages   = new PageLayoutKitPages('host_20.html.twig', [], $twig);
        $context = $pages->context(['page' => (new PageLayoutKitPages('host_20.html.twig', [], $twig))->blockEditPage(PageBlockType::Hero, 1, 'home', '/back', null)]);

        $html = $twig->render('@NowoPageLayoutKitBundle/admin/blocks/edit_stub.html.twig', $context);

        self::assertStringContainsString('<div id="host20">', $html);
        self::assertStringContainsString('<title>Edit block</title>', $html);
        self::assertStringContainsString('<h1 id="host-title">Edit block</h1>', $html);
        self::assertStringContainsString('<link id="host-css">', $html);
        self::assertStringContainsString('/assets/css/nowo-ui.css', $html);
        self::assertStringContainsString('<script id="host-js"></script>', $html);
        self::assertMatchesRegularExpression('#Type</dt>\s*<dd[^>]*>hero</dd>#', $html);
    }

    #[IgnoreDeprecations]
    public function testACopyOfThe20BundleLayoutKeepsItsOwnCanonicalBlocks(): void
    {
        $twig    = $this->twigWithLegacyTemplates();
        $pages   = new PageLayoutKitPages('host_20_copy.html.twig', [], $twig);
        $context = $pages->context(['page' => $pages->blockEditPage(PageBlockType::Hero, 1, 'home', null, null)]);

        $html = $twig->render('@NowoPageLayoutKitBundle/admin/blocks/edit_stub.html.twig', $context);

        self::assertSame(1, substr_count($html, 'data-testid="page-layout-kit-block"'));
        self::assertSame(1, substr_count($html, 'nowo-ui-flashes'));
        self::assertMatchesRegularExpression('#<div id="host-content">\s*<div class="nowo-ui-show-page"#', $html);
    }

    public function testTheCmsModalShellIsTheUiKitDialog(): void
    {
        $html = $this->twigWithLegacyTemplates()->render('@NowoPageLayoutKitBundle/cms/modal_shell.html.twig');

        self::assertMatchesRegularExpression('#<dialog id="cms-modal"[^>]*data-nowo-ui-dialog[^>]*data-cms-modal-target="modal"#', $html);
        self::assertStringContainsString('data-cms-modal-target="title"', $html);
        self::assertStringContainsString('data-cms-modal-target="body"', $html);
        self::assertMatchesRegularExpression('#<button type="submit"[^>]*form="cms-modal-active-form" data-cms-modal-target="submit" disabled>#', $html);
        self::assertStringContainsString('data-nowo-ui-dialog-close data-action="cms-modal#close"', $html);
        self::assertStringContainsString('/assets/js/nowo-ui-dialog.js', $html);
        self::assertStringContainsString('/assets/js/nowo-ui-tabs.js', $html);
        self::assertStringNotContainsString(' hidden', $html);
    }

    protected static function inlineTemplates(): array
    {
        return [
            'page_20.html.twig'      => self::PAGE_20,
            'host_20.html.twig'      => self::HOST_20,
            'host_20_copy.html.twig' => self::HOST_20_COPY,
        ];
    }

    private function twigWithLegacyTemplates(): Environment
    {
        $twig    = $this->createTwig();
        $checker = $this->createStub(PageLayoutKitAccessCheckerInterface::class);
        $twig->addExtension(new PageLayoutKitExtension(null, null, ['home'], 'en', $checker));

        return $twig;
    }
}
