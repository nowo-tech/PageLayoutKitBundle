<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use Nowo\PageLayoutKitBundle\Controller\Admin\PageLayoutController;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class PageLayoutControllerTest extends TestCase
{
    public function testLayoutThrowsForUnknownPageKey(): void
    {
        $controller = new PageLayoutController(
            $this->createRepository([]),
            $this->createMock(EntityManagerInterface::class),
            $this->createCsrfOnlyFormFactory(),
        );
        $controller->setContainer($this->createControllerContainer(['home']));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Unknown page key "contact".');

        $controller->layout('contact', new Request());
    }

    public function testLayoutRendersEntriesOnGetRequests(): void
    {
        $entries = [$this->createEntry(10, 1), $this->createEntry(11, 2)];
        $controller = new PageLayoutController(
            $this->createRepository(['home' => $entries]),
            $this->createMock(EntityManagerInterface::class),
            $this->createCsrfOnlyFormFactory(),
        );
        $controller->setContainer($this->createControllerContainer(['home']));

        $response = $controller->layout('home', Request::create('/admin/pages/home/layout', 'GET'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('render:@NowoPageLayoutKitBundle/admin/layout/index.html.twig:Layout: home', $response->getContent());
    }

    public function testLayoutReordersEntriesOnPostRequests(): void
    {
        $first = $this->createEntry(10, 1);
        $second = $this->createEntry(11, 2);

        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $csrfCalls = [];
        $csrfFactory = $this->createCsrfOnlyFormFactory($form, $csrfCalls);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $request = Request::create('/admin/pages/home/layout', 'POST', [
            'order' => [
                '10' => '7',
                '11' => 'invalid',
            ],
        ]);
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $controller = new PageLayoutController(
            $this->createRepository(['home' => [$first, $second]]),
            $entityManager,
            $csrfFactory,
        );
        $controller->setContainer($this->createControllerContainer(['home'], $request));

        $response = $controller->layout('home', $request);

        self::assertSame('/generated/admin_page_layout/home', $response->getTargetUrl());
        self::assertSame('csrf_only', $csrfCalls[0]['name']);
        self::assertSame('/generated/admin_page_layout/home', $csrfCalls[0]['options']['action']);
        self::assertSame('page_layout_reorder', $csrfCalls[0]['options']['csrf_token_id']);
        self::assertSame(7, $first->getPosition());
        self::assertSame(2, $second->getPosition());
        self::assertSame(['Order updated.'], $session->getFlashBag()->get('success'));
    }

    public function testLayoutRejectsInvalidCsrfSubmissions(): void
    {
        $form = $this->createConfiguredMock(FormInterface::class, [
            'isSubmitted' => false,
            'isValid' => false,
        ]);
        $form->expects(self::once())->method('handleRequest');

        $csrfFactory = $this->createCsrfOnlyFormFactory($form);

        $request = Request::create('/admin/pages/home/layout', 'POST', [
            'order' => ['10' => '5'],
        ]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $controller = new PageLayoutController(
            $this->createRepository(['home' => [$this->createEntry(10, 1)]]),
            $this->createMock(EntityManagerInterface::class),
            $csrfFactory,
        );
        $controller->setContainer($this->createControllerContainer(['home'], $request));

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Invalid CSRF token.');

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
            ->willReturnCallback(static function (string $route, array $parameters = []): string {
                return '/generated/' . $route . '/' . ($parameters['pageKey'] ?? '');
            });
        $container->set('router', $router);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')
            ->willReturnCallback(static function (string $view, array $parameters = []): string {
                return 'render:' . $view . ':' . ($parameters['page_title'] ?? '');
            });
        $container->set('twig', $twig);

        $requestStack = new RequestStack();
        if ($request !== null) {
            $requestStack->push($request);
        }
        $container->set('request_stack', $requestStack);

        return $container;
    }


    private function createCsrfOnlyFormFactory(?FormInterface $form = null, array &$calls = []): CsrfOnlyFormFactory
    {
        $factory = $this->createMock(FormFactoryInterface::class);
        $factory->method('createNamed')
            ->willReturnCallback(function (string $name, string $type, mixed $data = null, array $options = []) use ($form, &$calls): FormInterface {
                $calls[] = [
                    'name' => $name,
                    'type' => $type,
                    'data' => $data,
                    'options' => $options,
                ];

                return $form ?? $this->createMock(FormInterface::class);
            });

        return new CsrfOnlyFormFactory($factory);
    }

    /** @param array<string, list<PageLayoutEntry>> $resultsByPageKey */
    private function createRepository(array $resultsByPageKey): PageLayoutEntryRepository
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')
            ->with(PageLayoutEntry::class)
            ->willReturn(new ClassMetadata(PageLayoutEntry::class));
        $entityManager->method('createQueryBuilder')
            ->willReturnCallback(function () use ($resultsByPageKey): QueryBuilder {
                $params = [];
                $query = $this->createMock(Query::class);
                $query->method('getResult')
                    ->willReturnCallback(function () use (&$params, $resultsByPageKey): array {
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
                    ->willReturnCallback(function (string $key, mixed $value) use (&$params, $queryBuilder): QueryBuilder {
                        $params[$key] = $value;

                        return $queryBuilder;
                    });
                $queryBuilder->method('getQuery')->willReturn($query);

                return $queryBuilder;
            });

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')
            ->with(PageLayoutEntry::class)
            ->willReturn($entityManager);

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
