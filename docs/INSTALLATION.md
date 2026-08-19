# Installation

Install **Page Layout Kit Bundle** in a Symfony 7.4 or 8 application with Doctrine ORM, Twig, Security, FormKit, and UiKit.

## Table of contents

- [Requirements](#requirements)
- [Composer](#composer)
- [Symfony Flex recipe](#symfony-flex-recipe)
- [Manual registration](#manual-registration)
- [Routes](#routes)
- [Database schema](#database-schema)
- [Security](#security)
- [Verify](#verify)
- [Demo application](#demo-application)
- [Twig Extra Bundle](#twig-extra-bundle)

## Requirements

| Component | Version |
| --- | --- |
| PHP | 8.4 - 8.5 |
| Symfony | ^7.4 or ^8.0 |
| Doctrine Bundle | ^2.10 or ^3.0 |
| Doctrine ORM | ^2.15 or ^3.0 |
| Twig Bundle | ^7.4 or ^8.0 |
| Security Bundle | ^7.4 or ^8.0 |
| FormKitBundle | `nowo-tech/form-kit-bundle` ^2.4 |
| UiKitBundle | `nowo-tech/ui-kit-bundle` ^1.4 |
| Twig Extra | `twig/extra-bundle` and `twig/string-extra` |

## Composer

```bash
composer require nowo-tech/page-layout-kit-bundle:^1.0
composer require twig/extra-bundle twig/string-extra
```

`twig/extra-bundle` is required because the bundle ships Twig templates that expect Twig Extra to be enabled in the host application.

## Symfony Flex recipe

When the Flex recipe is available, it copies:

- `config/packages/nowo_page_layout_kit.yaml`
- `config/routes/nowo_page_layout_kit.yaml`

Recipe source in this repository:

`.symfony/recipe/nowo-tech/page-layout-kit-bundle/1.0/`

## Manual registration

If Flex is unavailable, register the bundles in `config/bundles.php`:

```php
Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
Nowo\FormKitBundle\NowoFormKitBundle::class => ['all' => true],
Nowo\UiKitBundle\NowoUiKitBundle::class => ['all' => true],
Nowo\PageLayoutKitBundle\NowoPageLayoutKitBundle::class => ['all' => true],
```

Then create `config/packages/nowo_page_layout_kit.yaml`:

```yaml
nowo_page_layout_kit:
    default_locale: es
    locales: [es, en]
    pages: [home, contact]
    security:
        access_roles: [ROLE_EDITOR]
        allow_unauthenticated: false
    web_ui:
        layout_template: '@NowoPageLayoutKitBundle/admin/layout.html.twig'
        css_framework: tailwind
    doctrine:
        table_prefix: ''
        connection: default
```

## Routes

Import the bundle routes:

```yaml
# config/routes/nowo_page_layout_kit.yaml
nowo_page_layout_kit:
    resource: '@NowoPageLayoutKitBundle/Resources/config/routing.yaml'
```

This exposes the admin layout and inline block editing routes handled by the bundle controllers.

## Database schema

The bundle registers Doctrine mappings for page layout entries, typed blocks, translations, and list/card items.

Generate and run a migration:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Or update the schema directly in development:

```bash
php bin/console doctrine:schema:update --force
```

If you set `doctrine.table_prefix`, the bundle prefixes its own entity tables automatically through a Doctrine metadata listener.

## Security

When `security.allow_unauthenticated` is `false` (the default), install and configure `symfony/security-bundle`.

Recommended host `access_control`:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin/pages/, roles: ROLE_EDITOR }
        - { path: ^/admin/page-blocks/, roles: ROLE_EDITOR }
```

You can replace role-based access with a custom service via `security.access_checker`.

## Verify

1. Open `/admin/pages/home/layout` as an authorized editor.
2. Confirm the page loads and shows the configured page layout entries.
3. Open a public page that uses `PageBlockProvider` and confirm blocks render in order.
4. If inline CMS editing is enabled in the page template, verify the modal edit flow works.

## Demo application

Clone this repository and run the FrankenPHP demo:

```bash
make -C demo/symfony8 up
```

Default URL: `http://localhost:8127`

See [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).

## Twig Extra Bundle

If your application does not already provide Twig Extra, install it explicitly:

```bash
composer require twig/extra-bundle twig/string-extra
```

Flex usually registers `Twig\Extra\TwigExtraBundle\TwigExtraBundle` automatically. If not, add it manually to `config/bundles.php`.
