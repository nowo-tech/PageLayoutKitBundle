# Demo applications with FrankenPHP

This repository ships a Symfony 8 demo for Page Layout Kit Bundle running on FrankenPHP.

## Contents

- [Overview](#overview)
- [What the demo includes](#what-the-demo-includes)
- [Development configuration](#development-configuration)
- [Production-style worker mode](#production-style-worker-mode)
- [Switching classic vs worker](#switching-classic-vs-worker)
- [Troubleshooting](#troubleshooting)

## Overview

Run the demo from the bundle root:

```bash
make -C demo/symfony8 up
```

Default URL: `http://localhost:8127`

The demo exists to prove that:

- the bundle boots on Symfony 8
- Doctrine mappings are valid
- admin routes are reachable
- the FrankenPHP setup works in both classic and worker mode

## What the demo includes

- Symfony 8 application under `demo/symfony8`
- FrankenPHP + Docker Compose
- SQLite database, no external DB container
- Path-mounted `PageLayoutKitBundle` from the repository root
- `TwigExtraBundle`, `FormKitBundle`, `UiKitBundle`, and demo-only `HotReloadBundle` / `TwigInspectorBundle`
- HTTP Basic auth for the editor user `admin` / `admin`
- Bundle config rooted at `config/packages/nowo_page_layout_kit.yaml`

Important demo routes:

- `/`
- `/api/ping`
- `/api/echo`
- `/admin/pages/home/layout`
- `/admin/pages/contact/layout`

## Development configuration

Goal: changes are visible quickly while developing the bundle.

- Use `docker/frankenphp/Caddyfile.dev`
- Keep `APP_ENV=dev`
- Use `FRANKENPHP_MODE=classic` when you want per-request PHP execution
- Use `make -C demo/symfony8 link-bundle` after dependency or schema changes

## Production-style worker mode

The demo defaults `FRANKENPHP_MODE=worker` in `.env.example`. That is useful to validate the bundle under a long-lived runtime.

The README image and claim are accurate: this bundle is designed to behave correctly in FrankenPHP worker mode.

## Switching classic vs worker

Set the mode in `demo/symfony8/.env`:

```dotenv
FRANKENPHP_MODE=classic
```

or:

```dotenv
FRANKENPHP_MODE=worker
```

Then recreate or restart the demo container:

```bash
make -C demo/symfony8 restart
```

## Troubleshooting

- If the demo does not answer on `8127`, check whether the port is already in use.
- If admin pages fail with `401`, use `admin` / `admin`.
- If layout pages are empty, confirm schema creation ran successfully inside `link-bundle`.
- If Twig changes are not visible, restart the demo or switch to classic mode while editing templates.
- If routes are missing, verify `config/routes/nowo_page_layout_kit.yaml` is present in the demo app.
