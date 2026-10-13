# Symfony 8 demo — Page Layout Kit Bundle

Minimal Symfony 8 application running under **FrankenPHP** with MySQL and the path-mounted bundle.

## Quick start

From the bundle root:

```bash
make -C demo/symfony8 up
```

Default URL: `http://localhost:8127`

`make up` also seeds demo page blocks (home + contact, English and Spanish) with
`nowo:page-layout:migrate --if-empty`, reading them from `App\PageLayout\DemoPageContentProvider`
(the `LegacyPageContentProviderInterface` adapter). Re-run with `make seed`; reset with
`docker compose exec php bin/console nowo:page-layout:migrate --force`.

## What to try

1. Open `/`
2. Open `/api/ping`
3. Open `/admin/pages/home/layout`
4. Open `/admin/pages/contact/layout`
5. Click **Edit** on a block (or open `/admin/page-blocks/hero/<id>/edit-modal`) to see the translated block edit form

Admin credentials: `admin` / `admin`

## Useful commands

```bash
make -C demo/symfony8 test
make -C demo/symfony8 shell
make -C demo/symfony8 down
```

## Configuration

- Bundle config: `config/packages/nowo_page_layout_kit.yaml`
- Routes import: `config/routes/nowo_page_layout_kit.yaml`
- Security: in-memory `ROLE_ADMIN` demo user with HTTP Basic
- Doctrine: underscore naming strategy (the bundle's batch SQL loader expects underscore column names)
- Database: MySQL 8 (`mysql` service, not published to the host; DSN via `DATABASE_URL`)

See [../../docs/DEMO-FRANKENPHP.md](../../docs/DEMO-FRANKENPHP.md).
