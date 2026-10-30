# Usage

How to manage ordered page blocks, render them publicly, and enable inline CMS editing.

## Table of contents

- [Admin layout management](#admin-layout-management)
- [Public rendering with PageBlockProvider](#public-rendering-with-pageblockprovider)
- [Twig rendering](#twig-rendering)
- [Inline CMS modals](#inline-cms-modals)
- [Custom access logic](#custom-access-logic)
- [Legacy migration command](#legacy-migration-command)
- [Twig overrides](#twig-overrides)
- [Rich text and host integration notes](#rich-text-and-host-integration-notes)

## Admin layout management

The bundle ships a reorder UI for configured page keys:

- `/admin/pages/home/layout`
- `/admin/pages/contact/layout`

More page keys become available when added to `nowo_page_layout_kit.pages`.

The screen lists the enabled `PageLayoutEntry` rows for that page and lets editors reorder them with a CSRF-protected Symfony form (`PageLayoutReorderType`: a collection of row entries with hidden block id and integer position). Block editing links open bundle-managed edit endpoints under `/admin/page-blocks/...`.

## Public rendering with PageBlockProvider

Inject `PageBlockProvider` into your controller or application service:

```php
use Nowo\PageLayoutKitBundle\Service\PageBlockProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function __invoke(PageBlockProvider $pageBlockProvider): Response
    {
        return $this->render('home/index.html.twig', [
            'pageMeta' => $pageBlockProvider->pageMeta('home'),
            'layout' => $pageBlockProvider->getLayout('home'),
        ]);
    }
}
```

`getLayout()` returns an ordered list of `PageBlockView` objects. Each item contains:

- `pageKey`
- `type`
- `blockId`
- `sectionKey`
- `data`
- `templateName()`

`pageMeta()` extracts page title and description from the resolved layout, with locale fallback.

## Twig rendering

Render the resolved layout by looping over the block views:

```twig
{% for block in layout %}
  {{ include('@NowoPageLayoutKitBundle/blocks/_wrapper.html.twig', {block: block}) }}
{% endfor %}

{{ include('@NowoPageLayoutKitBundle/cms/modal_shell.html.twig') }}
```

`_wrapper.html.twig` renders:

1. The public block template returned by `block.templateName()`
2. The inline edit pencil when `nowo_page_layout_kit_can_edit()` returns `true`

Block templates live under the Twig namespace `@NowoPageLayoutKitBundle/blocks/`:

- `hero.html.twig`
- `text.html.twig`
- `cards.html.twig`
- `list.html.twig`
- `cta.html.twig`
- `compare.html.twig`

## Inline CMS modals

Inline editing is powered by:

- `PageBlockEditController`
- `cms_modal_controller.ts`
- `@NowoPageLayoutKitBundle/admin/_modal_form.html.twig`
- `@NowoPageLayoutKitBundle/cms/modal_shell.html.twig`

Supported modal-editable block types are the six `PageBlockType` values:

- `hero`
- `text`
- `cards`
- `list`
- `cta`
- `compare`

When the current user can edit, the wrapper adds a pencil button. Clicking it loads the appropriate edit form over AJAX and submits changes back to the bundle endpoint.

### Registering the `cms-modal` Stimulus controller

The pencil buttons and `cms/modal_shell.html.twig` use the Stimulus identifier `cms-modal`. The controller source is
`src/Resources/assets/src/cms_modal_controller.ts`; the bundle ships two builds in `src/Resources/public/js/`
(published by `php bin/console assets:install` under `public/bundles/nowopagelayoutkit/js/`):

| File | Format | Use it when |
| --- | --- | --- |
| `cms_modal_controller.js` | ES module, imports `@hotwired/stimulus` | AssetMapper / importmap or any ES module bundler |
| `cms_modal_controller.iife.js` | IIFE over the Stimulus UMD global `window.Stimulus`; exposes `window.NowoPageLayoutKitCmsModalController` | Pages without a module loader |

AssetMapper / importmap (StimulusBundle already maps `@hotwired/stimulus`): expose the build directory and add an
importmap entry, then register the controller.

```yaml
# config/packages/asset_mapper.yaml
framework:
    asset_mapper:
        paths:
            - assets/
            - vendor/nowo-tech/page-layout-kit-bundle/src/Resources/public/js: nowo-page-layout-kit
```

```php
// importmap.php
'nowo-page-layout-kit/cms-modal' => ['path' => 'nowo-page-layout-kit/cms_modal_controller.js'],
```

```js
// assets/bootstrap.js (StimulusBundle)
import CmsModalController from 'nowo-page-layout-kit/cms-modal';

app.register('cms-modal', CmsModalController);
```

Bundlers that compile TypeScript can import the source instead
(`vendor/nowo-tech/page-layout-kit-bundle/src/Resources/assets/src/cms_modal_controller.ts`). Without a module loader,
load the Stimulus UMD build first, then
`<script src="/bundles/nowopagelayoutkit/js/cms_modal_controller.iife.js"></script>` and call
`Stimulus.Application.start().register('cms-modal', window.NowoPageLayoutKitCmsModalController)`.

CKEditor 5 fields inside the modal are mounted through `window.NowoCkeditor5Editor` when that API is present
(NowoCkeditor5Bundle); the controller waits up to two seconds for it and otherwise renders plain fields.

## Custom access logic

Role-based access is the default:

```yaml
nowo_page_layout_kit:
    security:
        access_roles: [ROLE_EDITOR]
```

For project-specific rules, implement `PageLayoutKitAccessCheckerInterface`:

```php
namespace App\Security;

use Nowo\PageLayoutKitBundle\Security\PageLayoutKitAccessCheckerInterface;

final class CmsEditorAccessChecker implements PageLayoutKitAccessCheckerInterface
{
    public function canAccess(): bool
    {
        // your project logic
        return true;
    }
}
```

Register it in configuration:

```yaml
nowo_page_layout_kit:
    security:
        access_checker: App\Security\CmsEditorAccessChecker
```

## Legacy migration command

If your application still stores page content in a legacy provider, wire `LegacyPageContentProviderInterface` and migrate to typed blocks:

```bash
php bin/console nowo:page-layout:migrate --if-empty
php bin/console nowo:page-layout:migrate --force
```

The command creates the default typed layout for `home` and `contact` from the legacy content source.

`PageBlockProvider` also falls back to the legacy provider automatically when no typed layout entries exist for the requested page.

## Twig overrides

The bundle registers the Twig namespace **`@NowoPageLayoutKitBundle`** for `src/Resources/views/` in `TwigPathsPass`
(always on; there is no feature flag). When `templates/bundles/NowoPageLayoutKitBundle/` exists in the application, the
pass prepends it to the namespace and then appends the bundle views, so **an application file at
`templates/bundles/NowoPageLayoutKitBundle/<subpath>` always wins** over the bundle copy. Bundle code renders only
logical names (`@NowoPageLayoutKitBundle/...`).

### Override vs upgrade (freeze rule)

An application file at the override path always wins and will not pick up vendor changes for that path until removed
or merged. A full-file override is an escape hatch: that `<subpath>` stays frozen on every `composer update` while the
rest of the bundle keeps upgrading.

Prefer the upgrade-safe options, from lightest to heaviest:

1. **Configuration, no Twig fork:** point `nowo_page_layout_kit.web_ui.layout_template` at your own admin layout or a
   bridge template (Twig global `nowo_page_layout_kit_layout`, see [CONFIGURATION.md](CONFIGURATION.md)) and pick
   `web_ui.css_framework`. The reorder and edit screens then render inside your chrome and keep receiving fixes.
2. **CSS only:** style the `nowo-ui-*` / `cms-*` classes the templates already emit, or use UiKit theme slots.
3. **Own public templates:** `PageBlockProvider::getLayout()` returns `PageBlockView` objects with `type`, `sectionKey`
   and `data`; your page template can include your own partial per `block.type.value` instead of overriding
   `blocks/*.html.twig`, and still include `@NowoPageLayoutKitBundle/cms/edit_button.html.twig` for the pencil.
4. **Surgical override:** copy one small partial (for example `cms/edit_button.html.twig` or a single
   `blocks/<type>.html.twig`) rather than a whole page.
5. **Full-file override:** copy a page (`admin/layout/index.html.twig`, `admin/_modal_form.html.twig`) only for a deep
   redesign, and accept that it is frozen.

**Recovering upstream after a freeze:** delete (or rename aside) the application override, clear the cache
(`php bin/console cache:clear`), and re-apply only the customisation you still need. Diff your copy against the new
vendor file under `vendor/nowo-tech/page-layout-kit-bundle/src/Resources/views/` before re-copying.

### Procedure

1. Pick the `<subpath>` from the table below (path relative to `src/Resources/views/`).
2. Create `templates/bundles/NowoPageLayoutKitBundle/<subpath>` in the application (same relative path and file name).
3. Clear the cache in dev if needed: `php bin/console cache:clear` (the override directory is detected when the
   container is compiled, so create it before the first cache warm-up or clear the cache afterwards).
4. Treat the file as a fork: it will not auto-update when the bundle ships a new version of that `<subpath>`.

Example: to restyle the hero block, create `templates/bundles/NowoPageLayoutKitBundle/blocks/hero.html.twig`.

### Overridable templates

| `<subpath>` (under `src/Resources/views/`) | Purpose |
| --- | --- |
| `admin/layout.html.twig` | Default admin HTML shell; prefer `web_ui.layout_template` over overriding it |
| `admin/layout/index.html.twig` | Reorder screen for one page key (`/admin/pages/{pageKey}/layout`) |
| `admin/blocks/edit_stub.html.twig` | Fallback admin page for a block without an inline modal form |
| `admin/_modal_form.html.twig` | Block edit modal body fetched over AJAX (form + FormKit locale tabs) |
| `admin/blocks/_modal_form.html.twig` | Alias that includes `admin/_modal_form.html.twig` (kept for hosts that include this path) |
| `blocks/_wrapper.html.twig` | Public wrapper per block: edit pencil + the block template from `block.templateName()` |
| `blocks/hero.html.twig` | Public hero block (starter template) |
| `blocks/text.html.twig` | Public text block (starter template) |
| `blocks/cards.html.twig` | Public cards block (starter template) |
| `blocks/list.html.twig` | Public list block (starter template) |
| `blocks/cta.html.twig` | Public call-to-action block (starter template; includes host `components/cta.html.twig`) |
| `blocks/compare.html.twig` | Public before/after compare block (starter template) |
| `cms/modal_shell.html.twig` | Modal chrome rendered once in the host page (`cms-modal` Stimulus controller targets) |
| `cms/edit_button.html.twig` | Inline pencil for a page block (shown when `nowo_page_layout_kit_can_edit()` is true) |
| `cms/seo_edit_button.html.twig` | Inline pencil for page SEO metadata |
| `cms/item_create_button.html.twig` | Inline "add" button for host resources (services, cases, blog) |
| `cms/item_edit_button.html.twig` | Inline pencil for host resources |
| `cms/faq_create_button.html.twig` | Inline "add FAQ" button for a service |
| `cms/faq_edit_button.html.twig` | Inline pencil for a service FAQ |
| `cms/_icon_pencil.svg.twig` | Pencil icon used by the edit buttons |
| `cms/_icon_plus.svg.twig` | Plus icon used by the create buttons |

Renaming or removing one of these `<subpath>` values is a breaking change and is listed in [UPGRADING.md](UPGRADING.md).
See [TEMPLATES.md](TEMPLATES.md) for the block edit modal internals.

## Rich text and host integration notes

Some shipped block templates are intentionally thin starter templates and may reference host-specific routes, components, or globals such as:

- `path('contact')`
- `path('services_index')`
- `include('components/cta.html.twig')`
- `site.services()`

Override those templates in real applications to match your own routes and design system.

Also note that several content fields are rendered with `|raw` in the default templates. Treat editor-authored HTML as trusted CMS content, or add your own sanitization strategy before rendering it publicly.

See also [INSTALLATION.md](INSTALLATION.md) and [CONFIGURATION.md](CONFIGURATION.md).
