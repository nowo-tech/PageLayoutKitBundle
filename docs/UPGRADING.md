# Upgrading

This document describes how to upgrade **Page Layout Kit Bundle** between released versions.

## Table of contents


- [From 1.0.6 to 1.1.0](#from-106-to-110)
- [From 1.0.5 to 1.0.6](#from-105-to-106)
- [From 1.0.4 to 1.0.5](#from-104-to-105)
- [1.0.4](#104)
- [1.0.2](#102)
- [1.0.1](#101)
- [1.0.0](#100)

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
