<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\PageLayoutKitBundle\Controller\Admin\PageBlockEditController;
use Nowo\PageLayoutKitBundle\Entity\PageCardItem;
use Nowo\PageLayoutKitBundle\Entity\PageCardsBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCompareBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlock;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Entity\PageListBlock;
use Nowo\PageLayoutKitBundle\Entity\PageListItem;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlock;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Form\PageCardsBlockInlineModalType;
use Nowo\PageLayoutKitBundle\Form\PageCompareBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageCtaBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageHeroBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageListBlockInlineModalType;
use Nowo\PageLayoutKitBundle\Form\PageTextBlockModalType;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Repository\PageCardsBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageCompareBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageCtaBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageHeroBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageListBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageTextBlockRepository;
use Nowo\PageLayoutKitBundle\Service\PageBlockRegistry;
use Nowo\PageLayoutKitBundle\Service\PublicPageUrlResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class PageBlockEditControllerTest extends TestCase
{
    private function pageLocales(): PageLocales
    {
        return new PageLocales('es', ['es', 'en']);
    }

    public function testEditModalThrowsNotFoundWhenRegistryMissesBlock(): void
    {
        $controller = new PageBlockEditController(
            $this->createRegistry(),
            $this->createMock(EntityManagerInterface::class),
            $this->pageLocales(),
        );
        $controller->setContainer($this->createControllerContainer());

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Page block not found.');

        $controller->editModal('hero', 15, Request::create('/admin/page-blocks/hero/15/edit-modal', 'GET'));
    }

    public function testEditModalRendersResolvedFormAndLocale(): void
    {
        $block     = $this->withId(new PageHeroBlock(), 5);
        $form      = $this->createMock(FormInterface::class);
        $formCalls = [];

        $controller = new PageBlockEditController(
            $this->createRegistry([PageBlockType::Hero->value => [5 => $block]]),
            $this->createMock(EntityManagerInterface::class),
            $this->pageLocales(),
        );
        $controller->setContainer($this->createControllerContainer(form: $form, formCalls: $formCalls));

        $request = Request::create('/admin/page-blocks/hero/5/edit-modal', 'GET');
        $request->setLocale('en');
        $response = $controller->editModal('hero', 5, $request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('render:@NowoPageLayoutKitBundle/admin/_modal_form.html.twig:en', $response->getContent());
        self::assertNotNull($block->getTranslation('es'));
        self::assertNotNull($block->getTranslation('en'));
        self::assertSame(PageHeroBlockModalType::class, $formCalls[0]['type']);
        self::assertSame($block, $formCalls[0]['data']);
    }

    public function testUpdateReturnsUnprocessableEntityWhenSubmittedFormIsInvalid(): void
    {
        $block = $this->withId((new PageTextBlock())->setSectionKey('contact_header'), 7);
        $form  = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(false);
        $formCalls = [];

        $request = Request::create('/admin/page-blocks/text/7', 'POST');
        $request->setLocale('es');

        $controller = new PageBlockEditController(
            $this->createRegistry([PageBlockType::Text->value => [7 => $block]]),
            $this->createMock(EntityManagerInterface::class),
            $this->pageLocales(),
        );
        $controller->setContainer($this->createControllerContainer(form: $form, request: $request, formCalls: $formCalls));

        $response = $controller->update('text', 7, $request);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('render:@NowoPageLayoutKitBundle/admin/_modal_form.html.twig:es', $response->getContent());
        self::assertTrue($formCalls[0]['options']['include_meta']);
    }

    public function testUpdateFlushesAndRedirectsToRefererWhenFormIsValid(): void
    {
        $block = $this->withId(new PageCtaBlock(), 9);
        $form  = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $formCalls = [];

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $request = Request::create('/admin/page-blocks/cta/9', 'POST');
        $request->headers->set('Referer', '/admin/layout/home');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $controller = new PageBlockEditController(
            $this->createRegistry([PageBlockType::Cta->value => [9 => $block]]),
            $entityManager,
            $this->pageLocales(),
        );
        $controller->setContainer($this->createControllerContainer(form: $form, request: $request, formCalls: $formCalls));

        $response = $controller->update('cta', 9, $request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/layout/home', $response->getTargetUrl());
        self::assertSame(['Block updated.'], $session->getFlashBag()->get('success'));
        self::assertSame(PageCtaBlockModalType::class, $formCalls[0]['type']);
    }

    public function testUpdateFallsBackToPublicPageUrlWithoutReferer(): void
    {
        $response = $this->updateWithoutReferer(static fn (string $route): string => match ($route) {
            'home'                     => '/generated/home',
            'admin_page_blocks_update' => '/update',
            default                    => throw new RouteNotFoundException($route),
        });

        self::assertSame('/generated/home', $response->getTargetUrl());
    }

    public function testUpdateFallsBackToLayoutAdminWhenHostHasNoPublicRoute(): void
    {
        // Regression: the bundle used to call generateUrl('home') and 500 when the host had no "home" route.
        $response = $this->updateWithoutReferer(static fn (string $route, array $parameters = []): string => match ($route) {
            'admin_page_layout'        => '/admin/pages/' . $parameters['pageKey'] . '/layout',
            'admin_page_blocks_update' => '/update',
            default                    => throw new RouteNotFoundException($route),
        });

        self::assertSame('/admin/pages/home/layout', $response->getTargetUrl());
    }

    /**
     * Regression for the 2026-10-30 audit (Appendix BJ, L1): the post-save redirect followed any Referer.
     */
    #[DataProvider('refererRedirects')]
    public function testUpdateOnlyFollowsSameHostReferer(string $referer, string $expectedTarget): void
    {
        $block = $this->withId(new PageCompareBlock(), 12);
        $form  = $this->createConfiguredMock(FormInterface::class, ['isSubmitted' => true, 'isValid' => true]);

        $request = Request::create('https://cms.example.test/admin/page-blocks/compare/12', 'POST');
        $request->headers->set('Referer', $referer);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route): string => match ($route) {
            'home'                     => '/generated/home',
            'admin_page_blocks_update' => '/update',
            default                    => throw new RouteNotFoundException($route),
        });

        $controller = new PageBlockEditController(
            $this->createRegistry([PageBlockType::Compare->value => [12 => $block]]),
            $this->createMock(EntityManagerInterface::class),
            $this->pageLocales(),
            new PublicPageUrlResolver($urls),
        );
        $container = $this->createControllerContainer(form: $form, request: $request);
        $container->set('router', $urls);
        $controller->setContainer($container);

        $response = $controller->update('compare', 12, $request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame($expectedTarget, $response->getTargetUrl());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refererRedirects(): iterable
    {
        yield 'same origin page' => ['https://cms.example.test/es/servicios?x=1', 'https://cms.example.test/es/servicios?x=1'];
        yield 'same origin root' => ['https://cms.example.test', 'https://cms.example.test'];
        yield 'same origin query' => ['https://cms.example.test?x=1', 'https://cms.example.test?x=1'];
        yield 'root-relative path' => ['/es/servicios', '/es/servicios'];
        yield 'other host' => ['https://evil.example/phish', '/generated/home'];
        yield 'host prefix trick' => ['https://cms.example.test.evil.example/x', '/generated/home'];
        yield 'userinfo trick' => ['https://cms.example.test@evil.example/x', '/generated/home'];
        yield 'other scheme same host' => ['http://cms.example.test/x', '/generated/home'];
        yield 'protocol-relative' => ['//evil.example/x', '/generated/home'];
        yield 'backslash' => ['/\\evil.example/x', '/generated/home'];
        yield 'control character' => ["/\tx", '/generated/home'];
        yield 'javascript' => ['javascript:alert(1)', '/generated/home'];
    }

    public function testUpdateFlashIsTranslated(): void
    {
        $block = $this->withId(new PageCtaBlock(), 9);
        $form  = $this->createConfiguredMock(FormInterface::class, ['isSubmitted' => true, 'isValid' => true]);

        $request = Request::create('/admin/page-blocks/cta/9', 'POST');
        $request->headers->set('Referer', '/back');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())->method('trans')
            ->with('admin.flash.block_updated', [], 'NowoPageLayoutKitBundle')
            ->willReturn('Bloque actualizado.');

        $controller = new PageBlockEditController(
            $this->createRegistry([PageBlockType::Cta->value => [9 => $block]]),
            $this->createMock(EntityManagerInterface::class),
            $this->pageLocales(),
            null,
            $translator,
        );
        $controller->setContainer($this->createControllerContainer(form: $form, request: $request));

        $controller->update('cta', 9, $request);

        self::assertSame(['Bloque actualizado.'], $session->getFlashBag()->get('success'));
    }

    public function testUnknownBlockTypeIsNotFound(): void
    {
        $controller = new PageBlockEditController(
            $this->createRegistry(),
            $this->createMock(EntityManagerInterface::class),
            $this->pageLocales(),
        );
        $controller->setContainer($this->createControllerContainer());

        foreach (['editModal', 'update', 'edit'] as $action) {
            try {
                match ($action) {
                    'editModal' => $controller->editModal('evil', 1, Request::create('/')),
                    'update'    => $controller->update('evil', 1, Request::create('/', 'POST')),
                    'edit'      => $controller->edit('evil', 1),
                };
                self::fail($action . ' must 404 on an unknown block type');
            } catch (NotFoundHttpException $e) {
                self::assertSame('Unknown page block type.', $e->getMessage());
            }
        }
    }

    /**
     * @param callable(string, array<string, mixed>=): string $generate
     */
    private function updateWithoutReferer(callable $generate): RedirectResponse
    {
        $block = $this->withId(new PageCompareBlock(), 12);
        $form  = $this->createConfiguredMock(FormInterface::class, ['isSubmitted' => true, 'isValid' => true]);

        $request = Request::create('/admin/page-blocks/compare/12', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback($generate);

        $controller = new PageBlockEditController(
            $this->createRegistry([PageBlockType::Compare->value => [12 => $block]]),
            $this->createMock(EntityManagerInterface::class),
            $this->pageLocales(),
            new PublicPageUrlResolver($urls),
        );
        $container = $this->createControllerContainer(form: $form, request: $request);
        $container->set('router', $urls);
        $controller->setContainer($container);

        $response = $controller->update('compare', 12, $request);
        self::assertInstanceOf(RedirectResponse::class, $response);

        return $response;
    }

    public function testUpdateThrowsNotFoundWhenRegistryMissesBlock(): void
    {
        $form = $this->createConfiguredMock(FormInterface::class, [
            'isSubmitted' => true,
            'isValid'     => true,
        ]);

        $request = Request::create('/admin/page-blocks/hero/404', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $controller = new PageBlockEditController(
            $this->createRegistry(),
            $this->createMock(EntityManagerInterface::class),
            $this->pageLocales(),
        );
        $controller->setContainer($this->createControllerContainer(form: $form, request: $request));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Page block not found.');

        $controller->update('hero', 404, $request);
    }

    public function testEditThrowsNotFoundWhenRegistryMissesBlock(): void
    {
        $controller = new PageBlockEditController(
            $this->createRegistry(),
            $this->createMock(EntityManagerInterface::class),
            $this->pageLocales(),
        );
        $controller->setContainer($this->createControllerContainer());

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Page block not found.');

        $controller->edit('hero', 404);
    }

    public function testEditRendersStubForExistingBlock(): void
    {
        $controller = new PageBlockEditController(
            $this->createRegistry([PageBlockType::List->value => [3 => $this->withId(new PageListBlock(), 3)]]),
            $this->createMock(EntityManagerInterface::class),
            $this->pageLocales(),
        );
        $controller->setContainer($this->createControllerContainer());

        $response = $controller->edit('list', 3);

        self::assertSame('render:@NowoPageLayoutKitBundle/admin/blocks/edit_stub.html.twig:Edit block', $response->getContent());
    }

    public function testEditStubLinksBackToTheLayoutOfTheOwningPage(): void
    {
        $entry      = (new PageLayoutEntry())->setPageKey('contact');
        $parameters = $this->renderEditStub($entry);

        self::assertSame('contact', $parameters['page_key']);
        self::assertSame('/admin/pages/contact/layout', $parameters['back_url']);
        self::assertSame('/public/contact', $parameters['view_page_url']);
        self::assertSame(PageBlockType::List, $parameters['block_type']);
    }

    public function testEditStubFallsBackToTheFirstConfiguredPageWhenTheBlockIsNotInAnyLayout(): void
    {
        $parameters = $this->renderEditStub(null);

        self::assertSame('home', $parameters['page_key']);
        self::assertSame('/admin/pages/home/layout', $parameters['back_url']);
        self::assertSame('/public/home', $parameters['view_page_url']);
    }

    public function testEditStubHasNoLinksWhenNoPagesAreConfigured(): void
    {
        $parameters = $this->renderEditStub(null, []);

        self::assertNull($parameters['page_key']);
        self::assertNull($parameters['back_url']);
        self::assertNull($parameters['view_page_url']);
    }

    /**
     * @param list<string> $pages
     *
     * @return array<string, mixed>
     */
    private function renderEditStub(?PageLayoutEntry $entry, array $pages = ['home', 'contact']): array
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('findOneBy')
            ->with(['pageBlockType' => PageBlockType::List, 'blockId' => 3])
            ->willReturn($entry);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('getRepository')->with(PageLayoutEntry::class)->willReturn($repository);

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static function (string $route, array $parameters = []): string {
            if ($route === 'admin_page_layout') {
                return '/admin/pages/' . $parameters['pageKey'] . '/layout';
            }

            return '/public/' . $route;
        });

        $controller = new PageBlockEditController(
            $this->createRegistry([PageBlockType::List->value => [3 => $this->withId(new PageListBlock(), 3)]]),
            $entityManager,
            $this->pageLocales(),
            new PublicPageUrlResolver($urls),
        );

        $captured = [];
        $twig     = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $view, array $parameters = []) use (&$captured): string {
            $captured = $parameters;

            return $view;
        });

        $container = $this->createControllerContainer();
        $container->set('router', $urls);
        $container->set('twig', $twig);
        $container->set('parameter_bag', new ParameterBag(['nowo_page_layout_kit.pages' => $pages]));
        $controller->setContainer($container);

        $response = $controller->edit('list', 3);
        self::assertSame('@NowoPageLayoutKitBundle/admin/blocks/edit_stub.html.twig', $response->getContent());

        return $captured;
    }

    public function testCreateBlockFormSelectsTheExpectedFormTypeForEveryBlockKind(): void
    {
        $form       = $this->createMock(FormInterface::class);
        $formCalls  = [];
        $controller = new PageBlockEditController(
            $this->createRegistry(),
            $this->createMock(EntityManagerInterface::class),
            $this->pageLocales(),
        );
        $controller->setContainer($this->createControllerContainer(form: $form, formCalls: $formCalls));

        $method = new ReflectionMethod(PageBlockEditController::class, 'createBlockForm');

        $hero = $this->withId(new PageHeroBlock(), 1);
        $method->invoke($controller, PageBlockType::Hero, $hero);

        $text = $this->withId((new PageTextBlock())->setSectionKey('contact_header'), 2);
        $method->invoke($controller, PageBlockType::Text, $text);

        $cards = $this->withId(new PageCardsBlock(), 3);
        $method->invoke($controller, PageBlockType::Cards, $cards);

        $list = $this->withId(new PageListBlock(), 4);
        $method->invoke($controller, PageBlockType::List, $list);

        $cta = $this->withId(new PageCtaBlock(), 5);
        $method->invoke($controller, PageBlockType::Cta, $cta);

        $compare = $this->withId(new PageCompareBlock(), 6);
        $method->invoke($controller, PageBlockType::Compare, $compare);

        self::assertSame(PageHeroBlockModalType::class, $formCalls[0]['type']);
        self::assertSame(PageTextBlockModalType::class, $formCalls[1]['type']);
        self::assertTrue($formCalls[1]['options']['include_meta']);
        self::assertSame(PageCardsBlockInlineModalType::class, $formCalls[2]['type']);
        self::assertNull($formCalls[2]['data']);
        self::assertSame($cards, $formCalls[2]['options']['block']);
        self::assertSame(PageListBlockInlineModalType::class, $formCalls[3]['type']);
        self::assertSame(PageCtaBlockModalType::class, $formCalls[4]['type']);
        self::assertSame(PageCompareBlockModalType::class, $formCalls[5]['type']);
    }

    public function testEnsureBlockTranslationsAlsoHydratesNestedItems(): void
    {
        $cards = new PageCardsBlock();
        $cards->addItem(new PageCardItem());

        $list = new PageListBlock();
        $list->addItem(new PageListItem());

        $hero = new PageHeroBlock();

        $controller = new PageBlockEditController(
            $this->createRegistry(),
            $this->createMock(EntityManagerInterface::class),
            $this->pageLocales(),
        );
        $controller->setContainer($this->createControllerContainer());

        $method = new ReflectionMethod(PageBlockEditController::class, 'ensureBlockTranslations');
        $method->invoke($controller, $cards);
        $method->invoke($controller, $list);
        $method->invoke($controller, $hero);

        self::assertNotNull($cards->getTranslation('es'));
        $cardItem = $cards->getItems()->first();
        self::assertInstanceOf(PageCardItem::class, $cardItem);
        self::assertNotNull($cardItem->getTranslation('en'));
        self::assertNotNull($list->getTranslation('en'));
        $listItem = $list->getItems()->first();
        self::assertInstanceOf(PageListItem::class, $listItem);
        self::assertNotNull($listItem->getTranslation('es'));
        self::assertNotNull($hero->getTranslation('es'));
    }

    public function testEnsureBlockTranslationsUsesTheInjectedPageLocales(): void
    {
        $cards = new PageCardsBlock();
        $cards->addItem(new PageCardItem());

        $controller = new PageBlockEditController(
            $this->createRegistry(),
            $this->createStub(EntityManagerInterface::class),
            new PageLocales('fr', ['fr']),
        );

        $method = new ReflectionMethod(PageBlockEditController::class, 'ensureBlockTranslations');
        $method->invoke($controller, $cards);

        self::assertNotNull($cards->getTranslation('fr'));
        self::assertNull($cards->getTranslation('es'));
        $cardItem = $cards->getItems()->first();
        self::assertInstanceOf(PageCardItem::class, $cardItem);
        self::assertNotNull($cardItem->getTranslation('fr'));
        self::assertNull($cardItem->getTranslation('en'));
    }

    /**
     * @param FormInterface<mixed>|null $form
     * @param list<array{type: string, data: mixed, options: array<string, mixed>}> $formCalls
     */
    private function createControllerContainer(
        ?FormInterface $form = null,
        ?Request $request = null,
        array &$formCalls = [],
    ): ContainerBuilder {
        $container = new ContainerBuilder();

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')
            ->willReturnCallback(static function (string $route, array $parameters = []): string {
                if ($route === 'home') {
                    return '/generated/home';
                }

                return '/generated/' . $route . '/' . ($parameters['type'] ?? '') . '/' . ($parameters['id'] ?? '');
            });
        $container->set('router', $router);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')
            ->willReturnCallback(static fn (string $view, array $parameters = []): string => 'render:' . $view . ':' . ($parameters['locale'] ?? $parameters['page_title'] ?? ''));
        $container->set('twig', $twig);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')
            ->willReturnCallback(static function (string $type, mixed $data = null, array $options = []) use ($form, &$formCalls): FormInterface {
                $formCalls[] = [
                    'type'    => $type,
                    'data'    => $data,
                    'options' => $options,
                ];

                return $form ?? throw new LogicException('Expected a form instance for this test.');
            });
        $container->set('form.factory', $formFactory);

        $requestStack = new RequestStack();
        if ($request instanceof Request) {
            $requestStack->push($request);
        }
        $container->set('request_stack', $requestStack);
        $container->set('parameter_bag', new ParameterBag(['nowo_page_layout_kit.pages' => ['home', 'contact']]));

        return $container;
    }

    /** @param array<string, array<int, object>> $resultsByType */
    private function createRegistry(array $resultsByType = []): PageBlockRegistry
    {
        return new PageBlockRegistry(
            $this->createRepository(PageHeroBlockRepository::class, PageHeroBlock::class, $resultsByType[PageBlockType::Hero->value] ?? []),
            $this->createRepository(PageTextBlockRepository::class, PageTextBlock::class, $resultsByType[PageBlockType::Text->value] ?? []),
            $this->createRepository(PageCardsBlockRepository::class, PageCardsBlock::class, $resultsByType[PageBlockType::Cards->value] ?? []),
            $this->createRepository(PageListBlockRepository::class, PageListBlock::class, $resultsByType[PageBlockType::List->value] ?? []),
            $this->createRepository(PageCtaBlockRepository::class, PageCtaBlock::class, $resultsByType[PageBlockType::Cta->value] ?? []),
            $this->createRepository(PageCompareBlockRepository::class, PageCompareBlock::class, $resultsByType[PageBlockType::Compare->value] ?? []),
        );
    }

    /**
     * @template TRepository of object
     *
     * @param class-string<TRepository> $repositoryClass
     * @param class-string<object> $entityClass
     * @param array<int, object> $resultsById
     *
     * @return TRepository
     */
    private function createRepository(string $repositoryClass, string $entityClass, array $resultsById): object
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')
            ->willReturnCallback(static function (string $className) use ($entityClass): ClassMetadata {
                self::assertSame($entityClass, $className);

                return new ClassMetadata($entityClass);
            });
        $entityManager->method('createQueryBuilder')
            ->willReturnCallback(function () use (&$resultsById): QueryBuilder {
                /** @var array<string, mixed> $params */
                $params = [];
                $query  = $this->createMock(Query::class);
                $query->method('setHint')->willReturnSelf();
                $query->method('getOneOrNullResult')
                    ->willReturnCallback(static function () use (&$params, $resultsById): ?object {
                        $id = $params['id'] ?? null;

                        $id = $id === null ? null : (int) $id;

                        return $id === null ? null : ($resultsById[$id] ?? null);
                    });

                $queryBuilder = $this->createMock(QueryBuilder::class);
                $queryBuilder->method('select')->willReturnSelf();
                $queryBuilder->method('from')->willReturnSelf();
                $queryBuilder->method('leftJoin')->willReturnSelf();
                $queryBuilder->method('addSelect')->willReturnSelf();
                $queryBuilder->method('andWhere')->willReturnSelf();
                $queryBuilder->method('orderBy')->willReturnSelf();
                $queryBuilder->method('setParameter')
                    ->willReturnCallback(static function (string $key, mixed $value) use (&$params, $queryBuilder): QueryBuilder {
                        $params[$key] = $value;

                        return $queryBuilder;
                    });
                $queryBuilder->method('getQuery')->willReturn($query);

                return $queryBuilder;
            });

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')
            ->willReturnCallback(static function (string $className) use ($entityClass, $entityManager): EntityManagerInterface {
                self::assertSame($entityClass, $className);

                return $entityManager;
            });

        return new $repositoryClass($registry);
    }

    /**
     * @template T of object
     *
     * @param T $block
     *
     * @return T
     */
    private function withId(object $block, int $id): object
    {
        $property = new ReflectionProperty($block, 'id');
        $property->setValue($block, $id);

        return $block;
    }
}
