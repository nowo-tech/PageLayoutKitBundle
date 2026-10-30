# Security

Security considerations for page layout editing, inline CMS modals, and public block rendering.

## Table of contents

- [Threat model](#threat-model)
- [Admin access guard](#admin-access-guard)
- [Inline editing and CSRF](#inline-editing-and-csrf)
- [Rich text rendering](#rich-text-rendering)
- [Operational guidance](#operational-guidance)
- [Logging](#logging)
- [Release security checklist](#release-security-checklist)
- [Secrets](#secrets)
- [REQ-SEC-004 recording](#req-sec-004-recording)

## Threat model

| Risk | Mitigation |
| --- | --- |
| Unauthorized editors reach layout admin or block edit endpoints | `access_roles`, custom `access_checker`, and Symfony Security |
| Misconfigured empty `access_roles` opens the admin to every user | An empty list grants nothing (`ConfigurablePageLayoutKitAccessChecker`); `access_roles: []` without `access_checker` / `allow_unauthenticated` → container compile error (`NowoPageLayoutKitExtension`) |
| Stored XSS through rich text block content | `text`, `cards` and `compare` bodies are rendered with `|raw` **only after** the configured sanitizer ran at render (`PageBlockProvider`, every body / compare text / item body); text, compare and CTA translations are also sanitized on persist. Default `allowlist` = symfony/html-sanitizer with the HTML5 parser (allowlisted elements / attributes, `http(s)` / `mailto` / root-relative links, comments dropped, raw-text and foreign-content elements removed with their content, iframes only `https://` on YouTube / Vimeo hosts with a restricted `allow`). `strip` returns escaped text and never decodes entities. `service` without a service id refuses to compile |
| Open redirect after saving a block | The post-save redirect follows the `Referer` only when it is a root-relative path or has the request's own scheme + host + port; otherwise the first public page / layout admin |
| CSRF on reorder or modal save actions | Symfony forms with CSRF tokens; `CsrfProtectionPass` fails compilation (fail closed) when `security.csrf.token_manager` / `form.type_extension.csrf` is missing or `framework.form.csrf_protection` is false |
| Overly broad demo access | `allow_unauthenticated` defaults to `false`; enable only for trusted demos |
| Shared-database table collisions | Optional `doctrine.table_prefix` |

## Admin access guard

`PageLayoutKitAdminAccessSubscriber` guards the bundle's admin controllers **by class** (every controller in
`Nowo\PageLayoutKitBundle\Controller\Admin\`), whatever the route names are, so importing the routes with a
`name_prefix` or renaming them never bypasses the check. It also guards host controllers that reuse route names
beginning with:

- `admin_page_layout`
- `admin_page_blocks`

The CMS pencil / create buttons (`cms/*.html.twig`) are shown through the same access checker
(`nowo_page_layout_kit_can_edit()`), not a hard-coded role.

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
- Do not remove CSRF handling from overridden forms. The bundle refuses to compile when form CSRF protection is off (`DependencyInjection\Compiler\CsrfProtectionPass`).
- Keep modal edit endpoints behind the same editor policy as the reorder UI.

## Rich text rendering

Several default public block templates render editor-authored HTML with `|raw`, especially for long-form text and compare content. That is intentional for CMS-managed rich text.

**HTML sanitization:** configure `nowo_page_layout_kit.html.sanitize.strategy`:

| Strategy | Behaviour |
| -------- | --------- |
| `none` | Trusted editors only; HTML stored/rendered as-is (opt-in) |
| `allowlist` (default) | `AllowlistPageLayoutHtmlSanitizer` on persist + public render: symfony/html-sanitizer, HTML5 parser, element / attribute allowlist, URL policy (`PageLayoutUrlAttributeSanitizer`) |
| `strip` | Remove all tags and return HTML-escaped text (entities stay encoded; never decoded) |
| `service` | Host `PageLayoutHtmlSanitizerInterface`; the container refuses to compile without `html.sanitize.service` |

Allowlist policy:

| Item | Rule |
| --- | --- |
| Elements | `p br strong b em i u s del h2 h3 h4 ul ol li blockquote code pre a img table thead tbody tr th td hr span div figure figcaption iframe` with `class` (+ the attributes below) |
| Unwrapped | Common structural / phrasing elements (`section`, `article`, `font`, `h1`, `small`, …) are removed and their **sanitized** children kept |
| Dropped with content | Everything else, including `script`, `style`, `template`, `noscript`, `textarea`, `xmp`, `svg`, `math`, `object`, `embed`, forms and custom elements; comments, processing instructions and CDATA |
| Event handlers / `style` attribute | Never kept |
| `a[href]` | `http:`, `https:`, `mailto:` or root-relative `/…` (not `//…`); `rel="noopener noreferrer"` forced |
| `img[src]` | `http(s)://` or root-relative `/…` |
| `iframe` | Kept only with `src` = `https://` on `www.youtube.com`, `youtube.com`, `www.youtube-nocookie.com`, `player.vimeo.com`; `allow` reduced to `accelerometer autoplay clipboard-write encrypted-media fullscreen gyroscope picture-in-picture web-share` (no origin lists); `srcdoc` / handlers dropped |

Flex recipe and PHP defaults use `strategy: allowlist`. Set `none` only for fully trusted staff editors. Demo/dev may still opt into `none`.

Additional guidance:

- Only trusted editors should be able to update rich-text fields.
- Content stored before 2.0.0 / 1.9.1 is re-sanitized at render with the fixed sanitizer; it is rewritten in the
  database the next time the block is saved.
- Template overrides should keep escaping behaviour explicit and reviewed.

## Operational guidance

- Audit which users receive `ROLE_EDITOR` or equivalent custom-checker access.
- Override starter Twig block templates when they reference app-specific routes or components your project does not provide.
- Use `doctrine.table_prefix` when multiple bundles or applications share one schema and naming isolation matters.
- Leave `allow_unauthenticated: false` in production.
- Review demo credentials and never copy demo auth settings directly into production.

## Logging

The only operation the bundle logs is the destructive legacy migration `nowo:page-layout:migrate` (`MigratePageBlocksCommand`), through the optional PSR-3 logger:

| Event | Level | Context |
| --- | --- | --- |
| Migration started / finished / skipped (layout exists, `--if-empty`) | `info` | `bundle: page_layout_kit`, `action: migrate_page_blocks`, `force`, `if_empty` |
| Not run (layout exists, no `--force`) | `warning` | same |
| Failure (exception rethrown) | `error` | same + `exception` (class name only) |

**Never logged:** page content, block payloads, rich-text HTML, editor identities, request data or credentials. Admin HTTP actions are not logged by the bundle; rely on your application's request / audit logging.

## Release security checklist

Before tagging a release, confirm:

| Item | Notes |
| --- | --- |
| `SECURITY.md` | Current and linked from the README |
| Admin access | `ROLE_EDITOR` or custom checker documented and tested |
| Route protection | Host docs cover both `/admin/pages/` and `/admin/page-blocks/` |
| Rich text | `|raw` rendering only after the sanitizer; sanitizer regression tests (Appendix BJ payloads) green |
| Template overrides | No insecure override removes escaping or CSRF protection |
| Demo config | Demo-only shortcuts are clearly separated from production guidance |
| Dependencies | `composer audit` and normal QA checks reviewed |

See also [CONFIGURATION.md](CONFIGURATION.md) and [USAGE.md](USAGE.md).

## Secrets

Never commit application secrets, `.env` files with credentials, or private keys. Use `.env.example` templates only.

## REQ-SEC-004 recording

| Field | Value |
| --- | --- |
| AI audit date | 2026-10-30 |
| Method | Independent static AI security review of `main` 7a91346 (pre-2.0.0) with PoC payloads: sanitizers, `\|raw` block templates, admin guard, CSRF, redirects, modal JS, dist contents, demo |
| Grade | **Pass (good)** after the fixes below (was Pass (conditional) / Medium) |
| Overall risk | **Low** |
| Open Critical / High / Medium | None |
| Medium | M1 `AllowlistPageLayoutHtmlSanitizer` bypasses (unwrapped children never sanitized, `javascript:` iframe on an allowlisted host, comment / HTML4-vs-HTML5 parsing differentials, raw-text elements; affects 1.0.4–1.9.0); M2 `strip` decoded entities after `strip_tags()` (1.0.4+) → **Fixed in 2.0.0** (backported to 1.9.1) |
| Low | L1 open redirect through the `Referer` after saving a block; L2 `strategy: service` without a service fell back to no sanitizing; L3 tests / demo / tooling shipped in dist archives → **Fixed in 2.0.0** |
| Info | CMS buttons use the access checker instead of `is_granted('ROLE_EDITOR')`; modal URL segments `encodeURIComponent`-encoded; `admin_page_layout` limited to GET / POST; demo bound to 127.0.0.1. Open: the deprecated `nowo_page_layout_kit_can_edit` Twig **global** is still frozen per environment in workers without `services_resetter` (use the function); starter `hero` / `cta` templates reference host routes |
| Residuals | `strategy: none` renders editor HTML as-is (opt-in, trusted editors only); keep host `access_control` on admin paths |
| Record | Monorepo `BUNDLES_SECURITY_ANALYSIS.md`, Appendix BJ |
