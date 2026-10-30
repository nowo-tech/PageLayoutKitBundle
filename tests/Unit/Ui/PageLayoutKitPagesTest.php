<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Ui;

use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Ui\PageLayoutKitPages;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\Label;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormView;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class PageLayoutKitPagesTest extends TestCase
{
    public function testLabelsUseTheBundleDomain(): void
    {
        $label = PageLayoutKitPages::label('admin.layout.title', ['%page%' => 'home']);

        self::assertEquals(new Label('admin.layout.title', 'NowoPageLayoutKitBundle', ['%page%' => 'home']), $label);
    }

    public function testTemplatesFallBackToTheBundlePages(): void
    {
        $pages = new PageLayoutKitPages(templates: ['block_edit' => 'admin/block.html.twig', 'layout_index' => '']);

        self::assertSame('admin/block.html.twig', $pages->template('block_edit'));
        self::assertSame(PageLayoutKitPages::TEMPLATES['layout_index'], $pages->template('layout_index'));
        self::assertSame('unknown', $pages->template('unknown'));
    }

    public function testNoLayoutMeansTheUiKitAdminLayout(): void
    {
        foreach ([null, '', PageLayoutKitPages::LEGACY_DEFAULT_LAYOUT] as $layout) {
            $pages = new PageLayoutKitPages($layout, [], $this->twig());

            self::assertNull($pages->layout());
            self::assertSame(['page' => 1, 'nowo_page_layout_kit_legacy_layout' => null, 'nowo_page_layout_kit_legacy_blocks' => []], $pages->context(['page' => 1]));
        }
    }

    public function testACanonicalLayoutIsUsedAsIs(): void
    {
        $pages = new PageLayoutKitPages('canonical.html.twig', [], $this->twig());

        self::assertSame('canonical.html.twig', $pages->layout());
        self::assertNull($pages->context([])['nowo_page_layout_kit_legacy_layout']);
    }

    public function testWithoutTwigOrForAMissingLayoutTheConfiguredLayoutIsUsed(): void
    {
        self::assertSame('legacy.html.twig', (new PageLayoutKitPages('legacy.html.twig'))->layout());
        self::assertSame('missing.html.twig', (new PageLayoutKitPages('missing.html.twig', [], $this->twig()))->layout());
        self::assertSame([], (new PageLayoutKitPages('legacy.html.twig'))->context([])['nowo_page_layout_kit_legacy_blocks']);
    }

    #[IgnoreDeprecations]
    public function testA20LayoutIsWrappedInTheDeprecatedBridge(): void
    {
        $this->expectUserDeprecationMessageMatches('/The layout "legacy_copy\\.html\\.twig" does not render the UiKit canonical blocks/');

        $pages = new PageLayoutKitPages('legacy_copy.html.twig', [], $this->twig());

        self::assertSame(PageLayoutKitPages::LEGACY_ADMIN_BRIDGE, $pages->layout());
        self::assertSame([
            'nowo_page_layout_kit_legacy_layout' => 'legacy_copy.html.twig',
            'nowo_page_layout_kit_legacy_blocks' => ['nowo_ui_flashes', 'nowo_ui_content'],
        ], $pages->context([]));
    }

    public function testLayoutPage(): void
    {
        $form = new FormView();
        $page = (new PageLayoutKitPages())->layoutPage('home', $form, true, '/');

        self::assertSame($form, $page->form);
        self::assertTrue($page->formTag);
        self::assertSame('NowoPageLayoutKitBundle', $page->bundle);
        self::assertNull($page->layout);
        self::assertEquals(PageLayoutKitPages::label('admin.layout.title', ['%page%' => 'home']), $page->header->title);
        self::assertEquals(PageLayoutKitPages::label('admin.layout.save_order'), $page->submitLabel);
        self::assertCount(1, $page->header->actions);
        self::assertSame('/', $page->header->actions[0]->href);
        self::assertSame(['target' => '_blank', 'rel' => 'noopener'], $page->header->actions[0]->attributes);

        $empty = (new PageLayoutKitPages())->layoutPage('home', $form, false, null);
        self::assertNull($empty->form);
        self::assertFalse($empty->formTag);
        self::assertSame([], $empty->header->actions);
    }

    public function testLayoutTableAndEmptyState(): void
    {
        $entry = (new PageLayoutEntry())->setPageKey('home')->setBlockType(PageBlockType::Hero)->setBlockId(5)->setPosition(0);
        $pages = new PageLayoutKitPages();

        $table = $pages->layoutTable([$entry], static fn (PageLayoutEntry $e): string => '/edit/' . $e->getBlockId());
        self::assertSame(['position', 'type', 'id'], array_map(static fn (Column $column): string => $column->key, $table->columns));
        self::assertSame([$entry], $table->rows);
        $actions = $table->rowActions($entry);
        self::assertCount(1, $actions);
        self::assertSame('/edit/5', $actions[0]->href);

        self::assertSame([], $pages->layoutTable([$entry])->rowActions($entry));
        self::assertEquals(PageLayoutKitPages::label('admin.layout.empty'), $pages->layoutEmpty()->message);
    }

    public function testBlockEditPage(): void
    {
        $page = (new PageLayoutKitPages())->blockEditPage(PageBlockType::Cards, 3, 'home', '/admin/pages/home/layout', '/');

        self::assertEquals(PageLayoutKitPages::label('admin.blocks.edit.lead', ['%type%' => 'cards']), $page->header->intro);
        self::assertSame('/admin/pages/home/layout', $page->header->back?->href);
        self::assertCount(1, $page->header->actions);
        self::assertSame(['cards', 3, 'home'], array_map(static fn (DetailItem $item): mixed => $item->value, $page->sections[0]->items));

        $orphan = (new PageLayoutKitPages())->blockEditPage(PageBlockType::Cards, 3, null, null, null);
        self::assertNull($orphan->header->back);
        self::assertSame([], $orphan->header->actions);
    }

    private function twig(): Environment
    {
        return new Environment(new ArrayLoader([
            'canonical.html.twig'   => '{% block nowo_ui_page_header %}{% endblock %}{% block nowo_ui_content %}{% endblock %}',
            'legacy_copy.html.twig' => '{% block admin %}{% endblock %}{% block nowo_ui_flashes %}{% endblock %}{% block nowo_ui_content %}{% endblock %}',
        ]));
    }
}
