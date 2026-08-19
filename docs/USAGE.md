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
2. The inline edit pencil when `nowo_page_layout_kit_can_edit` is `true`

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

Application templates under `templates/bundles/NowoPageLayoutKitBundle/` override the bundle copy for the same relative path.

Common override targets:

- `templates/bundles/NowoPageLayoutKitBundle/blocks/hero.html.twig`
- `templates/bundles/NowoPageLayoutKitBundle/blocks/text.html.twig`
- `templates/bundles/NowoPageLayoutKitBundle/admin/layout/index.html.twig`
- `templates/bundles/NowoPageLayoutKitBundle/cms/modal_shell.html.twig`

This precedence is installed by the bundle compiler pass before the bundle view path is added.

## Rich text and host integration notes

Some shipped block templates are intentionally thin starter templates and may reference host-specific routes, components, or globals such as:

- `path('contact')`
- `path('services_index')`
- `include('components/cta.html.twig')`
- `site.services()`

Override those templates in real applications to match your own routes and design system.

Also note that several content fields are rendered with `|raw` in the default templates. Treat editor-authored HTML as trusted CMS content, or add your own sanitization strategy before rendering it publicly.

See also [INSTALLATION.md](INSTALLATION.md) and [CONFIGURATION.md](CONFIGURATION.md).
