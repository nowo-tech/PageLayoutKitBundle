<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PageLayoutKitBundle\Controller\RequiresValidFormTrait;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderData;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderType;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function in_array;
use function sprintf;

/**
 * Admin UI for ordering enabled page layout entries (home/contact).
 */
final class PageLayoutController extends AbstractController
{
    use RequiresValidFormTrait;

    public function __construct(
        private readonly PageLayoutEntryRepository $pageLayoutEntryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    #[Route('/admin/pages/{pageKey}/layout', name: 'admin_page_layout', requirements: ['pageKey' => '[a-z0-9_-]+'])]
    public function layout(string $pageKey, Request $request): Response
    {
        /** @var list<string> $pages */
        $pages = $this->getParameter('nowo_page_layout_kit.pages');
        if (!in_array($pageKey, $pages, true)) {
            throw $this->createNotFoundException(sprintf('Unknown page key "%s".', $pageKey));
        }

        $entries     = $this->pageLayoutEntryRepository->findEnabledByPageKey($pageKey);
        $reorderForm = $this->createReorderForm($pageKey, $entries);

        if ($request->isMethod('POST')) {
            $this->reorder($request, $entries, $reorderForm);

            return $this->redirectToRoute('admin_page_layout', ['pageKey' => $pageKey]);
        }

        return $this->render('@NowoPageLayoutKitBundle/admin/layout/index.html.twig', [
            'page_title'   => sprintf('Layout: %s', $pageKey),
            'page_key'     => $pageKey,
            'entries'      => $entries,
            'reorder_form' => $reorderForm,
        ]);
    }

    /**
     * @param list<PageLayoutEntry> $entries
     *
     * @return FormInterface<PageLayoutReorderData>
     */
    private function createReorderForm(string $pageKey, array $entries): FormInterface
    {
        /** @var FormInterface<PageLayoutReorderData> $form */
        $form = $this->formFactory->create(
            PageLayoutReorderType::class,
            PageLayoutReorderData::fromEntries($entries),
            [
                'action' => $this->generateUrl('admin_page_layout', ['pageKey' => $pageKey]),
                'method' => 'POST',
            ],
        );

        return $form;
    }

    /**
     * @param list<PageLayoutEntry> $entries
     * @param FormInterface<PageLayoutReorderData> $reorderForm
     */
    private function reorder(Request $request, array $entries, FormInterface $reorderForm): void
    {
        $reorderForm->handleRequest($request);
        $this->requireValidForm($reorderForm);

        $reorderForm->getData()?->applyToEntries($entries);

        $this->entityManager->flush();
        $this->addFlash('success', 'Order updated.');
    }
}
