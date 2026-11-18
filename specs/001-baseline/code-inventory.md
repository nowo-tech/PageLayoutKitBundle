# Code inventory — PageLayoutKitBundle baseline

**Baseline spec:** [`spec.md`](spec.md)  
**Package:** `nowo-tech/page-layout-kit-bundle`  
**Last audited:** 2026-11-18 (2.2 outbox, entity manager isolation, UI page contract, asset build outputs, concurrency)

Maps production files under `src/` (baseline inventory kept in sync for worker-mode and HTML-sanitize surfaces).

## Bundle entry

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `NowoPageLayoutKitBundle.php` | Bundle entrypoint and Doctrine mapping registration | FR-DI-001, FR-ORM-001 |

## Command

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Command/MigratePageBlocksCommand.php` | Console migration from legacy content | FR-LEG-002 |

## Controllers

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Controller/RequiresValidFormTrait.php` | Shared CSRF/form validation helper | FR-ADM-003 |
| `Controller/Admin/PageLayoutController.php` | Admin reorder UI for page layouts | FR-ADM-001, FR-ADM-003 |
| `Controller/Admin/PageBlockEditController.php` | Inline modal edit endpoints for typed blocks | FR-ADM-002, FR-ADM-003 |

## Dependency injection and compiler

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `DependencyInjection/Configuration.php` | Bundle configuration tree | FR-CFG-001, FR-CFG-002 |
| `DependencyInjection/NowoPageLayoutKitExtension.php` | Loads services and publishes parameters | FR-CFG-001, FR-DI-001, FR-SEC-001 |
| `DependencyInjection/TablePrefixListener.php` | Applies configured table prefix | FR-ORM-002 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Registers Twig namespace and host override precedence | FR-TWIG-001, FR-TWIG-002 |
| `DependencyInjection/Compiler/CsrfProtectionPass.php` | Fails closed when the layout admin runs without form CSRF protection | FR-ADM-003 |
| `DependencyInjection/Compiler/PageLayoutEntityManagerPass.php` | Keeps the bundle entities on `doctrine.entity_manager` only (2.2) | FR-ORM-003 |
| `DependencyInjection/Compiler/AfterCommitMiddlewarePass.php` | Attaches the after-commit middleware to the bundle manager's connection only | FR-EVT-001 |

## Entities

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Entity/PageLayoutEntry.php` | Ordered page-to-block reference | FR-CMP-001, FR-ORM-001 |
| `Entity/PageHeroBlock.php` | Hero block aggregate | FR-CMP-003, FR-ORM-001 |
| `Entity/PageHeroBlockTranslation.php` | Hero localized content | FR-I18N-001, FR-ORM-001 |
| `Entity/PageTextBlock.php` | Text block aggregate | FR-CMP-003, FR-ORM-001 |
| `Entity/PageTextBlockTranslation.php` | Text localized content | FR-I18N-001, FR-ORM-001 |
| `Entity/PageCardsBlock.php` | Cards block aggregate | FR-CMP-003, FR-ORM-001 |
| `Entity/PageCardsBlockTranslation.php` | Cards block localized title | FR-I18N-001, FR-ORM-001 |
| `Entity/PageCardItem.php` | Individual card item | FR-CMP-003, FR-ORM-001 |
| `Entity/PageCardItemTranslation.php` | Card item localized content | FR-I18N-001, FR-ORM-001 |
| `Entity/PageListBlock.php` | List block aggregate | FR-CMP-003, FR-ORM-001 |
| `Entity/PageListBlockTranslation.php` | List block localized title | FR-I18N-001, FR-ORM-001 |
| `Entity/PageListItem.php` | Individual list item | FR-CMP-003, FR-ORM-001 |
| `Entity/PageListItemTranslation.php` | List item localized text | FR-I18N-001, FR-ORM-001 |
| `Entity/PageCtaBlock.php` | CTA block aggregate | FR-CMP-003, FR-ORM-001 |
| `Entity/PageCtaBlockTranslation.php` | CTA localized content | FR-I18N-001, FR-ORM-001 |
| `Entity/PageCompareBlock.php` | Compare block aggregate | FR-CMP-003, FR-ORM-001 |
| `Entity/PageCompareBlockTranslation.php` | Compare localized content | FR-I18N-001, FR-ORM-001 |

## Enums, locale, and model helpers

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Enum/PageBlockType.php` | Supported typed block kinds | FR-CMP-003 |
| `Locale/PageLocales.php` | Config-backed locale registry and fallback helper | FR-I18N-001, FR-CFG-001 |
| `Model/LocaleAwareTranslationInterface.php` | Translation contract | FR-I18N-001 |
| `Model/TranslatableBlockTrait.php` | Shared translation-management behavior | FR-I18N-001, FR-ORM-001 |
| `Legacy/LegacyPageContentProviderInterface.php` | Legacy content fallback contract | FR-LEG-001, FR-LEG-002 |

