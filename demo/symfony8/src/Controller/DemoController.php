<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\PageLayoutKitBundle\Service\PageBlockProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class DemoController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(PageBlockProvider $pageBlockProvider, Request $request): Response
    {
        return $this->renderPage('home', 'Home', 'Public home page backed by PageLayoutKit data.', $pageBlockProvider, $request);
    }

    #[Route('/contact', name: 'contact')]
    public function contact(PageBlockProvider $pageBlockProvider, Request $request): Response
    {
        return $this->renderPage('contact', 'Contact', 'Public contact page backed by PageLayoutKit data.', $pageBlockProvider, $request);
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('home');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    private function renderPage(
        string $pageKey,
        string $fallbackTitle,
        string $pageDescription,
        PageBlockProvider $pageBlockProvider,
        Request $request,
    ): Response {
        $locale = $request->getLocale();
        $layout = $pageBlockProvider->getLayout($pageKey, $locale);
        $meta = $pageBlockProvider->pageMeta($pageKey, $locale);

        return $this->render('demo/index.html.twig', [
            'current_page_key' => $pageKey,
            'page_key' => $pageKey,
            'page_title' => '' !== trim((string) ($meta['title'] ?? '')) ? (string) $meta['title'] : $fallbackTitle,
            'page_description' => '' !== trim((string) ($meta['description'] ?? '')) ? (string) $meta['description'] : $pageDescription,
            'layout' => $layout,
        ]);
    }
}
