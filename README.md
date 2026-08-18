# Page Layout Kit Bundle

[![CI](https://github.com/nowo-tech/PageLayoutKitBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/PageLayoutKitBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/page-layout-kit-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/page-layout-kit-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/page-layout-kit-bundle.svg)](https://packagist.org/packages/nowo-tech/page-layout-kit-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7.4%2B%20%7C%208.x-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/PageLayoutKitBundle.svg?style=social&label=Star)](https://github.com/nowo-tech/PageLayoutKitBundle) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** Give it a star on GitHub! It helps us maintain and improve the project.

**Reusable Symfony page compositor** for ordered, typed content blocks (`hero`, `text`, `cards`, `list`, `cta`, `compare`) with a secured admin reorder UI, inline CMS modals, Doctrine persistence, and Twig-based public rendering.

> Compatible with **Symfony 7.4+ and 8.x** on **PHP 8.4+**

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**.

## What is this?

Page Layout Kit Bundle gives Symfony applications a reusable page-layout layer backed by Doctrine. Editors can manage ordered blocks for configured page keys such as `home` and `contact`, reorder them in the admin UI, and edit supported blocks through inline CMS modals. Public pages consume the resolved layout through services and Twig templates.

## Features

- Ordered typed blocks: `hero`, `text`, `cards`, `list`, `cta`, `compare`
- Configurable page keys and locales with default fallback (`es`, `en` by default)
- Admin reorder UI at `/admin/pages/{pageKey}/layout`
- Inline modal editing endpoints for supported block types
- Public rendering via `PageBlockProvider`, `PageBlockView`, and Twig templates
- Legacy fallback and migration command for pre-block JSON/content sources
- Doctrine entities, repositories, and optional table prefixing
- Configurable access guard: roles, custom checker, or demo-only unauthenticated mode
- Twig namespace `NowoPageLayoutKitBundle` with host override precedence
- Symfony Flex recipe and FrankenPHP demo in `demo/symfony8`

## Quick start

```bash
composer require nowo-tech/page-layout-kit-bundle
composer require twig/extra-bundle twig/string-extra
```

```yaml
# config/packages/nowo_page_layout_kit.yaml
nowo_page_layout_kit:
    default_locale: es
    locales: [es, en]
    pages: [home, contact]
    security:
        access_roles: [ROLE_EDITOR]
```

```yaml
# config/routes/nowo_page_layout_kit.yaml
nowo_page_layout_kit:
    resource: '@NowoPageLayoutKitBundle/Resources/config/routing.yaml'
```

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Admin reorder UI: `/admin/pages/home/layout` and `/admin/pages/contact/layout`.

## Development

```bash
make up
make test
make phpstan
make -C demo/symfony8 up
make demo-smoke
```

Demo default URL: `http://localhost:8127`.

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release process](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [GitHub Actions CI requirements](docs/GITHUB_CI.md)
- [Demo with FrankenPHP](docs/DEMO-FRANKENPHP.md)

## Tests and coverage

| Area | Status | Command |
| --- | --- | --- |
| PHP `src/` coverage target | 100% | `make test-coverage-100` |
| Unit and bundle QA | Enabled | `make test` |
| Full release checks | Enabled | `make release-check` |

```bash
make test
make test-coverage
make test-coverage-100
make release-check
```

## License

MIT — see [LICENSE](LICENSE).
