# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/page-layout-kit-bundle` (`symfony-bundle`) |
| Audited revision | `v1.1.0` (from `v1.0.6` / `99a97be`) |
| Audit date | 2026-09-23 (remediation closed 2026-09-24) |
| Method | Manual review of every file under `src/` (services, controllers, subscribers, Twig extension, form types, repositories, command, DI extension, compiler pass, `Resources/config/services.yaml`) |
| Remediation | W-01…W-03 resolved: request-scoped layout memo (`WeakMap`), `nowo_page_layout_kit_can_edit()` Twig function (global deprecated), `HINT_REFRESH` on layout entries **and** admin block loaders, closed entity manager recovery subscriber. Regression tests simulate consecutive requests without `reset()`. |
| **Verdict** | ✅ **Compatible with scenario B (kernel not reset / no `services_resetter`)** for bundle-owned correctness: layout memo cannot outlive its main request, CMS pencils use a per-call Twig function, layout entries and admin block aggregates refresh from the DB, and a closed entity manager is reset at the next main request. Residual host responsibility: optional Doctrine identity-map memory hygiene (`clear()` / `FRANKENPHP_LOOP_MAX`) and migrating template overrides away from the deprecated `nowo_page_layout_kit_can_edit` global (W-02). |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `PageBlockProvider::$layoutCache` is a `WeakMap` keyed by the main `Request`; it cannot outlive the request (also cleared by `reset()`) |
| Static properties / `static` locals | ✅ | `PageLocales::$instance` is set once in `boot()` with immutable config (deprecated since 1.1.0, removed in 2.0; kept only for third-party callers, bundle code uses the injected service); no other static state |
| `ResetInterface` / `kernel.reset` coverage | ✅ | `PageBlockProvider` implements `ResetInterface` (autoconfigured) and `reset()` clears its only mutable property |
| Request / user / locale captured in services | ✅ | Nothing is captured in constructors; the edit permission is the Twig function `nowo_page_layout_kit_can_edit()` (legacy global deprecated) |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used; config is compiled into container parameters |
| Doctrine / EntityManager | ✅ | Closed manager reset at the next main request by `PageLayoutKitEntityManagerRecoverySubscriber`; layout entries and admin block loaders use `HINT_REFRESH`; optional identity-map `clear()` stays the application's job for memory hygiene |
| Output, headers, `exit`, shutdown functions | ✅ | None |
| Resources (files, sockets, cURL) held open | ✅ | None; `DOMDocument` is created per call |
| Memory growth across requests | ✅ (host optional) | Layout memo is released with its request; Doctrine identity map growth is bounded by CMS entity set — hosts may still `clear()` or set `FRANKENPHP_LOOP_MAX` |
| Blocking I/O and timeouts | ✅ | Only database queries through Doctrine/DBAL |
| Third-party static state | ✅ | `libxml_use_internal_errors()` is restored after each sanitize call; FormKit bound builder is restored in `finally` |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Service\PageBlockProvider` | yes | `$layoutCache` (`WeakMap<Request, …>`) | ✅ | ✅ (scoped to main request) |
| `Twig\PageLayoutKitExtension` | yes (`twig.extension`) | none; `nowo_page_layout_kit_can_edit()` evaluated per call; deprecated global still per-user | ✅ | ✅ (bundle templates); ⚠️ deprecated global frozen |
| `EventSubscriber\PageLayoutKitEntityManagerRecoverySubscriber` | yes | none (`readonly`) | ✅ | ✅ |
| `Security\ConfigurablePageLayoutKitAccessChecker` / `AllowAllPageLayoutKitAccessChecker` | yes | none (`readonly`), asks `AuthorizationChecker` on every call | ✅ | ✅ |
| `Security\PageLayoutProtection` + `PageLayoutProtectionConfig` | yes | none (`readonly`); builds a new sanitizer per call | ✅ | ✅ |
| `Security\Html\*Sanitizer` (Allowlist, Strip, Null) | created per call | none | ✅ | ✅ |
| `EventSubscriber\PageLayoutKitAdminAccessSubscriber` | yes | none (`readonly`), checks access per request | ✅ | ✅ |
| `EventSubscriber\PageBlockHtmlSanitizeSubscriber` (Doctrine listener) | yes | none (`readonly`) | ✅ | ✅ |
| `DependencyInjection\TablePrefixListener` (Doctrine listener) | yes | none (`readonly` prefix string) | ✅ | ✅ |
| `Locale\PageLocales` | yes (public) | none (`readonly` config); static `$instance` (deprecated since 1.1.0) bound once | ✅ | ✅ |
| `Repository\PageBlockSqlRepository` | yes | none (`readonly`, holds EntityManager; native SQL, always fresh) | ✅ | ✅ |
| 17 `ServiceEntityRepository` classes (`Page*Repository`) | yes | none; admin loaders use `HINT_REFRESH` | ✅ | ✅ |
| `Service\PageBlockRegistry` | yes | none (`readonly`) | ✅ | ✅ (fresh via repository hints) |
| `Service\PageBlockMigrator` | yes | none (`readonly`) | ✅ | ✅ (closed manager recovered, W-03) |
| `Controller\Admin\PageLayoutController`, `PageBlockEditController` | yes | none (`readonly` deps) | ✅ | ✅ (closed manager recovered, W-03) |
| 13 form types (`Form\*Type`, extending FormKit `FormKitAbstractType`) | yes | FormKit `$formKitBoundBuilder` (restored in `finally`), memoized `#[FormKitConfig]` name (class constant) | ✅ | ✅ |
| `Command\MigratePageBlocksCommand` | CLI only | none | N/A | N/A |

