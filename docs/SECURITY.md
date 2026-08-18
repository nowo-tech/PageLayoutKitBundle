# Security

Security considerations for page layout editing, inline CMS modals, and public block rendering.

## Table of contents

- [Threat model](#threat-model)
- [Admin access guard](#admin-access-guard)
- [Inline editing and CSRF](#inline-editing-and-csrf)
- [Rich text rendering](#rich-text-rendering)
- [Operational guidance](#operational-guidance)
- [Release security checklist](#release-security-checklist)

## Threat model

| Risk | Mitigation |
| --- | --- |
| Unauthorized editors reach layout admin or block edit endpoints | `access_roles`, custom `access_checker`, and Symfony Security |
| Stored XSS through rich text block content | Default Twig auto-escaping where possible; review templates that intentionally use `|raw` |
| CSRF on reorder or modal save actions | Symfony forms and CSRF-protected admin flows |
| Overly broad demo access | `allow_unauthenticated` defaults to `false`; enable only for trusted demos |
| Shared-database table collisions | Optional `doctrine.table_prefix` |

## Admin access guard

The bundle protects route names beginning with:

- `admin_page_layout`
- `admin_page_blocks`

Default configuration:

```yaml
nowo_page_layout_kit:
    security:
        access_roles: [ROLE_EDITOR]
        allow_unauthenticated: false
```

Recommended host access control:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin/pages/, roles: ROLE_EDITOR }
        - { path: ^/admin/page-blocks/, roles: ROLE_EDITOR }
```

If your project needs more context-aware rules, implement `PageLayoutKitAccessCheckerInterface` and configure `security.access_checker`.

## Inline editing and CSRF

The reorder form and modal update forms are submitted through Symfony forms, not ad hoc query-string actions. Keep that protection intact if you override admin templates.

Recommendations:

- Do not expose admin routes outside authenticated editor sessions.
- Do not remove CSRF handling from overridden forms.
- Keep modal edit endpoints behind the same editor policy as the reorder UI.

## Rich text rendering

Several default public block templates render editor-authored HTML with `|raw`, especially for long-form text and compare content. That is intentional for CMS-managed rich text, but it means:

- Only trusted editors should be able to update those fields.
- Hosts should sanitize content before storage or before rendering if untrusted HTML is possible.
- Template overrides should keep escaping behavior explicit and reviewed.

## Operational guidance

- Audit which users receive `ROLE_EDITOR` or equivalent custom-checker access.
- Override starter Twig block templates when they reference app-specific routes or components your project does not provide.
- Use `doctrine.table_prefix` when multiple bundles or applications share one schema and naming isolation matters.
- Leave `allow_unauthenticated: false` in production.
- Review demo credentials and never copy demo auth settings directly into production.

## Release security checklist

Before tagging a release, confirm:

| Item | Notes |
| --- | --- |
| `SECURITY.md` | Current and linked from the README |
| Admin access | `ROLE_EDITOR` or custom checker documented and tested |
| Route protection | Host docs cover both `/admin/pages/` and `/admin/page-blocks/` |
| Rich text | `|raw` rendering is documented and reviewed |
| Template overrides | No insecure override removes escaping or CSRF protection |
| Demo config | Demo-only shortcuts are clearly separated from production guidance |
| Dependencies | `composer audit` and normal QA checks reviewed |

See also [CONFIGURATION.md](CONFIGURATION.md) and [USAGE.md](USAGE.md).
