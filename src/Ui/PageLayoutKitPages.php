<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Ui;

use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\BackLink;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\DetailSection;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\ShowPage;
use Nowo\UiKitBundle\View\Table;
use Symfony\Component\Form\FormView;
use Throwable;
use Twig\Environment;

use function in_array;
use function is_string;
use function trigger_deprecation;

/**
 * UiKit page contract (REQ-UI-003) of the admin screens: builds the view models of the pages (`FormPage` for the
 * reorder screen, `ShowPage` for the block page), resolves the layout they extend (`web_ui.layout_template`, null =
 * `nowo_ui_kit.layouts.admin`), wraps a 2.0 admin layout (block `admin`, no UiKit canonical blocks) in a deprecated
 * bridge, and resolves the per-page template overrides (`web_ui.templates.<page>`). Configuration only: no request
 * state, safe in worker mode.
 */
final readonly class PageLayoutKitPages
{
    /** Bundle name of the page view models (theme exceptions `nowo_ui_kit.theme.bundles.NowoPageLayoutKitBundle`). */
    public const string BUNDLE = 'NowoPageLayoutKitBundle';

    /** Translation domain of the bundle labels. */
    public const string DOMAIN = 'NowoPageLayoutKitBundle';

    /**
     * The 2.0 default layout. Deprecated since 2.1: it extends the UiKit admin layout and only serves templates that
     * still extend the deprecated `nowo_page_layout_kit_layout` global; as `web_ui.layout_template` it means null.
     */
    public const string LEGACY_DEFAULT_LAYOUT = '@NowoPageLayoutKitBundle/admin/layout.html.twig';

    /** BC bridge around a 2.0 host admin layout that renders `{% block admin %}` (removed in 3.0). */
    public const string LEGACY_ADMIN_BRIDGE = '@NowoPageLayoutKitBundle/admin/_legacy_layout.html.twig';

    /** Page ids accepted by `web_ui.templates` (page id => default template). */
    public const array TEMPLATES = [
        'layout_index' => '@NowoPageLayoutKitBundle/admin/layout/index.html.twig',
        'block_edit'   => '@NowoPageLayoutKitBundle/admin/blocks/edit_stub.html.twig',
    ];

    /** A layout that defines this block renders the UiKit canonical blocks. */
    private const string CANONICAL_BLOCK = 'nowo_ui_page_header';

    /** Canonical blocks a 2.0 layout may already render (copies of the 2.0 bundle layout; the bridge skips them). */
    private const array LEGACY_HOST_BLOCKS = ['nowo_ui_flashes', 'nowo_ui_content', 'nowo_ui_modals', 'nowo_ui_styles', 'nowo_ui_scripts'];

    /**
     * @param array<string, string> $templates `web_ui.templates` (page id => template)
     */
    public function __construct(
        private ?string $layoutTemplate = null,
        private array $templates = [],
        private ?Environment $twig = null,
    ) {
    }

    /**
     * Translation key of the bundle domain as a UiKit label.
     *
     * @param array<string, mixed> $params
     */
    public static function label(string $key, array $params = []): Label
    {
        return new Label($key, self::DOMAIN, $params);
    }

    /**
     * Template of a page: the `web_ui.templates.<page>` override, else the bundle template.
     */
    public function template(string $page): string
    {
        $override = $this->templates[$page] ?? null;

        return is_string($override) && $override !== '' ? $override : self::TEMPLATES[$page] ?? $page;
    }

    /**
     * Layout the scaffolds extend (`page.layout`): `web_ui.layout_template`, the legacy bridge for a 2.0 layout, else
     * null (UiKit `nowo_ui_layout.admin`).
     */
    public function layout(): ?string
    {
        $layout = $this->configuredLayout();
        if ($layout === null) {
            return null;
        }

        return $this->legacy($layout) !== null ? self::LEGACY_ADMIN_BRIDGE : $layout;
    }

    /**
     * Shared variables of the admin pages (added to the controller variables): the 2.0 layout wrapped by the deprecated
     * bridge and the canonical blocks it already renders.
     *
     * @param array<string, mixed> $vars page variables
     *
     * @return array<string, mixed>
     */
    public function context(array $vars): array
    {
        $layout = $this->configuredLayout();
        $legacy = $layout !== null ? $this->legacy($layout) : null;

        return $vars + [
            'nowo_page_layout_kit_legacy_layout' => $legacy,
            'nowo_page_layout_kit_legacy_blocks' => $this->hostBlocks($legacy),
        ];
    }

    /**
     * Reorder screen of one page (`page/form` scaffold): header with the "View page" action, the reorder form (body =
     * the entries table, `layoutTable()`), submit "Save order". Without entries the form is not rendered (body-only
     * mode) and the body shows the empty state.
     */
    public function layoutPage(string $pageKey, ?FormView $form, bool $hasEntries, ?string $viewPageUrl): FormPage
    {
        $actions = [];
        if ($viewPageUrl !== null) {
            $actions[] = new Action(
                kind: 'view',
                label: self::label('admin.layout.view_page'),
                href: $viewPageUrl,
                variant: 'secondary',
                attributes: ['target' => '_blank', 'rel' => 'noopener'],
            );
        }

        return new FormPage(
            header: new PageHeader(
                self::label('admin.layout.title', ['%page%' => $pageKey]),
                self::label('admin.layout.lead', ['%page%' => $pageKey]),
                $actions,
            ),
            form: $hasEntries ? $form : null,
            submitLabel: self::label('admin.layout.save_order'),
            bundle: self::BUNDLE,
            layout: $this->layout(),
            attributes: ['data-testid' => 'page-layout-kit-layout'],
            formTag: $hasEntries,
        );
    }

    /**
     * Entries table of the reorder screen: position (form field), type, block id; row action Edit (`$editUrl`).
     *
     * @param list<PageLayoutEntry> $entries
     * @param (callable(PageLayoutEntry): string)|null $editUrl
     */
    public function layoutTable(array $entries, ?callable $editUrl = null): Table
    {
        return new Table(
            columns: [
                new Column('position', self::label('admin.layout.position'), required: true),
                new Column('type', self::label('admin.layout.type')),
                new Column('id', self::label('admin.layout.id'), priority: 2),
            ],
            rows: $entries,
            rowActions: static fn (PageLayoutEntry $entry): array => $editUrl === null ? [] : [
                Action::edit($editUrl($entry), self::label('admin.layout.edit')),
            ],
            actionsLabel: self::label('admin.layout.actions'),
            actionsLabelVisible: false,
            responsive: 'scroll',
            bundle: self::BUNDLE,
            label: self::label('admin.title'),
        );
    }

    /**
     * Empty state of the reorder screen (no migrated blocks).
     */
    public function layoutEmpty(): EmptyState
    {
        return new EmptyState(message: self::label('admin.layout.empty'));
    }

    /**
     * Block page (`page/show` scaffold): header (lead with the block type, "View page" action, back link to the
     * layout of the owning page) and one section with the block type, id and page.
     */
    public function blockEditPage(PageBlockType $type, int $id, ?string $pageKey, ?string $backUrl, ?string $viewPageUrl): ShowPage
    {
        $actions = [];
        if ($viewPageUrl !== null) {
            $actions[] = new Action(
                kind: 'view',
                label: self::label('admin.layout.view_page'),
                href: $viewPageUrl,
                variant: 'secondary',
                attributes: ['target' => '_blank', 'rel' => 'noopener'],
            );
        }

        return new ShowPage(
            header: new PageHeader(
                self::label('admin.blocks.edit.title'),
                self::label('admin.blocks.edit.lead', ['%type%' => $type->value]),
                $actions,
                back: $backUrl !== null ? new BackLink($backUrl, self::label('admin.blocks.edit.back_to_layout')) : null,
            ),
            sections: [
                new DetailSection(null, [
                    new DetailItem(self::label('admin.layout.type'), $type->value),
                    new DetailItem(self::label('admin.layout.id'), $id),
                    new DetailItem(self::label('admin.blocks.edit.page'), $pageKey),
                ], 'page-layout-kit-block'),
            ],
            bundle: self::BUNDLE,
            layout: $this->layout(),
            attributes: ['data-testid' => 'page-layout-kit-block'],
        );
    }

    private function configuredLayout(): ?string
    {
        if (in_array($this->layoutTemplate, [null, '', self::LEGACY_DEFAULT_LAYOUT], true)) {
            return null;
        }

        return $this->layoutTemplate;
    }

    /**
     * Canonical blocks a 2.0 layout defines itself (the bridge does not render them a second time).
     *
     * @return list<string>
     */
    private function hostBlocks(?string $legacy): array
    {
        if ($legacy === null || !$this->twig instanceof Environment) {
            return [];
        }

        $template = $this->twig->load($legacy);
        $context  = $this->twig->mergeGlobals([]);
        $blocks   = [];
        foreach (self::LEGACY_HOST_BLOCKS as $block) {
            if ($template->hasBlock($block, $context)) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * The configured layout when it is a 2.0 layout (it does not define the UiKit canonical blocks), else null.
     */
    private function legacy(string $layout): ?string
    {
        if (!$this->twig instanceof Environment) {
            return null;
        }

        try {
            $canonical = $this->twig->load($layout)->hasBlock(self::CANONICAL_BLOCK, $this->twig->mergeGlobals([]));
        } catch (Throwable) {
            return null;
        }

        if ($canonical) {
            return null;
        }

        trigger_deprecation('nowo-tech/page-layout-kit-bundle', '2.1', 'The layout "%s" does not render the UiKit canonical blocks (nowo_ui_page_header, nowo_ui_content, ...); it is wrapped in a deprecated bridge. Render the canonical blocks (or extend @NowoUiKit/layout/admin.html.twig) or set web_ui.layout_template to null to use nowo_ui_kit.layouts.admin; see docs/UPGRADING.md.', $layout);

        return $layout;
    }
}
