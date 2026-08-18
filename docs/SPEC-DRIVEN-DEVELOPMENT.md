# Spec-driven development

## Table of contents

- [Three layers](#three-layers)
- [User stories](#user-stories)
- [Functional scope](#functional-scope)
- [Validating the spec](#validating-the-spec)
- [Requirement identifiers (`REQ-*`)](#requirement-identifiers-req-)
- [Suggested workflow for contributors](#suggested-workflow-for-contributors)
- [GitHub Spec Kit (summary)](#github-spec-kit-summary)
- [See also](#see-also)

## Three layers

In this repository, spec-driven development has three layers that stay in sync:

1. **GitHub Spec Kit baseline**: `specs/001-baseline/` documents the bundle behavior and maps 100% of production files under `src/`.
2. **Product behavior**: Page composition, admin reorder, inline editing, public rendering, and legacy migration are documented in the integrator docs.
3. **Traceability anchors**: Stable `REQ-*` identifiers link docs, demo expectations, and QA workflows.

## User stories

| ID | Story |
| --- | --- |
| US-01 | As an editor, I reorder typed blocks for configured pages such as `home` and `contact` |
| US-02 | As an editor, I update supported page blocks through inline CMS modals without leaving the public page flow |
| US-03 | As a developer, I render an ordered page layout through services and Twig templates |
| US-04 | As a maintainer, I migrate older page content into typed Doctrine-backed blocks |
| US-05 | As an integrator, I adapt security, layout shell, locales, and page keys from configuration |
| US-06 | As a maintainer, I run the Symfony 8 FrankenPHP demo and QA checks to verify the bundle boots cleanly |

## Functional scope

**In scope:** typed page blocks, `PageLayoutEntry` ordering, locale fallback, admin reorder UI, inline block editing, custom access checking, legacy migration, Twig namespace and overrides, Doctrine persistence, SQL batch loading, Symfony Flex recipe, FrankenPHP demo.

**Non-goals:** full site-builder tooling, visual drag-and-drop page design, frontend asset pipeline ownership, or untrusted-user HTML sanitization.

## Validating the spec

```bash
make test
make phpstan
make validate-translations
make demo-smoke
make release-check
```

## Requirement identifiers (`REQ-*`)

| ID | Where | What it marks |
| --- | --- | --- |
| REQ-DOCS-002 | `README.md` | Canonical documentation link order |
| REQ-DOCS-018 | `README.md` | Root README structure for bundle docs |
| REQ-UI-002 | `docs/SECURITY.md`, security config | Admin routes require editor access unless explicitly relaxed |
| REQ-TWIG-001 | `TwigPathsPass` | Host Twig overrides win over bundle templates |
| REQ-TWIG-004 | `docs/INSTALLATION.md`, demo bundles | Twig Extra requirement is documented |
| REQ-TEST-011 | `Makefile` `demo-smoke` | Demo boots and returns HTTP 200 |
| REQ-MAKE-002 | `Makefile` `release-check` | Pre-release QA chain |
| REQ-MAKE-004 | `Makefile` `validate-translations` | Translation parity validation hook |
| REQ-GIT-001 | `docs/GITHUB_CI.md` | No Cursor co-author trailers in git history |

## Suggested workflow for contributors

1. Clarify the behavior change or bug.
2. Update or create the relevant spec artifact.
3. Implement the change with tests if production behavior changes.
4. Update integrator docs when host applications must act.
5. Keep `specs/001-baseline/spec.md` and `code-inventory.md` aligned with `src/`.

## GitHub Spec Kit (summary)

This repository uses [GitHub Spec Kit](https://github.com/github/spec-kit) with Cursor Agent integration.

| Artifact | Path |
| --- | --- |
| Baseline spec | `specs/001-baseline/spec.md` |
| Code inventory | `specs/001-baseline/code-inventory.md` |
| Tooling manual | `docs/SPEC-KIT.md` |
| Constitution | `.specify/memory/constitution.md` |
| Cursor skills | `.cursor/skills/speckit-*/` |

## See also

- [SPEC-KIT.md](SPEC-KIT.md)
- [INSTALLATION.md](INSTALLATION.md)
- [CONFIGURATION.md](CONFIGURATION.md)
- [USAGE.md](USAGE.md)
- [SECURITY.md](SECURITY.md)
