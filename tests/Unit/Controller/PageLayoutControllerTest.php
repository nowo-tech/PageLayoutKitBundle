<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Controller\Admin\PageLayoutController;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderData;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderType;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use Nowo\PageLayoutKitBundle\Service\PublicPageUrlResolver;
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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class PageLayoutControllerTest extends TestCase
{
    public function testLayoutRouteOnlyAcceptsGetAndPost(): void
    {
        $route = (new ReflectionMethod(PageLayoutController::class, 'layout'))->getAttributes(Route::class)[0]->newInstance();

        self::assertSame('admin_page_layout', $route->name);
        self::assertSame(['GET', 'POST'], $route->methods);
    }

    public function testLayoutThrowsForUnknownPageKey(): void
    {
        $controller = new PageLayoutController(
            $this->createRepository([]),
            $this->createMock(EntityManagerInterface::class),
            $this->createFormFactory(),
        );
        $controller->setContainer($this->createControllerContainer(['home']));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Unknown page key "contact".');

        $controller->layout('contact', new Request());
    }

    public function testLayoutRendersEntriesOnGetRequests(): void
    {
        $entries    = [$this->createEntry(10, 1), $this->createEntry(11, 2)];
        $controller = new PageLayoutController(
            $this->createRepository(['home' => $entries]),
            $this->createMock(EntityManagerInterface::class),
            $this->createFormFactory(),
        );
        $controller->setContainer($this->createControllerContainer(['home']));

        $response = $controller->layout('home', Request::create('/admin/pages/home/layout', 'GET'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('render:@NowoPageLayoutKitBundle/admin/layout/index.html.twig:Layout: home', $response->getContent());
    }

    public function testLayoutPassesResolvedViewPageUrlOrNull(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route): string => match ($route) {
            'app_home' => '/',
            default    => throw new RouteNotFoundException($route),
        });
        $resolver = new PublicPageUrlResolver($urls, ['home' => 'app_home']);

        foreach (['home' => '/', 'contact' => null] as $pageKey => $expected) {
            $controller = new PageLayoutController(
                $this->createRepository([$pageKey => []]),
                $this->createMock(EntityManagerInterface::class),
                $this->createFormFactory(),
                $resolver,
            );
            $container = $this->createControllerContainer([$pageKey]);
            $captured  = [];
            $twig      = $this->createMock(Environment::class);
            $twig->method('render')->willReturnCallback(static function (string $view, array $parameters) use (&$captured): string {
                $captured = $parameters;

                return '';
            });
            $container->set('twig', $twig);
            $controller->setContainer($container);

            $controller->layout($pageKey, Request::create('/admin/pages/' . $pageKey . '/layout'));

            self::assertArrayHasKey('view_page_url', $captured);
            self::assertSame($expected, $captured['view_page_url'], $pageKey);
        }
    }

    public function testReorderFlashIsTranslated(): void
    {
        $entry = $this->createEntry(10, 1);
        $form  = $this->createConfiguredMock(FormInterface::class, [
            'isSubmitted' => true,
            'isValid'     => true,
            'getData'     => PageLayoutReorderData::fromEntries([$entry]),
        ]);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())->method('trans')->with('admin.flash.reordered', [], 'NowoPageLayoutKitBundle')->willReturn('Orden actualizado.');

        $request = Request::create('/admin/pages/home/layout', 'POST');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $controller = new PageLayoutController(
            $this->createRepository(['home' => [$entry]]),
            $this->createMock(EntityManagerInterface::class),
            $this->createFormFactory($form),
            null,
            $translator,
        );
        $controller->setContainer($this->createControllerContainer(['home'], $request));
        $controller->layout('home', $request);

        self::assertSame(['Orden actualizado.'], $session->getFlashBag()->get('success'));
    }

    public function testLayoutReordersEntriesOnPostRequests(): void
    {
        $first  = $this->createEntry(10, 1);
        $second = $this->createEntry(11, 2);

        $reorderData                    = PageLayoutReorderData::fromEntries([$first, $second]);
        $reorderData->rows[0]->position = 7;

        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($reorderData);

        $formCalls   = [];
        $formFactory = $this->createFormFactory($form, $formCalls);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $request = Request::create('/admin/pages/home/layout', 'POST');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $controller = new PageLayoutController(
            $this->createRepository(['home' => [$first, $second]]),
            $entityManager,
            $formFactory,
        );
        $controller->setContainer($this->createControllerContainer(['home'], $request));

        $response = $controller->layout('home', $request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/generated/admin_page_layout/home', $response->getTargetUrl());
        self::assertSame(PageLayoutReorderType::class, $formCalls[0]['type']);
        self::assertSame('/generated/admin_page_layout/home', $formCalls[0]['options']['action']);
        self::assertSame(7, $first->getPosition());
        self::assertSame(2, $second->getPosition());
        self::assertSame(['Order updated.'], $session->getFlashBag()->get('success'));
    }

    public function testLayoutRejectsInvalidSubmissions(): void
    {
        $form = $this->createConfiguredMock(FormInterface::class, [
            'isSubmitted' => false,
            'isValid'     => false,
        ]);
        $form->expects(self::once())->method('handleRequest');

        $formFactory = $this->createFormFactory($form);

        $request = Request::create('/admin/pages/home/layout', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $controller = new PageLayoutController(
            $this->createRepository(['home' => [$this->createEntry(10, 1)]]),
            $this->createMock(EntityManagerInterface::class),
            $formFactory,
        );
        $controller->setContainer($this->createControllerContainer(['home'], $request));

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Invalid form submission.');

        $controller->layout('home', $request);
    }

    /** @param list<string> $pages */
    private function createControllerContainer(array $pages, ?Request $request = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->set('parameter_bag', new ParameterBag([
            'nowo_page_layout_kit.pages' => $pages,
        ]));

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')
            ->willReturnCallback(static fn (string $route, array $parameters = []): string => '/generated/' . $route . '/' . ($parameters['pageKey'] ?? ''));
        $container->set('router', $router);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')
            ->willReturnCallback(static function (string $view, array $parameters = []): string {
                self::assertArrayHasKey('reorder_form', $parameters);

                return 'render:' . $view . ':' . ($parameters['page_title'] ?? '');
            });
        $container->set('twig', $twig);

        $requestStack = new RequestStack();
        if ($request instanceof Request) {
            $requestStack->push($request);
        }
        $container->set('request_stack', $requestStack);

        return $container;
    }

    /**
     * @param FormInterface<mixed>|null $form
     * @param list<array{type: string, data: mixed, options: array<string, mixed>}> $calls
     */
    private function createFormFactory(?FormInterface $form = null, array &$calls = []): FormFactoryInterface
    {
        $factory = $this->createMock(FormFactoryInterface::class);
        $factory->method('create')
            ->willReturnCallback(function (string $type, mixed $data = null, array $options = []) use ($form, &$calls): FormInterface {
                $calls[] = [
                    'type'    => $type,
                    'data'    => $data,
                    'options' => $options,
                ];

                return $form ?? $this->createMock(FormInterface::class);
            });

        return $factory;
    }

    /** @param array<string, list<PageLayoutEntry>> $resultsByPageKey */
    private function createRepository(array $resultsByPageKey): PageLayoutEntryRepository
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')
            ->willReturnCallback(static function (string $className): ClassMetadata {
                self::assertSame(PageLayoutEntry::class, $className);

                return new ClassMetadata(PageLayoutEntry::class);
            });
        $entityManager->method('createQueryBuilder')
            ->willReturnCallback(function () use ($resultsByPageKey): QueryBuilder {
                /** @var array<string, mixed> $params */
                $params = [];
                $query  = $this->createMock(Query::class);
                $query->method('setHint')->willReturnSelf();
                $query->method('getResult')
                    ->willReturnCallback(static function () use (&$params, $resultsByPageKey): array {
                        if (($params['enabled'] ?? null) !== true) {
                            return [];
                        }

                        return $resultsByPageKey[$params['pageKey'] ?? ''] ?? [];
                    });

                $queryBuilder = $this->createMock(QueryBuilder::class);
                $queryBuilder->method('select')->willReturnSelf();
                $queryBuilder->method('from')->willReturnSelf();
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
            ->willReturnCallback(static function (string $className) use ($entityManager): EntityManagerInterface {
                self::assertSame(PageLayoutEntry::class, $className);

                return $entityManager;
            });

        return new PageLayoutEntryRepository($registry);
    }

    private function createEntry(int $id, int $position): PageLayoutEntry
    {
        $entry = (new PageLayoutEntry())
            ->setPageKey('home')
            ->setBlockType(PageBlockType::Hero)
            ->setBlockId($id)
            ->setPosition($position);

        $property = new ReflectionProperty(PageLayoutEntry::class, 'id');
        $property->setValue($entry, $id);

        return $entry;
    }
}
