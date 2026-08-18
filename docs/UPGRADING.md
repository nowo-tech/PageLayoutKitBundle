# Upgrading

This document describes how to upgrade **Page Layout Kit Bundle** between released versions.

## Table of contents

- [1.0.0](#100)
- [Future releases](#future-releases)

## 1.0.0

This is the first public release of `nowo-tech/page-layout-kit-bundle`, so there is no earlier upgrade path.

Install it with:

```bash
composer require nowo-tech/page-layout-kit-bundle
composer require twig/extra-bundle twig/string-extra
```

Then:

1. Register the bundle and its dependencies if Flex does not do it for you.
2. Import `config/routes/nowo_page_layout_kit.yaml`.
3. Apply the Doctrine schema changes.
4. Configure Security for `/admin/pages/*/layout` and `/admin/page-blocks/*`.
5. Override Twig block templates as needed for your own routes and design system.

See [INSTALLATION.md](INSTALLATION.md), [CONFIGURATION.md](CONFIGURATION.md), and [USAGE.md](USAGE.md).

## Future releases

Breaking changes and migration notes will be listed here under a new version heading.
