# Upgrading

This document describes how to upgrade **Page Layout Kit Bundle** between released versions.

## Table of contents

- [Unreleased](#unreleased)
- [1.0.4](#104)
- [1.0.2](#102)
- [1.0.1](#101)
- [1.0.0](#100)

## Unreleased

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
