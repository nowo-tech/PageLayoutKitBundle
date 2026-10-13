<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PageLayoutKitBundle\Entity\PageCardsBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCompareBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlock;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Nowo\PageLayoutKitBundle\Entity\PageListBlock;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlock;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Form\PageCardsBlockInlineModalType;
use Nowo\PageLayoutKitBundle\Form\PageCompareBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageCtaBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageHeroBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageListBlockInlineModalType;
use Nowo\PageLayoutKitBundle\Form\PageTextBlockModalType;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Service\PageBlockRegistry;
use Nowo\PageLayoutKitBundle\Service\PublicPageUrlResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Admin AJAX endpoints for editing composable page blocks (inline CMS modals).
 */
final class PageBlockEditController extends AbstractController
{
    public function __construct(
        private readonly PageBlockRegistry $pageBlockRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?PageLocales $pageLocales = null,
        private readonly ?PublicPageUrlResolver $publicPageUrls = null,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    #[Route('/admin/page-blocks/{type}/{id}/edit-modal', name: 'admin_page_blocks_edit_modal', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function editModal(string $type, int $id, Request $request): Response
    {
        $pageBlockType = $this->blockType($type);
        $block         = $this->pageBlockRegistry->get($pageBlockType, $id);

        if ($block === null) {
            throw $this->createNotFoundException('Page block not found.');
        }

        $this->ensureBlockTranslations($block);
        $locale = $request->getLocale();
        $form   = $this->createBlockForm($pageBlockType, $block);

        return $this->render('@NowoPageLayoutKitBundle/admin/_modal_form.html.twig', [
            'form'   => $form,
            'locale' => $locale,
        ]);
    }

    #[Route('/admin/page-blocks/{type}/{id}', name: 'admin_page_blocks_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(string $type, int $id, Request $request): Response
    {
        $pageBlockType = $this->blockType($type);
        $block         = $this->pageBlockRegistry->get($pageBlockType, $id);

        if ($block === null) {
            throw $this->createNotFoundException('Page block not found.');
        }

        $this->ensureBlockTranslations($block);
        $locale = $request->getLocale();
        $form   = $this->createBlockForm($pageBlockType, $block);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('@NowoPageLayoutKitBundle/admin/_modal_form.html.twig', [
                'form'   => $form,
                'locale' => $locale,
            ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $this->entityManager->flush();
        $this->addFlash('success', $this->translate('admin.flash.block_updated', 'Block updated.'));

        $referer = $request->headers->get('Referer');
        if ($referer !== null && $referer !== '') {
            return $this->redirect($referer);
        }

        /** @var list<string> $pages */
        $pages = $this->getParameter('nowo_page_layout_kit.pages');

        // No referer: go to the first public page the host actually routes, else back to the layout admin.
        return $this->redirect(
            $this->publicPageUrls?->resolveFirst($pages)
            ?? $this->generateUrl('admin_page_layout', ['pageKey' => $pages[0] ?? 'home']),
        );
    }

    #[Route('/admin/page-blocks/{type}/{id}/edit', name: 'admin_page_blocks_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function edit(string $type, int $id): Response
    {
        $pageBlockType = $this->blockType($type);
        $block         = $this->pageBlockRegistry->get($pageBlockType, $id);

        if ($block === null) {
            throw $this->createNotFoundException('Page block not found.');
        }

        return $this->render('@NowoPageLayoutKitBundle/admin/blocks/edit_stub.html.twig', [
            'page_title' => $this->translate('admin.blocks.edit.title', 'Edit block'),
            'block_type' => $pageBlockType,
            'block_id'   => $id,
        ]);
    }

    /**
     * Unknown `{type}` segments are a 404, not a ValueError (500).
     */
    private function blockType(string $type): PageBlockType
    {
        return PageBlockType::tryFrom($type) ?? throw $this->createNotFoundException('Unknown page block type.');
    }

    private function translate(string $id, string $fallback): string
    {
        return $this->translator?->trans($id, [], 'NowoPageLayoutKitBundle') ?? $fallback;
    }

    /**
     * @return FormInterface<mixed>
     */
    private function createBlockForm(
        PageBlockType $pageBlockType,
        PageHeroBlock|PageTextBlock|PageCardsBlock|PageListBlock|PageCtaBlock|PageCompareBlock $block,
    ): FormInterface {
        $action = $this->generateUrl('admin_page_blocks_update', [
            'type' => $pageBlockType->value,
            'id'   => $block->getId(),
        ]);

        return match ($pageBlockType) {
            PageBlockType::Hero => $this->createForm(PageHeroBlockModalType::class, $block, [
                'action' => $action,
                'method' => 'POST',
            ]),
            PageBlockType::Text => $this->createForm(PageTextBlockModalType::class, $block, [
                'action'       => $action,
                'method'       => 'POST',
                'include_meta' => $block instanceof PageTextBlock && $block->getSectionKey() === 'contact_header',
            ]),
            PageBlockType::Cta => $this->createForm(PageCtaBlockModalType::class, $block, [
                'action' => $action,
                'method' => 'POST',
            ]),
            PageBlockType::Compare => $this->createForm(PageCompareBlockModalType::class, $block, [
                'action' => $action,
                'method' => 'POST',
            ]),
            PageBlockType::Cards => $this->createForm(PageCardsBlockInlineModalType::class, null, [
                'block'  => $block,
                'action' => $action,
                'method' => 'POST',
            ]),
            PageBlockType::List => $this->createForm(PageListBlockInlineModalType::class, null, [
                'block'  => $block,
                'action' => $action,
                'method' => 'POST',
            ]),
        };
    }

    private function ensureBlockTranslations(
        PageHeroBlock|PageTextBlock|PageCardsBlock|PageListBlock|PageCtaBlock|PageCompareBlock $block,
    ): void {
        $block->ensureTranslations($this->pageLocales);

        if ($block instanceof PageCardsBlock) {
            foreach ($block->getItems() as $item) {
                $item->ensureTranslations($this->pageLocales);
            }
        }

        if ($block instanceof PageListBlock) {
            foreach ($block->getItems() as $item) {
                $item->ensureTranslations($this->pageLocales);
            }
        }
    }
}
