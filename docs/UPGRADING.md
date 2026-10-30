# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 1.9.2](#to-192)
- [To 1.9.1](#to-191)
- [To 1.9.0](#to-190)
- [To 1.8.1](#to-181)
- [To 1.8.0](#to-180)
- [To 1.7.0](#to-170)
- [To 1.6.1](#to-161)
- [To 1.6.0](#to-160)
- [To 1.5.1](#to-151)
- [To 1.5.0](#to-150)
- [To 1.4.0](#to-140)
- [To 1.3.0](#to-130)
- [To 1.2.1](#to-121)
- [To 1.2.0](#to-120)
- [To 1.1.1](#to-111)
- [From 1.0.6 to 1.1.0](#from-106-to-110)
- [From 1.0.5 to 1.0.6](#from-105-to-106)
- [From 1.0.4 to 1.0.5](#from-104-to-105)
- [1.0.4](#104)
- [1.0.2](#102)
- [1.0.1](#101)
- [1.0.0](#100)

## Unreleased

## To 1.9.2

From **1.9.1**:

```bash
composer update nowo-tech/page-layout-kit-bundle
```

No action needed when you import `@NowoPageLayoutKitBundle/Resources/config/routing.yaml` (the documented setup). If on Symfony >= 7.3 the layout admin routes appeared without that import (through the default `controllers: { resource: routing.controllers }` of `config/routes.yaml`), they are gone now: import the bundle routes explicitly (see [INSTALLATION](INSTALLATION.md)).

## To 1.9.1

From **1.9.0**:

```bash
composer update nowo-tech/page-layout-kit-bundle
```

From **1.9.0** (security patch, no API change):

```bash
composer update nowo-tech/page-layout-kit-bundle
```

- **New dependency `symfony/html-sanitizer`** (`^7.4.12 || ^8.0.12`, versions with the CVE-2026-45066 fix),
  installed by Composer.
- **`allowlist` output** keeps the same elements and attributes but is canonical HTML (`<br />`, attribute values
  and text entity-encoded, e.g. `mailto:a&#64;b.test`); browsers render it the same. Content that relied on the
  bypasses (event handlers, comments, `<style>`, `javascript:` / non-allowlisted iframes, protocol-relative links) is
  removed. Children of unknown or custom elements are dropped (structural elements such as `section`, `article`,
  `font`, `h1`, `small` are still unwrapped). Iframes need `https://` on an allowlisted video host; their `allow`
  attribute is reduced to player permissions. Stored content is re-sanitized at render and rewritten in the
  database the next time each block is saved.
- **Field contracts:** `body` / compare texts are sanitized HTML (print `|raw`; this includes the CTA `text` the
  starter `blocks/cta.html.twig` passes to your `components/cta.html.twig`). Card / list item bodies are plain text
  and are no longer HTML-sanitized: print them escaped (`{{ item.body }}`), never `|raw`. `pageMeta()['description']`
  falls back to the plain text of the contact header body (print escaped).
- **`strategy: service` without `service`** now uses the allowlist sanitizer instead of none: set
  `html.sanitize.service`, or choose a strategy explicitly.
- **`strip` output** is HTML-escaped text (`Tom & Jerry` → `Tom &amp; Jerry`, rendered identically through `|raw`).
  Code that read the stored value as plain text must decode it.

## To 1.9.0

From **1.8.1**:

```bash
composer update nowo-tech/page-layout-kit-bundle
```

## To 1.8.1

From **1.8.0**:

```bash
composer update nowo-tech/page-layout-kit-bundle
```

## To 1.8.0

From **1.7.0**:

```bash
composer update nowo-tech/page-layout-kit-bundle
```

```bash
composer update nowo-tech/page-layout-kit-bundle
```

**Behaviour changes (security):**

- `nowo_page_layout_kit.security.access_roles: []` used to allow everyone; it now fails container compilation (and
  an empty list denies). Configure a role, set `security.access_checker`, or — trusted demos only —
  `security.allow_unauthenticated: true`.
- The container no longer compiles without form CSRF protection. Keep `framework.csrf_protection` and
  `framework.form.csrf_protection` enabled; `symfony/security-csrf` is now a required dependency.
- Default configuration (`[ROLE_EDITOR]`, CSRF on) is unaffected.

## To 1.7.0

From **1.6.1**:

```bash
composer update nowo-tech/page-layout-kit-bundle
```

- New runtime requirement `psr/log` (interfaces only); the migration command autowires your logger.

## To 1.6.1

From **1.6.0**:

```bash
composer update nowo-tech/page-layout-kit-bundle
```

## To 1.6.0

From **1.5.1**:

```bash
composer update nowo-tech/page-layout-kit-bundle
```

From **1.5.1**:

```bash
composer update nowo-tech/page-layout-kit-bundle nowo-tech/form-kit-bundle nowo-tech/ui-kit-bundle -W
```

Requires **FormKit ^2.13** and **UiKit ^2.5.1** (UiKit 1.x dropped).

What hosts must do / may notice:

- **Delete your `locale-tabs` Stimulus controller** if it only served this modal (e.g. `assets/controllers/locale_tabs_controller.js`
  and its registration): the modal no longer emits `data-controller="locale-tabs"`. The tabs are driven by UiKit's
  `nowo-ui-tabs.js`, loaded by `cms/modal_shell.html.twig` (and `admin/layout.html.twig`); a host that loads it too is
  fine (idempotent). Load UiKit's `nowo-ui.css` on pages with the modal for the tab styling.
- **Copied `cms_modal_controller.ts`:** re-copy it. CKEditor now remounts on `nowo-ui:tabs:change`
  (`event.detail.panel`), not `locale-tabs:changed`; panels are `[data-nowo-ui-tabs-panel]` with `data-active` instead
  of `[data-locale-panel].is-active`.
- **Removed partial:** `admin/_locale_tabs.html.twig`. Delete `templates/bundles/NowoPageLayoutKitBundle/admin/_locale_tabs.html.twig`
  overrides; customise the look with UiKit theme slots (`tabs`, `tab`, `tab_active`, `tab_panel`) or
  `nowo_ui_kit.theme.partials.locale_tabs`, or override `admin/_modal_form.html.twig` (see [TEMPLATES](TEMPLATES.md)).
- **Changed DOM ids / classes:** `cms-locale-tab-<locale>` → `tab-cms-locale-<locale>`, `cms-locale-panel-<locale>` →
  `tab-cms-locale-<locale>-panel`, root `#cms-locale` (`[data-nowo-ui-locale-tabs]`); `admin-locale-tabs*`, `is-active`,
  `data-locale-panel`, `data-locale-tabs-*` are gone.
- **Translation key removed:** `admin.locale_tabs.label` (the tab list label comes from UiKit `locale_tabs.label`).

## To 1.5.1

From **1.5.0**:

```bash
composer update nowo-tech/page-layout-kit-bundle
```

## To 1.5.0

From **1.4.0** — self-contained admin modal templates and translated block forms

```bash
composer update nowo-tech/page-layout-kit-bundle
```

- Page layout kit form types no longer render FormKit's convention `{form}.{field}.help` / `.placeholder` keys implicitly. If your app translated any of those keys in its own `NowoPageLayoutKitBundle` catalogue, pass `help` / `placeholder` explicitly through a form type extension. The block edit field labels are now shipped by the bundle; app overrides of the same keys keep working.
- The block edit modal no longer includes your app's `components/admin/_locale_tabs.html.twig` or applies `form/auth_theme.html.twig`. To keep a custom look, override `templates/bundles/NowoPageLayoutKitBundle/admin/_locale_tabs.html.twig` (or `admin/_modal_form.html.twig`), or register your form theme globally in `twig.form_themes`.

## To 1.4.0

From **1.3.0** — translated block edit stub with resolved links; real translations for all locales.

```bash
composer update nowo-tech/page-layout-kit-bundle
```

- The block edit page now extends `web_ui.layout_template` instead of a host `admin/layout.html.twig`; if you overrode `@NowoPageLayoutKitBundle/admin/blocks/edit_stub.html.twig`, note the new variables `page_key`, `back_url` and `view_page_url` (both URLs may be null).

## To 1.3.0

From **1.2.1** — fixes - table_prefix honoured in native SQL, no hard-coded host route, 404 for unknown block types.

```bash
composer update nowo-tech/page-layout-kit-bundle
```

- Hosts using `doctrine.table_prefix` now get block content (the native queries used to fail).
- The admin "View page" link uses the new `web_ui.page_routes` map (or a route named like the page key) and is hidden when no route exists, instead of requiring a host route named `home`.

## To 1.2.1

From **1.2.0** — internal fix and dependency refresh.

```bash
composer update nowo-tech/page-layout-kit-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.2.0

From **1.1.1** — default HTML sanitize allowlist; Doctrine `SortDirection`.

```bash
composer update nowo-tech/page-layout-kit-bundle
php bin/console cache:clear
```

- Default `html.sanitize.strategy` is **`allowlist`**. Hosts that need unsanitized `|raw` CMS HTML must set `strategy: none` explicitly.
- Ensure `doctrine/orm` is `^3.7` (SortDirection).

## To 1.1.1

From **1.1.0** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/page-layout-kit-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

This document describes how to upgrade **Page Layout Kit Bundle** between released versions.

## From 1.0.6 to 1.1.0

No breaking changes. Recommended steps for FrankenPHP / RoadRunner workers that disable kernel reset / `services_resetter`:

```bash
composer update nowo-tech/page-layout-kit-bundle
```

- **Twig global `nowo_page_layout_kit_can_edit` is deprecated.** In template overrides replace `{% if nowo_page_layout_kit_can_edit %}` with `{% if nowo_page_layout_kit_can_edit() %}`. The global keeps the first user's value for the lifetime of a worker when `services_resetter` does not run.
- `PageBlockProvider::getLayout()` no longer memoizes outside an HTTP request (CLI commands query on every call).
- **Static `PageLocales` accessors are deprecated since 1.1.0** (`PageLocales::bind()`, `PageLocales::default()`, `PageLocales::all()`; removed in 2.0). They keep working because the bundle still binds the instance in `boot()`. Inject the service instead:

  ```php
  public function __construct(private readonly PageLocales $pageLocales) {}

  $default = $this->pageLocales->getDefault(); // was PageLocales::default()
  $locales = $this->pageLocales->getAll();     // was PageLocales::all()
  ```

  When calling `getTranslationOrFallback()`, `ensureTranslations()` or `toArray()` on block entities, pass the injected instance as the last argument (`$block->ensureTranslations($this->pageLocales)`, `$block->toArray($locale, $this->pageLocales)`) so no static lookup happens. If you instantiate `PageBlockSqlRepository`, `PageBlockMigrator`, `PageBlockEditController`, `PageCardsBlockInlineModalType` or `PageListBlockInlineModalType` manually (e.g. in tests), pass the `PageLocales` instance as the new last constructor argument; without it they fall back to the static binding. Tests that only called `PageLocales::bind()` for these classes can inject the instance instead.

See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md) for the full scenario A/B notes.

## From 1.0.5 to 1.0.6

No breaking changes. **No application upgrade steps.**

```bash
composer update nowo-tech/page-layout-kit-bundle
```

## From 1.0.4 to 1.0.5

No breaking changes. **No application upgrade steps.**

```bash
composer update nowo-tech/page-layout-kit-bundle
```

## 1.0.4

Security patch: HTML sanitization for rich-text CMS blocks. **Review production config** if you store editor HTML in `text` or `compare` blocks.

```bash
composer update nowo-tech/page-layout-kit-bundle
php bin/console cache:clear
```

Recommended production configuration (also shipped in the Flex recipe under `when@prod`):

```yaml
# config/packages/prod/nowo_page_layout_kit.yaml
nowo_page_layout_kit:
    html:
        sanitize:
            strategy: allowlist
```

If you already trust every editor and rely on Twig escaping elsewhere, you may keep `strategy: none` — see [SECURITY.md](SECURITY.md).

## 1.0.2

Patch release: fixes admin reorder forms when using FormKit collection fields. **No integrator upgrade steps.**

```bash
composer update nowo-tech/page-layout-kit-bundle
```

If admin reorder at `/admin/pages/{pageKey}/layout` failed with a FormKit type resolution error on **1.0.1**, upgrade to **1.0.2**.

## 1.0.1

Patch release: demo logout route fix and documentation corrections only. **No integrator upgrade steps.**

```bash
composer update nowo-tech/page-layout-kit-bundle
```

If you run the FrankenPHP demo from this repository, pull latest `main` and restart the demo stack.

## 1.0.0

This is the first public release of `nowo-tech/page-layout-kit-bundle`, so there is no earlier upgrade path.

Install it with:

```bash
composer require nowo-tech/page-layout-kit-bundle:^1.0
composer require twig/extra-bundle twig/string-extra
```

Then:

1. Register the bundle and its dependencies if Flex does not do it for you.
2. Import `config/routes/nowo_page_layout_kit.yaml`.
3. Apply the Doctrine schema changes.
4. Configure Security for `/admin/pages/*/layout` and `/admin/page-blocks/*`.
5. Override Twig block templates as needed for your own routes and design system.

When wiring logout in your host app, expose a route named `app_logout` (or point `security.firewalls.*.logout.path` at your route name). Symfony intercepts the controller; see the demo `DemoController::logout()` for the usual pattern.

See [INSTALLATION.md](INSTALLATION.md), [CONFIGURATION.md](CONFIGURATION.md), and [USAGE.md](USAGE.md).