## Event subscriber and security

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `EventSubscriber/PageLayoutKitAdminAccessSubscriber.php` | Enforces access on admin route prefixes | FR-SEC-001, FR-SEC-002 |
| `EventSubscriber/PageLayoutKitEntityManagerRecoverySubscriber.php` | Resets a closed page-layout EntityManager on the next main request (worker mode) | FR-WRK-001 |
| `EventSubscriber/PageBlockHtmlSanitizeSubscriber.php` | Sanitizes rich-text block HTML on Doctrine persist/update | FR-SEC-004 |
| `Security/PageLayoutKitAccessCheckerInterface.php` | Access checker contract | FR-SEC-001 |
| `Security/ConfigurablePageLayoutKitAccessChecker.php` | Role-based access checker | FR-SEC-001 |
| `Security/AllowAllPageLayoutKitAccessChecker.php` | Demo-style unauthenticated access checker | FR-SEC-002 |
| `Security/PageLayoutProtection.php` | Wires configured HTML sanitizer for persist and public render | FR-SEC-004 |
| `Security/PageLayoutProtectionConfig.php` | Value object for HTML sanitize strategy / service id | FR-SEC-004 |
| `Security/Html/PageLayoutHtmlSanitizerInterface.php` | HTML sanitizer contract | FR-SEC-004 |
| `Security/Html/NullPageLayoutHtmlSanitizer.php` | No-op sanitizer (`strategy: none`) | FR-SEC-004 |
| `Security/Html/StripPageLayoutHtmlSanitizer.php` | Strip-tags sanitizer | FR-SEC-004 |
| `Security/Html/AllowlistPageLayoutHtmlSanitizer.php` | Allowlist sanitizer | FR-SEC-004 |
| `Enum/HtmlSanitizeStrategy.php` | Sanitize strategy enum | FR-SEC-004 |
| `Security/Html/PageLayoutUrlAttributeSanitizer.php` | Bundle URL policy on top of symfony/html-sanitizer URL checks | FR-SEC-004 |

