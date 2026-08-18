# Contributing Guide

Thank you for contributing to **Page Layout Kit Bundle**.

## Table of contents

- [Code of Conduct](#code-of-conduct)
- [Reporting Bugs and Gaps](#reporting-bugs-and-gaps)
- [Submitting Changes](#submitting-changes)
- [Development setup](#development-setup)
- [Quality gates](#quality-gates)
- [Project structure](#project-structure)
- [Demo](#demo)
- [Questions](#questions)

## Code of Conduct

Please follow [CODE_OF_CONDUCT.md](../CODE_OF_CONDUCT.md). Report unacceptable behavior to `hectorfranco@nowo.tech`.

## Reporting Bugs and Gaps

Open a GitHub issue with:

- A clear description of the problem
- Reproduction steps
- Expected vs actual behavior
- PHP, Symfony, and bundle versions
- Relevant `nowo_page_layout_kit` configuration
- Whether the issue affects admin reorder, inline editing, public rendering, or legacy migration

## Submitting Changes

1. Fork the repository and create a branch from `main`.
2. Make the smallest coherent change you can.
3. Update docs when integrator-visible behavior changes.
4. Update `specs/001-baseline/` whenever `src/` changes.
5. Open a pull request against `main`.

## Development setup

```bash
git clone https://github.com/your-username/PageLayoutKitBundle.git
cd PageLayoutKitBundle
make up
make setup-hooks
```

Useful commands:

```bash
make test
make phpstan
make validate-translations
make release-check
```

If CI reports Cursor co-author trailers in git history, run:

```bash
make check-no-cursor-coauthor
make strip-cursor-coauthor-from-history
```

See [GITHUB_CI.md](GITHUB_CI.md).

## Quality gates

Before opening a PR, aim to pass:

- `make test`
- `make test-coverage-100`
- `make phpstan`
- `make validate-translations`
- `make release-check`

## Project structure

```text
PageLayoutKitBundle/
├── src/                    # Bundle source code
│   ├── Command/
│   ├── Controller/
│   ├── DependencyInjection/
│   ├── Entity/
│   ├── EventSubscriber/
│   ├── Form/
│   ├── Locale/
│   ├── Repository/
│   ├── Resources/
│   ├── Security/
│   ├── Service/
│   └── Twig/
├── tests/                  # Unit tests
├── demo/                   # FrankenPHP demo
├── docs/                   # Integrator and maintainer docs
└── specs/                  # Spec Kit baseline and future feature specs
```

## Demo

Run the Symfony 8 FrankenPHP demo on `http://localhost:8127`:

```bash
make -C demo/symfony8 up
make demo-smoke
```

The demo is useful for checking:

- Admin reorder UI
- Security wiring
- Bundle boot on Symfony 8
- Basic public rendering integration

## Questions

- Open an issue on GitHub
- Contact the maintainers at `hectorfranco@nowo.tech`
