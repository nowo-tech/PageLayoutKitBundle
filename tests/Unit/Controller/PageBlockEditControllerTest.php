<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Controller\Admin\PageBlockEditController;
use Nowo\PageLayoutKitBundle\Entity\PageCardItem;
use Nowo\PageLayoutKitBundle\Entity\PageCardsBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCompareBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlock;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
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
use Nowo\PageLayoutKitBundle\Repository\PageCardsBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageCompareBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageCtaBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageHeroBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageListBlockRepository;
use Nowo\PageLayoutKitBundle\Repository\PageTextBlockRepository;
use Nowo\PageLayoutKitBundle\Service\PageBlockRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class PageBlockEditControllerTest extends TestCase
{
    public function testEditModalThrowsNotFoundWhenRegistryMissesBlock(): void
    {
        $controller = new PageBlockEditController(
            $this->createRegistry(),
            $this->createMock(EntityManagerInterface::class),
        );
        $controller->setContainer($this->createControllerContainer());

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Bloque no encontrado.');

        $controller->editModal('hero', 15, Request::create('/admin/page-blocks/hero/15/edit-modal', 'GET'));
    }

    public function testEditModalRendersResolvedFormAndLocale(): void
    {
        $block = $this->withId(new PageHeroBlock(), 5);
        $form = $this->createMock(FormInterface::class);
        $formCalls = [];

        $controller = new PageBlockEditController(
            $this->createRegistry([PageBlockType::Hero->value => [5 => $block]]),
            $this->createMock(EntityManagerInterface::class),
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
        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(false);
        $formCalls = [];

        $request = Request::create('/admin/page-blocks/text/7', 'POST');
        $request->setLocale('es');

        $controller = new PageBlockEditController(
            $this->createRegistry([PageBlockType::Text->value => [7 => $block]]),
            $this->createMock(EntityManagerInterface::class),
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
        $form = $this->createMock(FormInterface::class);
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
        );
        $controller->setContainer($this->createControllerContainer(form: $form, request: $request, formCalls: $formCalls));

        $response = $controller->update('cta', 9, $request);

        self::assertSame('/admin/layout/home', $response->getTargetUrl());
        self::assertSame(['Bloque actualizado.'], $session->getFlashBag()->get('success'));
        self::assertSame(PageCtaBlockModalType::class, $formCalls[0]['type']);
    }

    public function testUpdateFallsBackToGeneratedHomeUrlWithoutReferer(): void
    {
        $block = $this->withId(new PageCompareBlock(), 12);
        $form = $this->createConfiguredMock(FormInterface::class, [
            'isSubmitted' => true,
            'isValid' => true,
        ]);
        $form->expects(self::once())->method('handleRequest');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $request = Request::create('/admin/page-blocks/compare/12', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $controller = new PageBlockEditController(
            $this->createRegistry([PageBlockType::Compare->value => [12 => $block]]),
            $entityManager,
        );
        $controller->setContainer($this->createControllerContainer(form: $form, request: $request));

        $response = $controller->update('compare', 12, $request);

        self::assertSame('/generated/home', $response->getTargetUrl());
    }

    public function testUpdateThrowsNotFoundWhenRegistryMissesBlock(): void
    {
        $form = $this->createConfiguredMock(FormInterface::class, [
            'isSubmitted' => true,
            'isValid' => true,
        ]);

        $request = Request::create('/admin/page-blocks/hero/404', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $controller = new PageBlockEditController(
            $this->createRegistry(),
            $this->createMock(EntityManagerInterface::class),
        );
        $controller->setContainer($this->createControllerContainer(form: $form, request: $request));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Bloque no encontrado.');

        $controller->update('hero', 404, $request);
    }

    public function testEditThrowsNotFoundWhenRegistryMissesBlock(): void
    {
        $controller = new PageBlockEditController(
            $this->createRegistry(),
            $this->createMock(EntityManagerInterface::class),
        );
        $controller->setContainer($this->createControllerContainer());

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Bloque no encontrado.');

        $controller->edit('hero', 404);
    }

    public function testEditRendersStubForExistingBlock(): void
    {
        $controller = new PageBlockEditController(
            $this->createRegistry([PageBlockType::List->value => [3 => $this->withId(new PageListBlock(), 3)]]),
            $this->createMock(EntityManagerInterface::class),
        );
        $controller->setContainer($this->createControllerContainer());

        $response = $controller->edit('list', 3);

        self::assertSame('render:@NowoPageLayoutKitBundle/admin/blocks/edit_stub.html.twig:Editar bloque', $response->getContent());
    }

    public function testCreateBlockFormSelectsTheExpectedFormTypeForEveryBlockKind(): void
    {
        $form = $this->createMock(FormInterface::class);
        $formCalls = [];
        $controller = new PageBlockEditController(
            $this->createRegistry(),
            $this->createMock(EntityManagerInterface::class),
        );
        $controller->setContainer($this->createControllerContainer(form: $form, formCalls: $formCalls));

        $method = new ReflectionMethod(PageBlockEditController::class, 'createBlockForm');
        $method->setAccessible(true);

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
        );
        $controller->setContainer($this->createControllerContainer());

        $method = new ReflectionMethod(PageBlockEditController::class, 'ensureBlockTranslations');
        $method->setAccessible(true);
        $method->invoke($controller, $cards);
        $method->invoke($controller, $list);
        $method->invoke($controller, $hero);

        self::assertNotNull($cards->getTranslation('es'));
        self::assertNotNull($cards->getItems()->first()->getTranslation('en'));
        self::assertNotNull($list->getTranslation('en'));
        self::assertNotNull($list->getItems()->first()->getTranslation('es'));
        self::assertNotNull($hero->getTranslation('es'));
    }

    private function createControllerContainer(
        ?FormInterface $form = null,
        ?Request $request = null,
        array &$formCalls = [],
    ): ContainerBuilder {
        $container = new ContainerBuilder();

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')
            ->willReturnCallback(static function (string $route, array $parameters = []): string {
                if ('home' === $route) {
                    return '/generated/home';
                }

                return '/generated/' . $route . '/' . ($parameters['type'] ?? '') . '/' . ($parameters['id'] ?? '');
            });
        $container->set('router', $router);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')
            ->willReturnCallback(static function (string $view, array $parameters = []): string {
                return 'render:' . $view . ':' . ($parameters['locale'] ?? $parameters['page_title'] ?? '');
            });
        $container->set('twig', $twig);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')
            ->willReturnCallback(function (string $type, mixed $data = null, array $options = []) use ($form, &$formCalls): FormInterface {
                $formCalls[] = [
                    'type' => $type,
                    'data' => $data,
                    'options' => $options,
                ];

                return $form ?? throw new \LogicException('Expected a form instance for this test.');
            });
        $container->set('form.factory', $formFactory);

        $requestStack = new RequestStack();
        if ($request !== null) {
            $requestStack->push($request);
        }
        $container->set('request_stack', $requestStack);

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
     * @param class-string<object>      $entityClass
     * @param array<int, object>        $resultsById
     *
     * @return TRepository
     */
    private function createRepository(string $repositoryClass, string $entityClass, array $resultsById): object
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')
            ->with($entityClass)
            ->willReturn(new ClassMetadata($entityClass));
        $entityManager->method('createQueryBuilder')
            ->willReturnCallback(function () use (&$resultsById): QueryBuilder {
                $params = [];
                $query = $this->createMock(Query::class);
                $query->method('getOneOrNullResult')
                    ->willReturnCallback(function () use (&$params, $resultsById): ?object {
                        $id = $params['id'] ?? null;

                        $id = null === $id ? null : (int) $id;

                        return null === $id ? null : ($resultsById[$id] ?? null);
                    });

                $queryBuilder = $this->createMock(QueryBuilder::class);
                $queryBuilder->method('select')->willReturnSelf();
                $queryBuilder->method('from')->willReturnSelf();
                $queryBuilder->method('leftJoin')->willReturnSelf();
                $queryBuilder->method('addSelect')->willReturnSelf();
                $queryBuilder->method('andWhere')->willReturnSelf();
                $queryBuilder->method('orderBy')->willReturnSelf();
                $queryBuilder->method('setParameter')
                    ->willReturnCallback(function (string $key, mixed $value) use (&$params, $queryBuilder): QueryBuilder {
                        $params[$key] = $value;

                        return $queryBuilder;
                    });
                $queryBuilder->method('getQuery')->willReturn($query);

                return $queryBuilder;
            });

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')
            ->with($entityClass)
            ->willReturn($entityManager);

        return new $repositoryClass($registry);
    }

    private function withId(object $block, int $id): object
    {
        $property = new ReflectionProperty($block, 'id');
        $property->setValue($block, $id);

        return $block;
    }
}
