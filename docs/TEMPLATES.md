# Templates

Templates live in `src/Resources/views/` (Twig namespace `@NowoPageLayoutKitBundle`). The admin pages follow the UiKit
page contract (REQ-UI-003, UiKit 2.17): they extend a UiKit scaffold and fill data blocks only, inside the host admin
layout (`nowo_ui_kit.layouts.admin`, or `nowo_page_layout_kit.web_ui.layout_template`). `Nowo\PageLayoutKitBundle\Ui\PageLayoutKitPages`
builds the UiKit view models (`Nowo\UiKitBundle\View\*`) for the controllers, resolves the layout and the per-page
overrides (`web_ui.templates.<page>`). Contract test: `tests/Integration/Contract/PageContractTest.php` (UiKit
`PageContractTestCase`, strict missing-translation rule).

Override one template by copying it to `templates/bundles/NowoPageLayoutKitBundle/<same path>`. An application file at
the override path always wins and will not pick up vendor changes for that path until removed or merged; see
[USAGE.md — Twig overrides](USAGE.md#twig-overrides) for the upgrade-safe alternatives, the recovery steps and the full
list of overridable `<subpath>` values.

## Table of contents

- [Pages](#pages)
- [Unique pages](#unique-pages)
- [Per-page overrides](#per-page-overrides)
- [Layout bridge (2.0 layouts)](#layout-bridge-20-layouts)
- [Inline CMS modal](#inline-cms-modal)
- [Block edit modal](#block-edit-modal)
- [Template contract changes](#template-contract-changes)

## Pages

Every view model passes `bundle: 'NowoPageLayoutKitBundle'` (theme exception
`nowo_ui_kit.theme.bundles.NowoPageLayoutKitBundle`) and `layout: PageLayoutKitPages::layout()`. Labels are
`NowoPageLayoutKitBundle` translation keys (`PageLayoutKitPages::label()`).

| Page (`web_ui.templates` id) | Template | Scaffold | View model | Data blocks filled |
| --- | --- | --- | --- | --- |
| Reorder screen (`layout_index`) | `admin/layout/index.html.twig` | `page/form` | `FormPage` (header `admin.layout.title`, intro `admin.layout.lead`, action "View page" (`admin.layout.view_page`, new tab) when a public route resolves; the `PageLayoutReorderType` form, submit `admin.layout.save_order`; body-only mode — no form, no submit — when the page has no entries; `data-testid="page-layout-kit-layout"`) + `layout_table` (`Table`: columns position, type, id (priority 2); row action Edit → block page; responsive `scroll`) + `layout_empty` (`EmptyState` `admin.layout.empty`) | `nowo_ui_form_body` (the table through the UiKit `table` partial, `nowo_ui_table_row`: position field on the `input` slot, type on `badge_neutral`, id on `code`; else the UiKit `empty` partial) |
| Block page (`block_edit`) | `admin/blocks/edit_stub.html.twig` | `page/show` | `ShowPage` (header `admin.blocks.edit.title`, intro `admin.blocks.edit.lead`, action "View page", back link `admin.blocks.edit.back_to_layout` to the layout of the owning page; one section with type, id, page key; `data-testid="page-layout-kit-block"`) | — (the scaffold renders the section) |

The controllers also pass the 2.0 variables for host templates set through `web_ui.templates.<page>`:
`page_title`, `page_key`, `entries`, `reorder_form`, `view_page_url` (reorder screen) and `page_title`, `block_type`,
`block_id`, `page_key`, `back_url`, `view_page_url` (block page).

## Unique pages

None: both admin pages extend a scaffold. The public block partials (`blocks/*.html.twig`, `cms/*.html.twig`) are
fragments included in the host's own pages, not pages of the bundle.

## Per-page overrides

- `nowo_page_layout_kit.web_ui.templates.<page>` (ids in the [Pages](#pages) table): a host template that extends
  `@!NowoPageLayoutKitBundle/<template>` and overrides a data block (`nowo_ui_form_body`, `nowo_ui_show_sections`,
  `nowo_ui_content`, …). Unknown page ids fail the container compilation.
- Prefer theming the UiKit slots (`nowo_ui_kit.theme`) and scaffolds once for every bundle, and record such overrides in
  the application's override inventory.

## Layout bridge (2.0 layouts)

Until 2.0 the pages extended the Twig global `nowo_page_layout_kit_layout` (`web_ui.layout_template`, default
`@NowoPageLayoutKitBundle/admin/layout.html.twig`, which loaded Bootstrap from a CDN) and filled `title`, `page_title`
and `admin`. Since 2.1 (deprecated, removed in 3.0):

- `web_ui.layout_template` defaults to null (= `nowo_ui_kit.layouts.admin`); the 2.0 default value means null.
- A configured layout that does not define `nowo_ui_page_header` (a 2.0 layout rendering `{% block admin %}`) is wrapped
  in `admin/_legacy_layout.html.twig`: it maps `title` / `page_title` to the page title and prints the page header,
  flashes, content and modals inside `admin`, the UiKit stylesheet in `stylesheets` and the UiKit scripts in
  `javascripts`; canonical blocks the 2.0 layout already renders (`nowo_ui_flashes`, `nowo_ui_content`, …, e.g. a copy
  of the 2.0 bundle layout) are not printed twice. A deprecation is triggered.
- `admin/layout.html.twig` now extends `nowo_ui_layout.admin` (no own `<html>`, no CDN) and maps `title`, `page_title`
  and `admin` into the canonical blocks, so copies of the 2.0 pages that extend `nowo_page_layout_kit_layout` keep
  rendering. The globals `nowo_page_layout_kit_layout` and `nowo_page_layout_kit_css_framework` are deprecated; the
  bundle pages do not read them.

## Inline CMS modal

`cms/modal_shell.html.twig` is rendered once in the host page. Since 2.1 it is the UiKit `dialog` partial (native
`<dialog id="cms-modal">`, size `lg`) carrying the `cms-modal` Stimulus targets: `modal` (the dialog), `title`, `body`
(the form fetched from `/admin/page-blocks/{type}/{id}/edit-modal`) and `submit` (footer, `btn_primary` slot, bound to
`#cms-modal-active-form`); cancel on `btn_secondary`. `cms_modal_controller.ts` opens it with `showModal()` and resets
it on `close` (Escape, the UiKit close / cancel buttons). The shell loads UiKit's `dialog` and `tabs` runtimes. Labels:
`cms.edit_block`, `cms.close`, `cms.cancel`, `cms.save` (host `messages` domain). The controller still supports a
2.0 shell override (a `<div hidden>` modal target).

## Block edit modal

| Template | Role |
| --- | --- |
| `cms/modal_shell.html.twig` | Modal chrome rendered in the host page (see [Inline CMS modal](#inline-cms-modal)) |
| `admin/_modal_form.html.twig` | Modal body fetched over AJAX (`/admin/page-blocks/{type}/{id}/edit-modal`): the block form, translations as locale tabs |
| `admin/blocks/_modal_form.html.twig` | Alias of `admin/_modal_form.html.twig` kept for hosts that include this path |

The `translations` collection (entries `PageHeroBlockEditType`, `PageBlockLocalePanelType`, …; `error_bubbling: false`)
is rendered with FormKit's `@NowoFormKitBundle/form/_locale_tabs.html.twig` in mode `entries`: `default_locale` and
`required_locales` = `nowo_page_layout_kit.default_locale`, `active_locale` = the request locale, tabs id
`cms-locale`, tab names from the bundle's `locale.<code>` keys. FormKit renders it through UiKit's `locale_tabs`
partial: root `#cms-locale`, buttons `#tab-cms-locale-<locale>`, panels `#tab-cms-locale-<locale>-panel`
(`[data-nowo-ui-tabs-panel]`, the open one `data-active`), theme slots `tabs`, `tab`, `tab_active`, `tab_panel`.
The default FormKit profile `page_layout_kit` is theme-neutral (`attr.class: nowo-ui-input`, no `row_attr`).

Editors in a panel mount when it opens: `cms_modal_controller.ts` listens to `nowo-ui:tabs:change` on the modal body
and mounts CKEditor in `event.detail.panel`.

## Template contract changes

| Version | Change |
| --- | --- |
| 2.1.0 | Reorder screen and block page on UiKit scaffolds (`page/form`, `page/show`); blocks `title`, `page_title`, `admin` replaced by the scaffold data blocks; `admin/layout.html.twig` deprecated (extends the UiKit admin layout, no CDN); new `admin/_legacy_layout.html.twig` bridge; `cms/modal_shell.html.twig` is the UiKit `dialog` partial; FormKit profile defaults without framework classes; `web_ui.templates`; new key `admin.blocks.edit.page`. |
