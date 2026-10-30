<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Support;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderData;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderRowType;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderType;
use Nowo\PageLayoutKitBundle\Ui\PageLayoutKitPages;
use ReflectionProperty;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Page contexts of the REQ-UI-003 page contract test: the view models come from {@see PageLayoutKitPages} (the
 * service the controllers use), the reorder FormView from the real form types (FormKit profile `page_layout_kit` with
 * the bundle defaults, CSRF), the other variables mirror the controllers.
 */
final class PageFixtures
{
    /**
     * Reorder screen (`admin/layout/index.html.twig`).
     *
     * @return array<string, mixed>
     */
    public static function layoutIndex(bool $empty = false, ?string $viewPageUrl = '/'): array
    {
        $pages   = new PageLayoutKitPages();
        $entries = $empty ? [] : [
            self::entry(10, PageBlockType::Hero, 1, 0),
            self::entry(11, PageBlockType::Text, 2, 1),
            self::entry(12, PageBlockType::Cards, 3, 2),
        ];
        $form = self::reorderForm($entries);

        return [
            'page'          => $pages->layoutPage('home', $form, $entries !== [], $viewPageUrl),
            'layout_table'  => $pages->layoutTable($entries, static fn (PageLayoutEntry $entry): string => '/admin/page-blocks/' . $entry->getBlockType()->value . '/' . $entry->getBlockId() . '/edit'),
            'layout_empty'  => $pages->layoutEmpty(),
            'page_title'    => 'Layout: home',
            'page_key'      => 'home',
            'entries'       => $entries,
            'reorder_form'  => $form,
            'view_page_url' => $viewPageUrl,
        ];
    }

    /**
     * Block page (`admin/blocks/edit_stub.html.twig`).
     *
     * @return array<string, mixed>
     */
    public static function blockEdit(bool $orphan = false): array
    {
        $pageKey     = $orphan ? null : 'home';
        $backUrl     = $orphan ? null : '/admin/pages/home/layout';
        $viewPageUrl = $orphan ? null : '/';

        return [
            'page'          => (new PageLayoutKitPages())->blockEditPage(PageBlockType::Cards, 3, $pageKey, $backUrl, $viewPageUrl),
            'page_title'    => 'Edit block',
            'block_type'    => PageBlockType::Cards,
            'block_id'      => 3,
            'page_key'      => $pageKey,
            'back_url'      => $backUrl,
            'view_page_url' => $viewPageUrl,
        ];
    }

    /**
     * @param list<PageLayoutEntry> $entries
     */
    private static function reorderForm(array $entries): FormView
    {
        $merger = new FormOptionsMerger([
            'page_layout_kit' => [
                'translation_domain' => 'NowoPageLayoutKitBundle',
                'defaults'           => ['attr' => ['class' => 'nowo-ui-input'], 'row_attr' => []],
                'field_types'        => [],
            ],
        ], 'page_layout_kit', new ConstraintDefinitionFactory());

        $tokens = new class implements CsrfTokenManagerInterface {
            public function getToken(string $tokenId): CsrfToken
            {
                return new CsrfToken($tokenId, 'REDACTED-TEST-' . $tokenId);
            }

            public function refreshToken(string $tokenId): CsrfToken
            {
                return $this->getToken($tokenId);
            }

            public function removeToken(string $tokenId): ?string
            {
                return null;
            }

            public function isTokenValid(CsrfToken $token): bool
            {
                return true;
            }
        };

        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension($tokens))
            ->addTypes([
                new PageLayoutReorderType($merger, new FormTypeMap()),
                new PageLayoutReorderRowType($merger, new FormTypeMap()),
            ])
            ->getFormFactory();

        return $factory->create(PageLayoutReorderType::class, PageLayoutReorderData::fromEntries($entries), [
            'action' => '/admin/pages/home/layout',
            'method' => 'POST',
        ])->createView();
    }

    private static function entry(int $id, PageBlockType $type, int $blockId, int $position): PageLayoutEntry
    {
        $entry = (new PageLayoutEntry())
            ->setPageKey('home')
            ->setBlockType($type)
            ->setBlockId($blockId)
            ->setPosition($position);
        (new ReflectionProperty(PageLayoutEntry::class, 'id'))->setValue($entry, $id);

        return $entry;
    }
}
