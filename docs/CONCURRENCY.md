# Concurrency

How the bundle stays correct when admins, workers and the migration command write at the same time
(nowo-tech REQ-RUNTIME-003). This page lists the write paths, the conflict answers and codes, the host options and the
residual risks.

## Table of contents

- [Rules the bundle follows](#rules-the-bundle-follows)
- [Write paths](#write-paths)
- [Conflict answers and codes](#conflict-answers-and-codes)
- [What is not a conflict](#what-is-not-a-conflict)
- [Host options](#host-options)
- [Retries](#retries)
- [Tests and CI](#tests-and-ci)
- [Residual risks](#residual-risks)

## Rules the bundle follows

- **One transaction per write unit.** Units that read before they write (the layout reorder, each page of the
  migration) run through nowo-tech/transaction-doctrine's runner (`PageLayoutTransaction::run()`): one transaction of
  the bundle's connection, flushed before the commit, nothing pending after a failure. The block edit is one ORM flush
  (one transaction).
- **Your entity manager is never reset or cleared.** Without `doctrine.entity_manager` the bundle writes on the manager
  that maps its entities (your default one) with `DoctrineTransactionRunner::forHostEntityManager()`: an open manager is
  never closed, cleared or reset, and the runner refuses to start (`LogicException`) while your manager holds unflushed
  changes. With `doctrine.entity_manager` the bundle owns that manager: `forEntityManager()` may close and reset it
  after a failed unit, and `PageLayoutKitEntityManagerRecoverySubscriber` resets it when closed and clears it at every
  request / message / command boundary.
- **Side effects after the real commit.** `PageLayoutChangedEvent`, `PageBlockUpdatedEvent` and
  `PageBlocksMigratedEvent` wait for the outermost commit of the bundle's connection (`PageLayoutOutbox`) and are dropped
  with a rollback (2.2).
- **No host I/O under a lock.** The migration reads the legacy content provider before it takes any lock.
- **FrankenPHP worker mode with kernel reset off.** Every service is stateless per request; the commit ledger of the
  conflict answer is a monotonic counter of the process, read by difference only.

## Write paths

| Write path | Transaction | Control | Conflict answer |
| --- | --- | --- | --- |
| Layout reorder (`PageLayoutController::layout` POST → `PageLayoutReorderer`) | One runner unit on the bundle's manager | The page's entries `SELECT … FOR UPDATE` in id order and read again under the lock (`PageLayoutEntryRepository::lockByPageKey()`); every submitted position of the enabled entries is written | 409 `CONFLICT_001` / `CONFLICT_002` |
| Block edit (`PageBlockEditController::update`) | One ORM flush on the bundle's manager (`PageLayoutTransaction::commit()`) | Unique `(translatable, locale)` key per translation table for "one translation per locale" | Lock conflict: 409 `CONFLICT_001` / `CONFLICT_002`. Duplicate translation: 409 `PAGE_LAYOUT_CONCURRENT_EDIT` (domain) |
| Migration (`nowo:page-layout:migrate`, `PageBlockMigrator::migrate()`) | One runner unit **per page** (`home`, then `contact`): with `--force` the page's entries and the blocks they reference are deleted, then its new blocks and entries are inserted; the unit commits whole or not at all | Migration lock (MySQL / MariaDB `GET_LOCK(name, 0)`, PostgreSQL `pg_try_advisory_lock`, per database and layout table, never waits) for "one migration at a time"; the page's entries `FOR UPDATE` first, so it serialises with an admin reorder of that page; without `--force` a page that has a layout is kept | Console: exit code 1 with "run the command again" (lock conflict) or "another migration is running" (lock held); nothing of the failed page is stored, pages built before it stay |

`flush()` in `src/`: `PageLayoutTransaction::commit()` (the block edit's single flush), the runner's own flush, the
migration's id flushes inside its page unit (`PageBlockMigrator::addLayout()`), and the controllers' fallback when
they are built without `PageLayoutTransaction` (tests only; the container always injects it).

## Conflict answers and codes

On the routes of the bundle (`PageLayoutConflictListener`, controllers of the bundle only; your own routes keep your
error handling):

| Code | When | Status | `Retry-After` | Client should |
| --- | --- | --- | --- | --- |
| `CONFLICT_001` | Deadlock, lock wait timeout or serialization failure (MySQL 1213 / 1205, MariaDB 1020, PostgreSQL 40001 / 40P01 / 55P03; a `TransientStorageFailure` of the runner or a DBAL error anywhere in the chain) and nothing committed since the request started | 409 (`conflict_response.status`) | Yes: the runner's hint, else `conflict_response.retry_after`, else 1 s | Retry |
| `CONFLICT_002` | The same after a commit earlier in the request (transaction-doctrine's request mark, `ConflictCode::forRequest()`, when its bundle is registered with `commit_detection`; and the bundle's own mark on a `CommitLedger` of its connection), a `ConcurrencyConflict` that committed, or a lost savepoint with no deadlock behind it (outcome unknown) | 409 (`conflict_response.status`) | No | Reload, do not repeat blindly |
| `PAGE_LAYOUT_CONCURRENT_EDIT` (`X-Error-Code`) | Two editors added the same missing translation of one block at once; the unique key refused the second | 409 | No | Reload the block and apply the change again |

- The `CONFLICT_*` code is in the `X-Conflict-Code` header, which only ever holds `CONFLICT_001` or `CONFLICT_002`.
  The domain answer has no `X-Conflict-Code`.
- The admin pages are rendered by your error page: the answer is an `HttpException` with a fixed, client-safe message.
  The storage error stays the previous exception for your logs; no SQL, parameter or value reaches the client.
- The bundle has no JSON API; its answers are pages and redirects, so no JSON shape changed.
- The codes are `Nowo\TransactionDoctrine\ConflictCode` values.

## What is not a conflict

- **Unique constraint violations** are not lock conflicts. The bundle's only unique keys are the block translation
  keys; a violation there on the bundle's routes is the "two editors, one translation" race and answers the domain 409
  above. Anywhere else (your code) it stays a real error.
- **Lost connections** are never mapped: the outcome of the write is unknown and nothing tells it was a lock.
- Validation errors stay form errors (422 for the block modal).

## Host options

```yaml
nowo_page_layout_kit:
    conflict_response:
        status: null        # 409 (default) or 503
        retry_after: null   # seconds for CONFLICT_001 without a hint
```

- Unset (null), the bundle follows `nowo_transaction_doctrine.conflict_response` when nowo-tech/transaction-doctrine's
  Symfony bundle is registered, so the whole application answers the same; otherwise 409 with `Retry-After: 1`.
- `status: 503` for proxies or monitors that treat 503 as retryable; the codes and the `Retry-After` rule stay the same.
- `doctrine.entity_manager`: give the bundle its own manager (see [CONFIGURATION](CONFIGURATION.md#doctrine)) if your
  requests may hold unflushed changes when they reach the bundle's routes.
- Database settings: READ COMMITTED and a short `innodb_lock_wait_timeout` / `lock_timeout` (a few seconds) for HTTP;
  the wait is bounded by the database, not by the bundle.

## Retries

- HTTP writes make **one attempt**: the client (the admin) retries after a `CONFLICT_001`.
- The runner makes one attempt too (no replay); a retried reorder loads and locks the rows again, so it never applies a
  stale read.
- The migration is safe to run again: a failed page is unchanged, a built page is kept without `--force`, and with
  `--force` each page is replaced whole.
- The bundle has no Messenger handlers.

## Tests and CI

- `tests/Integration/Database/PageLayoutConcurrencyDatabaseTest.php` plays two connections against real servers, each
  session with a 2 s `innodb_lock_wait_timeout` / `lock_timeout`:
  - a forced migration that meets an admin reorder of the same page rolls the page back whole (the admin's order is
    committed, no block of the failed unit stays, the next page is not started, the connection is usable, running it
    again succeeds);
  - an admin reorder that meets the open migration unit answers 409 + `Retry-After` + `CONFLICT_001` (503 with the
    host option); the migrated layout is whole and untouched by the failed reorder;
  - two reorders of one page, the second read before the first saved: the second order replaces the first whole, no
    duplicate position;
  - a second migration while the first is inside its unit fails fast with nothing changed; each page is built once;
  - a lock conflict after a committed unit of the same request answers `CONFLICT_002` without `Retry-After` (commit
    counted by the real commit-tracking middleware);
  - two editors adding the same translation answer the domain 409.
- `tests/Unit/EventListener/PageLayoutConflictListenerTest.php` maps every case: `CONFLICT_001` for each vendor code,
  `CONFLICT_002` (own ledger, transaction-doctrine's mark, committed `ConcurrencyConflict`, lost savepoint), 503, the
  runner's hint, the duplicate translation, lost connections and other errors left alone, host routes untouched.
- The CI job **Real databases (MySQL 8.4, MariaDB 11.4, PostgreSQL 16)** runs the `database` group on DBAL 3 and 4 with
  `--fail-on-skipped` and is a required check of `main`.

```bash
make test-with-db   # starts the "databases" compose profile and runs the group
make down-db
```

## Residual risks

- **Two editors saving the same block: last write wins per field.** The block edit has no version column: the second
  save overwrites the fields the first one changed, without a warning. Admin-only path; a version column needs a
  schema change and is left for a later minor.
- **Two admins reordering one page: the later save wins whole.** The lock makes the outcome one admin's complete
  order, never a mix, but the earlier admin is not told that the order changed.
- **Locking reads without an index on `page_key`.** Under MySQL / MariaDB REPEATABLE READ (the default) the
  `FOR UPDATE` read of one page scans and locks every layout entry, so a reorder of `home` waits for a migration of
  `contact`. READ COMMITTED is recommended; the table is small and admin-only.
- **Commit detection covers the bundle's connection** (and every connection when transaction-doctrine's
  `commit_detection` is on). A write of yours on another connection before the bundle's controller fails is not
  counted without transaction-doctrine's bundle.
- **The migration lock is per database server session.** On SQLite (and other platforms) there is no lock; SQLite
  serialises writers itself and each page unit re-checks the page. On a connection pooler in transaction mode
  (PgBouncer) a session-level advisory lock is not reliable; run the migration on a direct connection.
- **A host closed manager.** Without `doctrine.entity_manager` a failed flush that closes your manager is left for you
  (DoctrineBundle, or transaction-doctrine's `WorkerConnectionHygiene` at the next request).
