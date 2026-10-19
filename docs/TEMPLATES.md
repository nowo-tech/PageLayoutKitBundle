# Templates

Templates live in `src/Resources/views/` (Twig namespace `@NowoPageLayoutKitBundle`). Override one by copying it to
`templates/bundles/NowoPageLayoutKitBundle/<same path>`.

## Table of contents

- [Block edit modal](#block-edit-modal)

## Block edit modal

| Template | Role |
| --- | --- |
| `cms/modal_shell.html.twig` | Modal chrome rendered in the host page (`cms-modal` Stimulus controller); loads UiKit's `nowo-ui-tabs.js` |
| `admin/_modal_form.html.twig` | Modal body fetched over AJAX (`/admin/page-blocks/{type}/{id}/edit-modal`): the block form, translations as locale tabs |
| `admin/blocks/_modal_form.html.twig` | Alias of `admin/_modal_form.html.twig` kept for hosts that include this path |

The `translations` collection (entries `PageHeroBlockEditType`, `PageBlockLocalePanelType`, …; `error_bubbling: false`)
is rendered with FormKit's `@NowoFormKitBundle/form/_locale_tabs.html.twig` in mode `entries`: `default_locale` and
`required_locales` = `nowo_page_layout_kit.default_locale`, `active_locale` = the request locale, tabs id
`cms-locale`, tab names from the bundle's `locale.<code>` keys. FormKit renders it through UiKit's `locale_tabs`
partial: root `#cms-locale`, buttons `#tab-cms-locale-<locale>`, panels `#tab-cms-locale-<locale>-panel`
(`[data-nowo-ui-tabs-panel]`, the open one `data-active`), theme slots `tabs`, `tab`, `tab_active`, `tab_panel`.

Editors in a panel mount when it opens: `cms_modal_controller.ts` listens to `nowo-ui:tabs:change` on the modal body
and mounts CKEditor in `event.detail.panel`.
