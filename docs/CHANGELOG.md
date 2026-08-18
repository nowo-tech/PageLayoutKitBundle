# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[1.0.0] - 2026-08-18](#100---2026-08-18)

## [1.0.0] - 2026-08-18

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

### Security

- Default editor role guard: `ROLE_EDITOR`
- Symfony Security required by default when `allow_unauthenticated` is `false`
- CSRF-protected reorder and update flows
- Inline CMS visibility tied to the access checker through Twig globals
