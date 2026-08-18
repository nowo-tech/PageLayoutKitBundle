<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Controller\Admin;

use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use Nowo\PageLayoutKitBundle\Controller\RequiresValidFormTrait;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin UI for ordering enabled page layout entries (home/contact).
 */
final class PageLayoutController extends AbstractController
{
    use RequiresValidFormTrait;

    public function __construct(
        private readonly PageLayoutEntryRepository $pageLayoutEntryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfOnlyFormFactory $csrfOnlyFormFactory,
    ) {
    }

    #[Route('/admin/pages/{pageKey}/layout', name: 'admin_page_layout', requirements: ['pageKey' => '[a-z0-9_-]+'])]
    public function layout(string $pageKey, Request $request): Response
    {
        /** @var list<string> $pages */
        $pages = $this->getParameter('nowo_page_layout_kit.pages');
        if (!\in_array($pageKey, $pages, true)) {
            throw $this->createNotFoundException(sprintf('Unknown page key "%s".', $pageKey));
        }

        $entries = $this->pageLayoutEntryRepository->findEnabledByPageKey($pageKey);

        if ($request->isMethod('POST')) {
            $this->reorder($request, $entries, $pageKey);

            return $this->redirectToRoute('admin_page_layout', ['pageKey' => $pageKey]);
        }

        return $this->render('@NowoPageLayoutKitBundle/admin/layout/index.html.twig', [
            'page_title' => sprintf('Layout: %s', $pageKey),
            'page_key' => $pageKey,
            'entries' => $entries,
        ]);
    }

    /** @param list<\Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry> $entries */
    private function reorder(Request $request, array $entries, string $pageKey): void
    {
        $form = $this->csrfOnlyFormFactory->createNamed(
            $this->generateUrl('admin_page_layout', ['pageKey' => $pageKey]),
            'page_layout_reorder',
        );
        $form->handleRequest($request);
        $this->requireValidCsrfForm($form);

        $order = $request->request->all('order');

        foreach ($entries as $entry) {
            $id = (string) $entry->getId();

            if (isset($order[$id]) && is_numeric($order[$id])) {
                $entry->setPosition((int) $order[$id]);
            }
        }

        $this->entityManager->flush();
        $this->addFlash('success', 'Order updated.');;
    }
}