## Forms

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Form/AbstractPageLayoutFormType.php` | Shared form behavior for block editors | FR-ADM-002 |
| `Form/PageBlockLocalePanelData.php` | Locale-panel DTO | FR-I18N-001, FR-ADM-002 |
| `Form/PageBlockLocalePanelType.php` | Locale tab form section | FR-I18N-001, FR-ADM-002 |
| `Form/PageHeroBlockEditType.php` | Hero edit form | FR-ADM-002 |
| `Form/PageHeroBlockModalType.php` | Hero modal form | FR-ADM-002 |
| `Form/PageTextBlockEditType.php` | Text edit form | FR-ADM-002 |
| `Form/PageTextBlockModalType.php` | Text modal form | FR-ADM-002 |
| `Form/PageCardsBlockInlineModalType.php` | Cards inline modal form | FR-ADM-002 |
| `Form/PageListBlockInlineModalType.php` | List inline modal form | FR-ADM-002 |
| `Form/PageCtaBlockEditType.php` | CTA edit form | FR-ADM-002 |
| `Form/PageCtaBlockModalType.php` | CTA modal form | FR-ADM-002 |
| `Form/PageCompareBlockEditType.php` | Compare edit form | FR-ADM-002 |
| `Form/PageCompareBlockModalType.php` | Compare modal form | FR-ADM-002 |
| `Form/PageLayoutReorderData.php` | Reorder form DTO | FR-ADM-001, FR-ADM-003 |
| `Form/PageLayoutReorderRowData.php` | Reorder row DTO | FR-ADM-001, FR-ADM-003 |
| `Form/PageLayoutReorderType.php` | Reorder collection form | FR-ADM-001, FR-ADM-003 |
| `Form/PageLayoutReorderRowType.php` | Reorder row form | FR-ADM-001, FR-ADM-003 |

## Services

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Service/PageBlockProvider.php` | Resolves ordered public block views with locale fallback | FR-REN-001, FR-REN-003, FR-LEG-001, FR-WRK-001 |
| `Service/PageBlockView.php` | Public block view object with template resolution | FR-REN-001, FR-REN-002 |
| `Service/PageBlockRegistry.php` | Loads typed block aggregates by type and id | FR-ADM-002, FR-ORM-001, FR-WRK-001 |
| `Service/PageBlockMigrator.php` | Converts legacy content into typed blocks and layout entries | FR-LEG-002, FR-CMP-001 |
| `Service/PublicPageUrlResolver.php` | Public URL of a page key (view links, redirects) | FR-ADM-004 |
| `Service/PageLayoutProviderInterface.php` | Read port: ordered block DTOs by page key, no entity (2.2) | FR-REN-001 |

## Repositories

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Repository/Concerns/RunsDocumentedSql.php` | Shared raw-SQL execution helper | FR-REN-004 |
| `Repository/PageBlockSqlRepository.php` | Batch SQL loader for public block rendering | FR-REN-004, FR-I18N-001 |
| `Repository/PageLayoutEntryRepository.php` | Layout entry lookup and ordering queries | FR-CMP-001, FR-ADM-001 |
| `Repository/PageHeroBlockRepository.php` | Hero block repository | FR-ORM-001 |
| `Repository/PageHeroBlockTranslationRepository.php` | Hero translation repository | FR-ORM-001, FR-I18N-001 |
| `Repository/PageTextBlockRepository.php` | Text block repository | FR-ORM-001 |
| `Repository/PageTextBlockTranslationRepository.php` | Text translation repository | FR-ORM-001, FR-I18N-001 |
| `Repository/PageCardsBlockRepository.php` | Cards block repository | FR-ORM-001 |
| `Repository/PageCardsBlockTranslationRepository.php` | Cards translation repository | FR-ORM-001, FR-I18N-001 |
| `Repository/PageCardItemRepository.php` | Card item repository | FR-ORM-001 |
| `Repository/PageCardItemTranslationRepository.php` | Card item translation repository | FR-ORM-001, FR-I18N-001 |
| `Repository/PageListBlockRepository.php` | List block repository | FR-ORM-001 |
| `Repository/PageListBlockTranslationRepository.php` | List translation repository | FR-ORM-001, FR-I18N-001 |
| `Repository/PageListItemRepository.php` | List item repository | FR-ORM-001 |
| `Repository/PageListItemTranslationRepository.php` | List item translation repository | FR-ORM-001, FR-I18N-001 |
| `Repository/PageCtaBlockRepository.php` | CTA block repository | FR-ORM-001 |
| `Repository/PageCtaBlockTranslationRepository.php` | CTA translation repository | FR-ORM-001, FR-I18N-001 |
| `Repository/PageCompareBlockRepository.php` | Compare block repository | FR-ORM-001 |
| `Repository/PageCompareBlockTranslationRepository.php` | Compare translation repository | FR-ORM-001, FR-I18N-001 |

## Twig extension

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Twig/PageLayoutKitExtension.php` | Publishes Twig globals/functions for layout shell, pages, locale, and editability | FR-SEC-003, FR-TWIG-001, FR-WRK-001 |