Value objects (`PageBlockView`, form data DTOs) are created per call; `PageBlockView` is `readonly`. Entities are only held in local variables and form closures built per request.

## Findings

### W-01 — `PageBlockProvider` layout cache survives requests when nothing resets it (Medium)

- **Where:** `src/Service/PageBlockProvider.php:26` (`private array $layoutCache`), filled at `:56` and `:88`, cleared only in `reset()` at `:38-41`. The class implements `ResetInterface` (`:23`) and is autoconfigured (`src/Resources/config/services.yaml:3-4`), so it gets the `kernel.reset` tag.
- **Worker impact:** under A the cache is cleared after every request, which is correct. Under B the first rendering of `pageKey|locale` in a worker is served forever: edits saved from the admin (`PageBlockEditController::update()`, `PageLayoutController::reorder()`) are not visible on that worker until it restarts. The cache key is `pageKey . '|' . $locale` (`:47`); `getLayout()` does not check `pageKey` against `nowo_page_layout_kit.pages`, so if a host passes a request-derived page key or accepts arbitrary `_locale` values the array grows without bound. The content is not user-specific, so there is no cross-user leak.
- **Recommendation:** keep `services_resetter` enabled (default in Symfony). If scenario B must be supported, drop the memo or move it to a request attribute, and validate `pageKey` against the configured `pages` list.
- **Status:** Resolved — `src/Service/PageBlockProvider.php` now stores the memo in a `WeakMap<Request, array>` keyed by `RequestStack::getMainRequest()`: a new main request starts with an empty memo, the entry is freed with the request object, and without a request (CLI) nothing is memoized. `reset()` still clears it (scenario A). Because the memo is bounded by one request, `pageKey` validation was not added (it would change the legacy-fallback behaviour for unknown keys). `spl_object_id()` was deliberately not used as key: ids are reused after the previous request is garbage-collected. Test: `PageBlockProviderTest::testLayoutMemoIsScopedToTheMainRequestWithoutCallingReset`.

### W-02 — `nowo_page_layout_kit_can_edit` is a per-user value exposed as a Twig global (Medium)

- **Where:** `src/Twig/PageLayoutKitExtension.php:35` returns `'nowo_page_layout_kit_can_edit' => $this->accessChecker->canAccess()` from `getGlobals()`. It is used in `src/Resources/views/cms/edit_button.html.twig:1`.
- **Worker impact:** Twig caches extension globals once the extension set is initialized (`Twig\ExtensionSet::getGlobals()` / `Environment::$resolvedGlobals`). The `twig` service is tagged `kernel.reset` with `?resetGlobals` in the installed `symfony/twig-bundle` (v8.1.2, `Resources/config/twig.php:74`), so under A the value is computed again per request. Under B the first visitor's answer is reused by the worker: an editor's `true` shows the CMS pencil buttons to anonymous visitors, or an anonymous `false` hides them from editors. The admin routes remain protected, because `PageLayoutKitAdminAccessSubscriber` calls `canAccess()` on every request, so this does not grant access; it leaks UI state across users. The composer constraint `twig/twig: ^3.8` also allows older Twig releases; I did not verify from which version `resetGlobals()` exists. Because the tag uses the optional `?resetGlobals` form, on a Twig without that method the reset is silently skipped and the leak also happens under A.
- **Recommendation:** expose the permission as a Twig function (for example `nowo_page_layout_kit_can_edit()`) or a lazy object evaluated at render time, instead of a scalar global. Keep the static config values (`layout`, `css_framework`, `pages`, `default_locale`) as globals.
- **Status:** Resolved — `PageLayoutKitExtension` adds the `nowo_page_layout_kit_can_edit()` function (calls the access checker on every call) and `cms/edit_button.html.twig` uses it (checking `block.blockId > 0` first). The global is documented public API (`docs/CONFIGURATION.md`, `docs/USAGE.md`), so it is kept for BC but **deprecated**; a boolean global cannot be made request-independent without breaking `{% if %}`. Twig version question: `Environment::resetGlobals()` exists since **Twig 3.14.0** (verified in `vendor/twig/twig/CHANGELOG`); the bundle templates no longer depend on it, so the `twig/twig: ^3.8` constraint was left unchanged. Only host overrides that still read the deprecated global are affected (UI only; documented in `docs/UPGRADING.md`). Test: `PageLayoutKitExtensionTest::testCanEditFunctionIsEvaluatedPerRenderOnTheSameTwigEnvironment`.

