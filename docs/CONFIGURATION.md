# Configuration

All options live under the root key `nowo_page_layout_kit`.

## Table of contents

- [Full YAML tree](#full-yaml-tree)
- [html.sanitize](#htmlsanitize)
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
        layout_template: null    # null = nowo_ui_kit.layouts.admin (REQ-UI-003)
        page_routes: {}          # e.g. { home: app_home, contact: app_contact }
        templates: {}            # per-page overrides: { layout_index: ..., block_edit: ... }
        # css_framework: deprecated since 2.1 (the pages use nowo_ui_kit.css_framework)
    doctrine:
        table_prefix: ''
        connection: default
    html:
        sanitize:
            strategy: allowlist # none | strip | allowlist | service
            service: null
```

Production (Flex recipe): `html.sanitize.strategy: allowlist` (same as the PHP default).

## html.sanitize

| Key | Default | Description |
| --- | --- | --- |
| `strategy` | `allowlist` | `allowlist` (default; symfony/html-sanitizer with the HTML5 parser), `strip` (all tags removed, HTML-escaped text), `service`, or `none` (trusted editors only) |
| `service` | `null` | Host service implementing `PageLayoutHtmlSanitizerInterface`. **Required** when `strategy: service`: the container refuses to compile without it |

Sanitization runs on Doctrine persist/update for text/compare/cta block translations and again when serving public layouts via `PageBlockProvider`. The allowlist policy (elements, attributes, link / image / iframe rules) is in [SECURITY.md](SECURITY.md#rich-text-rendering).

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
| `access_roles` | `[ROLE_EDITOR]` | Any matching role grants admin access when no custom checker is configured. An empty list **denies** (never "everyone") and fails container compilation unless `access_checker` or `allow_unauthenticated` is set. |
| `access_checker` | `null` | Optional service id implementing `PageLayoutKitAccessCheckerInterface`. |
| `allow_unauthenticated` | `false` | When `true`, the bundle uses an allow-all checker. Intended only for trusted demos or internal tools. |

The bundle enforces access on route names beginning with `admin_page_layout` and `admin_page_blocks`.

The container also refuses to compile without form CSRF protection (`security.csrf.token_manager` +
`form.type_extension.csrf`, `framework.form.csrf_protection` not `false`): block reorder and block edit rely on it.

## web_ui

| Key | Default | Description |
| --- | --- | --- |
| `layout_template` | `null` | Layout the admin pages (UiKit scaffolds, REQ-UI-003) extend. `null` = `nowo_ui_kit.layouts.admin`: set your admin layout (or a one-file bridge rendering the UiKit canonical blocks) once in UiKit for every bundle. A layout here must render the canonical blocks; a 2.0 layout that renders `{% block admin %}` is wrapped in a deprecated bridge (see [TEMPLATES.md](TEMPLATES.md#layout-bridge-20-layouts)). The 2.0 default `@NowoPageLayoutKitBundle/admin/layout.html.twig` means `null`. |
| `css_framework` | `null` | **Deprecated since 2.1** (the admin pages take the CSS stack from `nowo_ui_kit.css_framework`). Only feeds the deprecated `nowo_page_layout_kit_css_framework` global (`null` → `tailwind`). Allowed values: `bootstrap`, `bootstrap4`, `bootstrap5`, `tabler`, `tailwind`, `foundation`, `custom`, `none`. |
| `templates` | `{}` | Per-page template overrides (REQ-UI-003): `layout_index` (reorder screen), `block_edit` (block page). Prefer a thin `{% extends '@!NowoPageLayoutKitBundle/...' %}` overriding one data block. Unknown ids fail the container compilation. |
| `page_routes` | `{}` | Public route name per page key, used for the "View page" link on the reorder screen and as redirect target after saving a block when no `Referer` is sent. Unmapped keys try a route with the same name as the page key (`home` → route `home`). When no route resolves (or it needs parameters) the link is hidden; an empty string disables it. |

The reorder screen (`@NowoPageLayoutKitBundle/admin/layout/index.html.twig`) extends the UiKit form scaffold and the
block page (`admin/blocks/edit_stub.html.twig`) the show scaffold; see [TEMPLATES.md](TEMPLATES.md).

## doctrine

| Key | Default | Description |
| --- | --- | --- |
| `table_prefix` | `''` | Prefix applied to all bundle entity tables through `TablePrefixListener`. The batch SQL loader (`PageBlockSqlRepository`) resolves table names from Doctrine metadata, so the prefix also applies to native queries. Column names assume Symfony's default underscore naming strategy. |
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
| `nowo_page_layout_kit_layout` | **Deprecated since 2.1** (the pages extend UiKit scaffolds). `web_ui.layout_template`, else the deprecated `@NowoPageLayoutKitBundle/admin/layout.html.twig` (extends the UiKit admin layout) for copies of 2.0 pages |
| `nowo_page_layout_kit_css_framework` | **Deprecated since 2.1** (read `nowo_ui_kit.css_framework`). `web_ui.css_framework`, else `tailwind` |
| `nowo_page_layout_kit_pages` | Configured page keys |
| `nowo_page_layout_kit_default_locale` | Configured default locale |
| `nowo_page_layout_kit_can_edit` | **Deprecated**, use `nowo_page_layout_kit_can_edit()`. Whether the current user may use inline CMS editing |

Twig function:

| Function | Meaning |
| --- | --- |
| `nowo_page_layout_kit_can_edit()` | Whether the current user may use inline CMS editing (evaluated per call) |

Twig resolves globals once per environment. In long-running workers (FrankenPHP / RoadRunner) without `services_resetter` (or on Twig < 3.14, which has no `Environment::resetGlobals()`), the `nowo_page_layout_kit_can_edit` global keeps the value of the first user rendered by the worker; the function is evaluated on every call.

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
# Once for every UiKit-based bundle (REQ-UI-003): your layout or bridge renders the UiKit canonical blocks.
nowo_ui_kit:
    css_framework: bootstrap5
    layouts:
        admin: 'admin/layout.html.twig'
```

See also [USAGE.md](USAGE.md) and [SECURITY.md](SECURITY.md).