## Resources — config and assets

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Resources/config/routing.yaml` | Attribute-route import | FR-DI-001 |
| `Resources/config/services.yaml` | Service and form wiring | FR-DI-001 |
| `Resources/assets/src/cms_modal_controller.ts` | Stimulus controller for inline CMS modal flow | FR-ADM-002 |
| `Resources/assets/src/cms_modal_controller.test.ts` | Co-located Vitest suite of the modal controller (FR-TEST, not shipped to hosts at runtime) | FR-ADM-002, FR-QA-001 |
| `Resources/public/js/cms_modal_controller.js` | Build output (ESM) of `cms_modal_controller.ts` | FR-ADM-002 |
| `Resources/public/js/cms_modal_controller.iife.js` | Build output (IIFE) of `cms_modal_controller.ts` | FR-ADM-002 |

## Resources — admin Twig views

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Resources/views/admin/layout.html.twig` | Default admin shell layout | FR-CFG-002, FR-TWIG-001 |
| `Resources/views/admin/_modal_form.html.twig` | Shared admin modal form | FR-ADM-002 |
| `Resources/views/admin/layout/index.html.twig` | Reorder page layout screen | FR-ADM-001 |
| `Resources/views/admin/blocks/_modal_form.html.twig` | Block modal body partial | FR-ADM-002 |
| `Resources/views/admin/blocks/edit_stub.html.twig` | Block edit stub page | FR-ADM-002 |
| `Resources/views/admin/_legacy_layout.html.twig` | BC bridge for a 2.0 host admin layout (deprecated since 2.1, removed in 3.0) | FR-CFG-002 |

## Resources — public block Twig views

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Resources/views/blocks/_wrapper.html.twig` | Public block wrapper plus inline edit button | FR-REN-002, FR-SEC-003 |
| `Resources/views/blocks/hero.html.twig` | Hero public block template | FR-REN-002 |
| `Resources/views/blocks/text.html.twig` | Text public block template | FR-REN-002 |
| `Resources/views/blocks/cards.html.twig` | Cards public block template | FR-REN-002 |
| `Resources/views/blocks/list.html.twig` | List public block template | FR-REN-002 |
| `Resources/views/blocks/cta.html.twig` | CTA public block template | FR-REN-002 |
| `Resources/views/blocks/compare.html.twig` | Compare public block template | FR-REN-002 |

## Resources — CMS Twig views

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Resources/views/cms/modal_shell.html.twig` | Shared inline CMS modal shell | FR-ADM-002 |
| `Resources/views/cms/edit_button.html.twig` | Pencil edit button for editable blocks | FR-ADM-002, FR-SEC-003 |
| `Resources/views/cms/item_create_button.html.twig` | Generic inline create button | FR-ADM-002 |
| `Resources/views/cms/item_edit_button.html.twig` | Generic inline item edit button | FR-ADM-002 |
| `Resources/views/cms/faq_create_button.html.twig` | FAQ create button partial | FR-ADM-002 |
| `Resources/views/cms/faq_edit_button.html.twig` | FAQ edit button partial | FR-ADM-002 |
| `Resources/views/cms/seo_edit_button.html.twig` | SEO edit button partial | FR-ADM-002 |
| `Resources/views/cms/_icon_pencil.svg.twig` | Pencil icon partial | FR-ADM-002 |
| `Resources/views/cms/_icon_plus.svg.twig` | Plus icon partial | FR-ADM-002 |

