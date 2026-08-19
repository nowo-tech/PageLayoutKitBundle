# Configuration

All options live under the root key `nowo_page_layout_kit`.

## Table of contents

- [Full YAML tree](#full-yaml-tree)
- [Top-level options](#top-level-options)
- [security](#security)
- [web_ui](#web_ui)
- [doctrine](#doctrine)
- [Twig globals](#twig-globals)
- [Examples](#examples)

## Full YAML tree

```yaml
nowo_page_layout_kit:
    default_locale: es
    locales: [es, en]
    pages: [home, contact]
    security:
        access_roles: [ROLE_EDITOR]
        access_checker: null
        allow_unauthenticated: false
    web_ui:
        layout_template: '@NowoPageLayoutKitBundle/admin/layout.html.twig'
        css_framework: tailwind
    doctrine:
        table_prefix: ''
        connection: default
    html:
        sanitize:
            strategy: none      # none | strip | allowlist | service
            service: null
```

Production (Flex recipe): `when@prod` sets `html.sanitize.strategy: allowlist`.

## html.sanitize

| Key | Default | Description |
| --- | --- | --- |
| `strategy` | `none` | `none` (trusted editors), `strip`, `allowlist`, or `service` |
| `service` | `null` | Host service implementing `PageLayoutHtmlSanitizerInterface` when `strategy: service` |

Sanitization runs on Doctrine persist/update for text/compare/cta block translations and again when serving public layouts via `PageBlockProvider`.

## Top-level options

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `default_locale` | string | `es` | Default locale used for translation fallback and `PageLocales`. |
| `locales` | list<string> | `[es, en]` | Locales exposed to the admin forms and public rendering fallback chain. |
| `pages` | list<string> | `[home, contact]` | Allowed page keys for admin layout management and `PageBlockProvider::getLayout()`. |
| `security` | map | see YAML | Access control for reorder and inline editing routes. |
| `web_ui` | map | see YAML | Admin shell layout and CSS framework hint. |
| `doctrine` | map | see YAML | Bundle table prefixing and connection-related host configuration. |

## security

| Key | Default | Description |
| --- | --- | --- |
| `access_roles` | `[ROLE_EDITOR]` | Any matching role grants admin access when no custom checker is configured. |
| `access_checker` | `null` | Optional service id implementing `PageLayoutKitAccessCheckerInterface`. |
| `allow_unauthenticated` | `false` | When `true`, the bundle uses an allow-all checker. Intended only for trusted demos or internal tools. |

The bundle enforces access on route names beginning with `admin_page_layout` and `admin_page_blocks`.

## web_ui

| Key | Default | Description |
| --- | --- | --- |
| `layout_template` | `@NowoPageLayoutKitBundle/admin/layout.html.twig` | Base layout used by the reorder UI. Point this to your own layout or bridge template to embed the admin screen in host chrome. |
| `css_framework` | `tailwind` | Styling hint exposed to Twig. Allowed values: `bootstrap`, `bootstrap4`, `bootstrap5`, `tabler`, `tailwind`, `foundation`, `custom`, `none`. |

The reorder screen lives at `@NowoPageLayoutKitBundle/admin/layout/index.html.twig` and extends `nowo_page_layout_kit_layout`.

## doctrine

| Key | Default | Description |
| --- | --- | --- |
| `table_prefix` | `''` | Prefix applied to all bundle entity tables through `TablePrefixListener`. |
| `connection` | `default` | Connection name recorded in configuration for host alignment. The current release mainly relies on standard Doctrine mapping plus optional table prefixing. |

Example:

```yaml
nowo_page_layout_kit:
    doctrine:
        table_prefix: 'tenant_a_'
```

## Twig globals

The bundle Twig extension publishes:

| Global | Meaning |
| --- | --- |
| `nowo_page_layout_kit_layout` | Active admin layout template |
| `nowo_page_layout_kit_css_framework` | Selected CSS framework hint |
| `nowo_page_layout_kit_pages` | Configured page keys |
| `nowo_page_layout_kit_default_locale` | Configured default locale |
| `nowo_page_layout_kit_can_edit` | Whether the current user may use inline CMS editing |

## Examples

**Custom pages and locales:**

```yaml
nowo_page_layout_kit:
    default_locale: en
    locales: [en, es, fr]
    pages: [home, contact, landing]
```

**Role-based editor access:**

```yaml
nowo_page_layout_kit:
    security:
        access_roles: [ROLE_EDITOR, ROLE_ADMIN]
```

**Custom access checker service:**

```yaml
nowo_page_layout_kit:
    security:
        access_checker: App\Security\CmsEditorAccessChecker
```

**Host layout integration:**

```yaml
nowo_page_layout_kit:
    web_ui:
        layout_template: 'admin/layout.html.twig'
        css_framework: bootstrap5
```

See also [USAGE.md](USAGE.md) and [SECURITY.md](SECURITY.md).
