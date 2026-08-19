# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.0.2] - 2026-08-19](#102---2026-08-19)
- [[1.0.1] - 2026-08-19](#101---2026-08-19)
- [[1.0.0] - 2026-08-19](#100---2026-08-19)

## [Unreleased]

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
