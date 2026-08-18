# Code inventory — PageLayoutKitBundle baseline

**Baseline spec:** [`spec.md`](spec.md)  
**Package:** `nowo-tech/page-layout-kit-bundle`  
**Last audited:** 2026-08-18

Maps **100%** of production files under `src/` (**97 units**).

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
| `Security/PageLayoutKitAccessCheckerInterface.php` | Access checker contract | FR-SEC-001 |
| `Security/ConfigurablePageLayoutKitAccessChecker.php` | Role-based access checker | FR-SEC-001 |
| `Security/AllowAllPageLayoutKitAccessChecker.php` | Demo-style unauthenticated access checker | FR-SEC-002 |

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

## Services

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Service/PageBlockProvider.php` | Resolves ordered public block views with locale fallback | FR-REN-001, FR-REN-003, FR-LEG-001 |
| `Service/PageBlockView.php` | Public block view object with template resolution | FR-REN-001, FR-REN-002 |
| `Service/PageBlockRegistry.php` | Loads typed block aggregates by type and id | FR-ADM-002, FR-ORM-001 |
| `Service/PageBlockMigrator.php` | Converts legacy content into typed blocks and layout entries | FR-LEG-002, FR-CMP-001 |

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
| `Twig/PageLayoutKitExtension.php` | Publishes Twig globals for layout shell, pages, locale, and editability | FR-SEC-003, FR-TWIG-001 |

## Resources — config and assets

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Resources/config/routing.yaml` | Attribute-route import | FR-DI-001 |
| `Resources/config/services.yaml` | Service and form wiring | FR-DI-001 |
| `Resources/assets/cms_modal_controller.ts` | Stimulus controller for inline CMS modal flow | FR-ADM-002 |

## Resources — admin Twig views

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Resources/views/admin/layout.html.twig` | Default admin shell layout | FR-CFG-002, FR-TWIG-001 |
| `Resources/views/admin/_locale_tabs.html.twig` | Locale tab UI partial | FR-I18N-001, FR-ADM-002 |
| `Resources/views/admin/_modal_form.html.twig` | Shared admin modal form | FR-ADM-002 |
| `Resources/views/admin/layout/index.html.twig` | Reorder page layout screen | FR-ADM-001 |
| `Resources/views/admin/blocks/_modal_form.html.twig` | Block modal body partial | FR-ADM-002 |
| `Resources/views/admin/blocks/edit_stub.html.twig` | Block edit stub page | FR-ADM-002 |

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

## Coverage summary

| Metric | Value |
| --- | --- |
| Production units mapped | 97 |
| Unmapped files under `src/` | 0 |