### W-03 — Doctrine EntityManager state relies on DoctrineBundle's resetter (Medium)

- **Where:** `src/Controller/Admin/PageLayoutController.php:94` and `src/Controller/Admin/PageBlockEditController.php:81` call `flush()` without handling failures; `src/Service/PageBlockMigrator.php:65` and `:386` also flush. `PageLayoutEntryRepository::findEnabledByPageKey()` (`src/Repository/PageLayoutEntryRepository.php:24-34`) hydrates managed entities on every public page view.
- **Worker impact:** under A DoctrineBundle resets the EntityManager (clears the identity map, reopens it after an exception). Under B: a failed `flush()` closes the EntityManager and every later admin edit on that worker fails with "EntityManager is closed"; and managed `PageLayoutEntry` entities stay in the identity map, so later DQL hydration returns the already-managed objects (stale `position` / `enabled`) and memory grows with every distinct page key loaded. Public block data comes from native SQL in `PageBlockSqlRepository` and is not affected by the identity map.
- **Recommendation:** keep `services_resetter` enabled so Doctrine's `ManagerRegistry` reset runs. Hosts that disable it must clear/reset the EntityManager themselves at the end of each request.
- **Status:** Resolved — new `src/EventSubscriber/PageLayoutKitEntityManagerRecoverySubscriber.php` (`KernelEvents::REQUEST`, priority 4096, main request only) resets the manager of `PageLayoutEntry` by name when it is closed; it never clears an open manager. `PageLayoutEntryRepository::findEnabledByPageKey()` and the six admin block loaders used by `PageBlockRegistry` (`findWithTranslations` / `findWithItemsAndTranslations`) use `Query::HINT_REFRESH`, so stale `position` / `enabled` / translation / item values are not served. Clearing an *open* identity map between requests (memory hygiene only) remains an optional **application** concern under scenario B. The bundle does not catch flush exceptions. Tests: `PageLayoutKitEntityManagerRecoverySubscriberTest`, `PageBlockRegistryTest`.

### Info

- `src/Locale/PageLocales.php:17` stores a static `$instance`, bound once in `src/NowoPageLayoutKitBundle.php:34-40` (`boot()`). The instance only holds `readonly` config from the container, and `boot()` runs once per worker, so there is nothing request-specific in it. The static accessors (`bind()`, `default()`, `all()`) are deprecated since 1.1.0 and will be removed in 2.0; bundle services, forms and the edit controller receive the `PageLocales` service by injection and pass it to `TranslatableBlockTrait` and the entities' `toArray()`, so no bundle code path depends on the static binding. It is kept (bound in `boot()`) only as a fallback for third-party callers that omit the optional `?PageLocales` argument, so PHPStan's two `frankenphp.worker.noMutableStaticProperty` findings on `PageLocales` remain accepted until 2.0.
- `src/Security/Html/AllowlistPageLayoutHtmlSanitizer.php:80-88` toggles `libxml_use_internal_errors()` and restores the previous value right after `loadHTML()`, so process-wide libxml state is not changed across requests.
- FormKit's `withBuilder()` (dependency `nowo-tech/form-kit-bundle`, `src/Form/FormKitTrait.php`) stores the builder in a property on the shared form type only for the duration of the callback and restores it in a `finally` block, so a failing `buildForm()` does not leave a stale builder behind.
- The demo ships a worker Caddyfile (`demo/symfony8/docker/frankenphp/Caddyfile:15-18`) and defaults to `FRANKENPHP_MODE=worker`.

No other findings: no superglobals, no `ini_set`/`setlocale`/timezone changes, no native output or headers, no open resources and no external I/O besides the database.

## Usage recommendations in worker mode

- Bundle-owned correctness no longer depends on `services_resetter` / kernel reboot between requests; keeping the resetter active (default) is still recommended for framework and shared Doctrine state.
- Under scenario B the bundle only resets a *closed* manager; hosts may still `clear()` an open identity map or set `max_requests` / `FRANKENPHP_LOOP_MAX` for memory hygiene.
- In template overrides use `nowo_page_layout_kit_can_edit()` instead of the deprecated global (the global also needs Twig ≥ 3.14 `resetGlobals()` to be refreshed under A).
- Custom `LegacyPageContentProviderInterface`, HTML sanitizer (`html.sanitize.service`) or access checker (`security.access_checker`) services must be stateless, or implement `ResetInterface`. An access checker that memoizes its answer in a property would leak permissions across users.

## Re-audit triggers

Re-run this audit when a change adds: properties or caches to `PageBlockProvider` or the Twig extension, new Twig globals, new event listeners/subscribers, Doctrine listeners that buffer entities, a custom access checker or sanitizer shipped by the bundle, or any use of `$_SERVER` / `$_ENV` at runtime.
