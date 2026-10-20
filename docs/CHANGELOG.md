# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.6.1] - 2026-10-20](#161---2026-10-20)
- [[1.6.0] - 2026-10-19](#160---2026-10-19)
- [[1.5.1] - 2026-10-18](#151---2026-10-18)
- [[1.5.0] - 2026-10-13](#150---2026-10-13)
- [[1.4.0] - 2026-10-13](#140---2026-10-13)
- [[1.3.0] - 2026-10-13](#130---2026-10-13)
- [[1.2.1] - 2026-10-09](#121---2026-10-09)
- [[1.2.0] - 2026-09-28](#120---2026-09-28)
- [[1.1.1] - 2026-09-27](#111---2026-09-27)
- [[1.1.0] - 2026-09-24](#110---2026-09-24)
- [[1.0.6] - 2026-09-07](#106---2026-09-07)
- [[1.0.5] - 2026-08-24](#105---2026-08-24)
- [[1.0.4] - 2026-08-19](#104---2026-08-19)
- [[1.0.2] - 2026-08-19](#102---2026-08-19)
- [[1.0.1] - 2026-08-19](#101---2026-08-19)
- [[1.0.0] - 2026-08-19](#100---2026-08-19)

## [Unreleased]

## [1.6.1] - 2026-10-20

Documentation: wave-1 REQ compliance (README, CONTRIBUTING, issue templates, Spec Kit inventory). See [UPGRADING](UPGRADING.md#to-161).

### Changed

- Documentation: wave-1 REQ compliance — README standard badges (Symfony 7.4 | 8.0 | 8.1+, stars image, Coverage) and documentation list order, canonical FrankenPHP claim line, CONTRIBUTING Cursor Agent settings and post-release-commit trailer check, canonical issue templates, tailored Spec Kit constitution, demo `.env.example` comments, SPEC-DRIVEN-DEVELOPMENT relationship to Engram, doc TOCs, Spec Kit inventory refresh (117/117).

[1.6.1]: https://github.com/nowo-tech/PageLayoutKitBundle/releases/tag/v1.6.1

## [1.6.0] - 2026-10-19

Block edit modal translations use FormKit 2.13 locale_tabs through the UiKit 2.5 partial; CKEditor remounts on nowo-ui:tabs:change and hosts no longer need a locale-tabs Stimulus controller; requires FormKit ^2.13 and UiKit ^2.5.1. See [UPGRADING](UPGRADING.md#to-160).

### Changed

- **Block edit modal translations** render through FormKit 2.13 `locale_tabs` (mode `entries`) and UiKit 2.5's
  `locale_tabs` partial instead of the bundle's own `admin/_locale_tabs.html.twig` and a host-provided Stimulus
  `locale-tabs` controller: default locale (`nowo_page_layout_kit.default_locale`) first and marked `★` / `*`, the
  locale child a hidden input, WAI-ARIA tabs with arrow / Home / End keys, invalid tabs marked `!` and opened first,
  `aria-invalid` on invalid widgets, theme slots `tabs` / `tab` / `tab_active` / `tab_panel`. Translation entries use
  `error_bubbling: false`. Tab names keep the bundle's `locale.<code>` labels.
- `cms_modal_controller.ts` remounts CKEditor in the panel a tab opens on `nowo-ui:tabs:change` (`detail.panel`)
  instead of `locale-tabs:changed`; the open panel is the one with `data-active`.
- `cms/modal_shell.html.twig` loads UiKit's tabs runtime (`nowo-ui-tabs.js`, through the UiKit `scripts` partial) and
  `admin/layout.html.twig` loads the UiKit runtimes, so hosts need no tabs controller.
- The default-locale panel no longer forces `required` on every field; each field keeps its own `required`.
- Requires `nowo-tech/form-kit-bundle ^2.13` and `nowo-tech/ui-kit-bundle ^2.5.1` (UiKit 1.x is no longer supported).

### Removed

- `@NowoPageLayoutKitBundle/admin/_locale_tabs.html.twig`, its `admin.locale_tabs.label` translation key and the
  dependency on a host Stimulus `locale-tabs` controller.

[1.6.0]: https://github.com/nowo-tech/PageLayoutKitBundle/releases/tag/v1.6.0

## [1.5.1] - 2026-10-18

Allows nowo-tech/ui-kit-bundle 2.x (^1.4 || ^2.2) so hosts can move to UiKit 2.2. See [UPGRADING](UPGRADING.md#to-151).

### Changed

- Allows nowo-tech/ui-kit-bundle 2.x (`^1.4 || ^2.2`); the dev lock and the demo lock use 2.2.0. Templates are unchanged (compatibility release). With UiKit 2.x `nowo-ui.css` ships in `@layer nowo-ui`, so unlayered host/framework CSS wins equal-specificity conflicts with kit rules in the admin pages; to keep the 1.x cascade, override the admin layout `stylesheets` block and link `css/nowo-ui.unlayered.css` (UiKit 2.x only).

[1.5.1]: https://github.com/nowo-tech/PageLayoutKitBundle/releases/tag/v1.5.1

## [1.5.0] - 2026-10-13

### Fixed

- **Translations:** the layout reorder form no longer shows the raw key `page_layout_reorder.rows.help` (visible when a page has no blocks), and the block edit modals (hero, text, CTA, compare, cards, list) no longer show raw `page_*_block_edit.*.label` / `.help` / `.placeholder` keys. `AbstractPageLayoutFormType` now renders help / placeholder only when a field passes them explicitly, translations collections render without a label (they are shown as locale tabs), and the field labels plus the cards/list items help ship in all seven locales. New regression test renders every bundle form and checks each label / help / placeholder key against every catalogue.
- **Block edit modal:** `admin/_modal_form.html.twig` (and `admin/blocks/_modal_form.html.twig`) included the host templates `components/admin/_locale_tabs.html.twig` and `form/auth_theme.html.twig`, so the modal failed with a Twig `LoaderError` in hosts that do not ship them, and it required the optional CKEditor 5 bundle's form theme. It now uses the bundle's own `@NowoPageLayoutKitBundle/admin/_locale_tabs.html.twig` (now in the `NowoPageLayoutKitBundle` domain, with `admin.locale_tabs.label` and `locale.<code>` names in all seven locales; unknown codes fall back to the bare code) and only the form themes of optional bundles that are installed (new Twig function `nowo_page_layout_kit_modal_form_themes()`), on top of the host's `twig.form_themes`. Hosts still customise both templates through `templates/bundles/NowoPageLayoutKitBundle/`. A regression test renders the modal with bundle templates only.

[1.5.0]: https://github.com/nowo-tech/PageLayoutKitBundle/releases/tag/v1.5.0

## [1.4.0] - 2026-10-13

### Fixed

- Block edit page (`admin/blocks/edit_stub.html.twig`): no more hard-coded Spanish strings or `pageKey: 'home'` link. The page now extends the configured `web_ui.layout_template` (it extended a host `admin/layout.html.twig`), uses the new `admin.blocks.edit.lead` / `admin.blocks.edit.back_to_layout` keys in all 7 locales, links back to the layout of the page that contains the block (first configured page as fallback) and shows a "View page" link through `PublicPageUrlResolver` when the host routes that page.
- Translations: de/fr/it/nl/pt had English placeholder values for most `admin.layout.*` / `admin.action.*` / `admin.flash.*` keys; they are now real translations. A key/placeholder parity test guards all locales.
- Admin templates passed the CSS framework as the `size` argument of the UI Kit `ui.btn()` macro; buttons now get the framework classes.

[1.4.0]: https://github.com/nowo-tech/PageLayoutKitBundle/releases/tag/v1.4.0

## [1.3.0] - 2026-10-13

### Added

- `web_ui.page_routes` (pageKey => route name) for the admin "View page" link and the post-save redirect; unmapped page keys try a route named like the key.

### Fixed

- Native SQL in `PageBlockSqlRepository` now reads table names from Doctrine metadata, so `doctrine.table_prefix` no longer breaks public page rendering ("no such table content_page_*").
- The layout admin and block save no longer require a host route named `home` (500 before): the "View page" link is hidden when no route resolves, and saving without a `Referer` redirects to the first resolvable public page or back to the layout admin.
- Unknown `{type}` in `/admin/page-blocks/{type}/{id}` routes returns 404 instead of a 500 (`PageBlockType::tryFrom`).
- Admin flash messages ("Block updated.", "Order updated.") and the block edit page title are translated (7 locales) instead of hard-coded Spanish/English.

[1.3.0]: https://github.com/nowo-tech/PageLayoutKitBundle/releases/tag/v1.3.0

## [1.2.1] - 2026-10-09

### Fixed

- `PageBlockMigrator`: block id lookups after `flush()` go through a guarded helper (PHPStan 2.3 `method.notFound`); behaviour unchanged.

### Dependencies

- Dependabot: `symfony/security-bundle` lockfile bump.
- Composer refresh: `nowo-tech/form-kit-bundle` 2.6.1, `nowo-tech/ui-kit-bundle` 1.9.1; dev tooling `phpstan/phpstan` 2.3.1, `phpstan/phpstan-symfony` 2.1.0, `rector/rector` 2.7.0.
- Demo: Symfony 8.1.8, `doctrine/orm` 3.7.4, `doctrine/dbal` 4.5.0, `twig/twig` 3.30.0; regenerated `config/reference.php`.

[1.2.1]: https://github.com/nowo-tech/PageLayoutKitBundle/releases/tag/v1.2.1

## [1.2.0] - 2026-09-28

### Security

- Default `html.sanitize.strategy` is **`allowlist`** (was `none`). Flex recipe ships allowlist at install time. Set `none` only for fully trusted staff editors.

### Changed

- **Doctrine ORM SortDirection:** replace string `'ASC'`/`'DESC'` in `#[ORM\OrderBy]` and QueryBuilder `orderBy`/`addOrderBy` with `SortDirection::Ascending`/`Descending` (doctrine/orm deprecation, https://github.com/doctrine/orm/issues/11313); require `doctrine/orm` `^3.7` where applicable.

## [1.1.1] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[1.2.0]: https://github.com/nowo-tech/PageLayoutKitBundle/releases/tag/v1.2.0
[1.1.1]: https://github.com/nowo-tech/PageLayoutKitBundle/releases/tag/v1.1.1

## [1.1.0] - 2026-09-24

FrankenPHP worker mode (kernel not reset / no `services_resetter`) compatibility.

### Added

- **Twig function `nowo_page_layout_kit_can_edit()`**, evaluated per call; `cms/edit_button.html.twig` uses it.
- **`PageLayoutKitEntityManagerRecoverySubscriber`:** resets the page layout entity manager at the start of a main request when a previous request closed it (FrankenPHP worker mode without `services_resetter`).
- **Docs:** `docs/FRANKENPHP-WORKER-AUDIT.md` (scenario A/B audit and remediation notes).
- **QA:** PHPStan FrankenPHP classic + worker rulesets in `phpstan.neon.dist` (`nowo-tech/phpstan-frankenphp`).

### Changed

- **`PageBlockProvider`:** the layout memo is keyed by the current main request (`WeakMap`), so it never outlives the request even when `reset()` is not called; nothing is memoized without a request (CLI).
- **`PageLayoutEntryRepository::findEnabledByPageKey()`** and admin block loaders (`findWithTranslations` / `findWithItemsAndTranslations`) refresh from the database (`HINT_REFRESH`), so a long-lived identity map cannot serve stale layout or block data.
- **Make:** `test-coverage` preserves PHPUnit exit status when teeing coverage output (avoids green `release-check` on failing tests).

### Deprecated

- Twig global `nowo_page_layout_kit_can_edit` (frozen per Twig environment in worker mode without reset, and on Twig < 3.14 even with reset).
- **`PageLocales::bind()`, `PageLocales::default()` and `PageLocales::all()`** are deprecated since 1.1.0 (static binding, removed in 2.0). Inject the `PageLocales` service and use `getDefault()` / `getAll()`. `PageBlockSqlRepository`, `PageBlockMigrator`, `PageBlockEditController`, `PageCardsBlockInlineModalType` and `PageListBlockInlineModalType` accept an optional trailing `?PageLocales $pageLocales` argument (wired by the bundle); `TranslatableBlockTrait::getTranslationOrFallback()` / `ensureTranslations()` and the block entities' `toArray()` accept an optional trailing `?PageLocales`. Without it they fall back to the static binding, which `NowoPageLayoutKitBundle::boot()` still sets for third-party callers; bundle code always passes the injected service.

### Fixed

- Worker-mode issues W-01…W-03 from `docs/FRANKENPHP-WORKER-AUDIT.md`.
- Fixed PHPStan findings in `src/` and `tests/` (level 8); the two `PageLocales` static-binding worker findings (`frankenphp.worker.noMutableStaticProperty`) remain by design until the static API is removed in 2.0.

[1.1.0]: https://github.com/nowo-tech/PageLayoutKitBundle/releases/tag/v1.1.0

## [1.0.6] - 2026-09-07

### Changed

- **Dependency:** bump `symfony/security-bundle` in the Symfony group (lock refresh).
- **Style:** PHP CS Fixer alignment.

### Notes

- **No API or configuration changes** for integrators.

[1.0.6]: https://github.com/nowo-tech/PageLayoutKitBundle/releases/tag/v1.0.6

## [1.0.5] - 2026-08-24

### Changed

- **Demos:** MySQL env policy in FrankenPHP stack (REQ-DEMO-011).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).
- **Style:** PHP CS Fixer alignment.

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.0.5]: https://github.com/nowo-tech/PageLayoutKitBundle/releases/tag/v1.0.5

## [1.0.4] - 2026-08-19

Security patch: HTML sanitization for CMS block content and CI hardening.

### Security

- **`html.sanitize`:** configurable strategy (`none`, `strip`, `allowlist`, `service`) with Doctrine subscriber on persist and sanitization at public render time via `PageBlockProvider`.
- **Flex recipe:** `when@prod` defaults to `allowlist` for production hosts.
- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

### Changed

- **Tests:** 100% PHP coverage for the HTML sanitize stack (`AllowlistPageLayoutHtmlSanitizer`, subscriber, protection wiring).

## [1.0.2] - 2026-08-19

### Fixed

- **Admin reorder:** map `CollectionType::class` to the FormKit `collection` alias in `AbstractPageLayoutFormType::addWithDefaults`, so `PageLayoutReorderType` resolves correctly at runtime.

### Changed

- **CI:** workflow comments now describe the **≥99%** element-coverage gate (no threshold change).
- **Tests:** Rector-aligned class-name references in unit tests (`StringClassNameToClassConstantRector`).

## [1.0.1] - 2026-08-19

### Fixed

- **Demo:** register the `app_logout` route (`/logout`) required by `security.yaml` and `demo/symfony8/templates/base.html.twig`.

### Documentation

- [INSTALLATION.md](INSTALLATION.md) — PHP requirement aligned with `composer.json` (**8.4+**, not 8.2).
- [README.md](../README.md) — coverage badge and QA table reflect the CI gate (**≥99%**).
- [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md) — correct demo routes, form login (`admin` / `admin`), and `/login` / `/logout`.
- [USAGE.md](USAGE.md) — document admin reorder as a Symfony Form collection (`PageLayoutReorderType`).
- [UPGRADING.md](UPGRADING.md) — add `1.0.1` notes.

## [1.0.0] - 2026-08-19

Initial public release of **Page Layout Kit Bundle** (`nowo-tech/page-layout-kit-bundle`).

### Added

- Reusable Symfony page compositor for ordered typed blocks: `hero`, `text`, `cards`, `list`, `cta`, `compare`
- Admin reorder UI for configured page keys such as `home` and `contact`
- Inline CMS modal editing endpoints for supported block types
- Doctrine entities, repositories, and SQL batch loading for public block rendering
- Locale-aware content fallback with default `es` and shipped `es` / `en` configuration defaults
- Public rendering services: `PageBlockProvider`, `PageBlockView`, `PageBlockRegistry`
- Legacy fallback plus `nowo:page-layout:migrate` to convert older page content into typed blocks
- Configurable security: `access_roles`, `access_checker`, `allow_unauthenticated`
- Configurable admin shell: `layout_template`, `css_framework`
- Optional Doctrine `table_prefix`
- Symfony Flex recipe and FrankenPHP demo (`demo/symfony8`, default port `8127`)
- Integrator documentation and Spec Kit baseline

### Changed

- Admin reorder uses `PageLayoutReorderType` and row form types with CSRF (REQ-TWIG-005); no raw HTML position inputs
- CI matrix: PHP **8.4** / **8.5**, Symfony **7.4** / **8.0** / **8.1**; coverage gate **≥99%**
- Removed inline `"version"` from `composer.json` (Packagist resolves versions from tags)

### Security

- Default editor role guard: `ROLE_EDITOR`
- Symfony Security required by default when `allow_unauthenticated` is `false`
- CSRF-protected reorder and update flows
- Inline CMS visibility tied to the access checker through Twig globals
