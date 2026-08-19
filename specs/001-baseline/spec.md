# Baseline specification — Page Layout Kit Bundle

**Package:** `nowo-tech/page-layout-kit-bundle`  
**Namespace:** `Nowo\PageLayoutKitBundle`  
**Bundle class:** `Nowo\PageLayoutKitBundle\NowoPageLayoutKitBundle`  
**Config alias:** `nowo_page_layout_kit`  
**Status:** 1.0.4 baseline (HTML sanitize allowlist)

## Overview

Page Layout Kit Bundle is a reusable Symfony page compositor backed by Doctrine. It models pages as ordered references to typed content blocks (`hero`, `text`, `cards`, `list`, `cta`, `compare`), provides an editor-facing reorder UI, supports inline CMS modal editing, resolves locale-aware public layouts, and can migrate older page-content sources into the new typed model.

## User scenarios (`US-*`)

### US-01 — Reorder page layouts (Priority: P1)

As an editor, I reorder the blocks assigned to a page such as `home` or `contact` so the public page renders sections in the intended order.

### US-02 — Edit blocks inline (Priority: P1)

As an editor, I open an inline modal from a public page or admin action and edit the selected block without manually touching database rows.

### US-03 — Render public pages from typed blocks (Priority: P1)

As a developer, I resolve an ordered layout for a page and locale, then render each block through Twig.

### US-04 — Fallback to legacy content (Priority: P2)

As a maintainer, I keep existing pages working even before the typed layout is fully migrated, using a legacy content provider as a fallback.

### US-05 — Migrate legacy content (Priority: P2)

As a maintainer, I run a command that converts older page content into Doctrine-backed typed blocks and layout entries.

### US-06 — Adapt bundle behavior by configuration (Priority: P2)

As an integrator, I configure locales, page keys, editor access, admin shell layout, CSS framework hint, and Doctrine table prefixing.

### US-07 — Validate real boot behavior (Priority: P3)

As a maintainer, I boot the Symfony 8 FrankenPHP demo and run the QA workflow to prove the bundle works in a real host application.

## Functional requirements (`FR-*`)

### Core composition (`FR-CMP-*`)

| ID | Requirement |
| --- | --- |
| FR-CMP-001 | A page layout is an ordered list of `PageLayoutEntry` rows keyed by `pageKey` |
| FR-CMP-002 | Each layout entry references one typed block and stores position plus enabled state |
| FR-CMP-003 | Supported block types are `hero`, `text`, `cards`, `list`, `cta`, and `compare` |

### Public rendering (`FR-REN-*`)

| ID | Requirement |
| --- | --- |
| FR-REN-001 | `PageBlockProvider::getLayout()` returns ordered `PageBlockView` objects for a page and locale |
| FR-REN-002 | Public rendering uses Twig templates under `@NowoPageLayoutKitBundle/blocks/` |
| FR-REN-003 | `PageBlockProvider::pageMeta()` exposes title/description from the resolved layout |
| FR-REN-004 | SQL batch loading avoids per-block N+1 queries for public rendering |

### Admin UI and inline editing (`FR-ADM-*`)

| ID | Requirement |
| --- | --- |
| FR-ADM-001 | Editors can reorder layout entries from `/admin/pages/{pageKey}/layout` |
| FR-ADM-002 | Supported blocks can be edited through inline modal endpoints under `/admin/page-blocks/...` |
| FR-ADM-003 | Reorder and modal update flows are CSRF-protected |

### Legacy compatibility (`FR-LEG-*`)

| ID | Requirement |
| --- | --- |
| FR-LEG-001 | A legacy content provider may supply fallback content when typed layout entries are missing |
| FR-LEG-002 | `nowo:page-layout:migrate` converts legacy content for `home` and `contact` into typed blocks |

### Security (`FR-SEC-*`)

| ID | Requirement |
| --- | --- |
| FR-SEC-001 | Admin routes are gated by a role-based or custom access checker |
| FR-SEC-002 | `allow_unauthenticated` may relax access only when explicitly configured |
| FR-SEC-003 | Twig exposes whether the current user may edit inline content |
| FR-SEC-004 | Configurable `html.sanitize` strategy sanitizes rich-text block HTML on persist and at public render (`none`, `strip`, `allowlist`, `service`) |

### Configuration and DI (`FR-CFG-*`, `FR-DI-*`)

| ID | Requirement |
| --- | --- |
| FR-CFG-001 | The bundle exposes the configuration keys `default_locale`, `locales`, `pages`, `security`, `web_ui`, and `doctrine` |
| FR-CFG-002 | Defaults are `es`, `[es,en]`, `[home,contact]`, `ROLE_EDITOR`, `tailwind`, and `@NowoPageLayoutKitBundle/admin/layout.html.twig` |
| FR-DI-001 | Services, routes, Twig globals, and form types are wired by the bundle extension |

### Persistence and localization (`FR-ORM-*`, `FR-I18N-*`)

| ID | Requirement |
| --- | --- |
| FR-ORM-001 | Doctrine entities persist layout entries, block records, translations, and list/card items |
| FR-ORM-002 | `table_prefix` prefixes bundle entity tables when configured |
| FR-I18N-001 | Locale-aware block loading uses requested locale with fallback to `default_locale` |

### Twig integration (`FR-TWIG-*`)

| ID | Requirement |
| --- | --- |
| FR-TWIG-001 | The Twig namespace is `NowoPageLayoutKitBundle` |
| FR-TWIG-002 | Host overrides under `templates/bundles/NowoPageLayoutKitBundle/` take precedence |

### Demo and quality (`FR-DEMO-*`, `FR-QA-*`)

| ID | Requirement |
| --- | --- |
| FR-DEMO-001 | A Symfony 8 FrankenPHP demo boots on port `8127` by default |
| FR-QA-001 | The repository documents and validates bundle behavior through tests, docs, and Spec Kit baseline artifacts |

## Non-goals

- Visual drag-and-drop page builders
- Frontend theme ownership for host applications
- Generic CMS for untrusted end users
- Automatic sanitization policy for every host project's rich text strategy

## Success criteria (`SC-*`)

| ID | Criterion |
| --- | --- |
| SC-01 | Editors can open `/admin/pages/home/layout` and reorder persisted blocks |
| SC-02 | A host page can inject `PageBlockProvider` and render the returned blocks through Twig |
| SC-03 | Legacy content can be migrated with `nowo:page-layout:migrate` |
| SC-04 | `code-inventory.md` maps all 97 production files under `src/` |
| SC-05 | The Symfony 8 demo boots and answers on `http://localhost:8127` |

## Validation

```bash
make test
make phpstan
make validate-translations
make demo-smoke
make release-check
```

## Related requirements

See `docs/SPEC-DRIVEN-DEVELOPMENT.md` for `REQ-*` traceability and maintainer workflow guidance.