## Resources — translations

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/NowoPageLayoutKitBundle.de.yaml` | UI strings (de) | FR-I18N-002 |
| `Resources/translations/NowoPageLayoutKitBundle.en.yaml` | UI strings (en) | FR-I18N-002 |
| `Resources/translations/NowoPageLayoutKitBundle.es.yaml` | UI strings (es) | FR-I18N-002 |
| `Resources/translations/NowoPageLayoutKitBundle.fr.yaml` | UI strings (fr) | FR-I18N-002 |
| `Resources/translations/NowoPageLayoutKitBundle.it.yaml` | UI strings (it) | FR-I18N-002 |
| `Resources/translations/NowoPageLayoutKitBundle.nl.yaml` | UI strings (nl) | FR-I18N-002 |
| `Resources/translations/NowoPageLayoutKitBundle.pt.yaml` | UI strings (pt) | FR-I18N-002 |

## Write events and transaction outbox

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Event/PageLayoutChangedEvent.php` | Block order of a page layout changed (scalars only) | FR-EVT-001 |
| `Event/PageBlockUpdatedEvent.php` | A page block was saved from the inline editor (scalars only) | FR-EVT-001 |
| `Event/PageBlocksMigratedEvent.php` | Legacy content migrated into typed blocks (scalars only) | FR-EVT-001, FR-LEG-002 |
| `Persistence/AfterCommitMiddleware.php` | DBAL middleware reporting real commits / rollbacks to observers | FR-EVT-001 |
| `Persistence/AfterCommitDriver.php` | Driver of the after-commit middleware | FR-EVT-001 |
| `Persistence/AfterCommitConnection.php` | Connection reporting the real transaction and savepoints | FR-EVT-001 |
| `Persistence/TransactionObserverInterface.php` | Observer contract of the after-commit middleware | FR-EVT-001 |
| `Persistence/PageLayoutOutbox.php` | Per-transaction outbox of the bundle write events | FR-EVT-001, FR-WRK-001 |
| `Persistence/PageLayoutPendingEventsInterface.php` | Write events recorded and not dispatched yet | FR-EVT-001 |
| `Persistence/PageLayoutTransaction.php` | Unit-of-work boundary of the bundle's own writes | FR-EVT-001, FR-WRK-001 |

## Entity manager isolation

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Persistence/PageLayoutEntityManagerFactory.php` | Resolves the manager that maps the bundle entities when none is configured | FR-ORM-003 |
| `Persistence/ExcludedEntityNamespaceDriver.php` | Maps nothing for the bundle namespace on every other entity manager | FR-ORM-003 |

## Concurrency

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `EventListener/PageLayoutConflictListener.php` | 409 / 503 + `CONFLICT_001` / `CONFLICT_002` for lock conflicts on the bundle's routes; domain 409 for a duplicate translation | FR-RUN-001 |
| `Exception/PageMigrationInProgressException.php` | Another migration holds the migration lock (nothing changed) | FR-RUN-001, FR-LEG-002 |
| `Persistence/PageMigrationLock.php` | Non-blocking named lock: one migration at a time | FR-RUN-001, FR-LEG-002 |
| `Service/PageLayoutReorderer.php` | Reorder write unit (page entries locked and re-read) | FR-RUN-001, FR-ADM-001 |

## UI page contract

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Ui/PageLayoutKitPages.php` | UiKit page view models of the admin screens (REQ-UI-003) | FR-ADM-001, FR-ADM-002 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Bundle entry | 1 | 1 |
| Command | 1 | 1 |
| Controllers | 3 | 3 |
| Dependency injection and compiler | 7 | 7 |
| Entities | 17 | 17 |
| Enums, locale, and model helpers | 5 | 5 |
| Event subscriber and security | 14 | 14 |
| Forms | 17 | 17 |
| Services | 6 | 6 |
| Repositories | 19 | 19 |
| Twig extension | 1 | 1 |
| Resources — config and assets | 6 | 6 |
| Resources — admin Twig views | 6 | 6 |
| Resources — public block Twig views | 7 | 7 |
| Resources — CMS Twig views | 9 | 9 |
| Resources — translations | 7 | 7 |
| Write events and transaction outbox | 10 | 10 |
| Entity manager isolation | 2 | 2 |
| Concurrency | 4 | 4 |
| UI page contract | 1 | 1 |
| **Total production sources** | **143** | **143** |
