<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Integration\Persistence;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\PageLayoutKitBundle\NowoPageLayoutKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function dirname;
use function md5;
use function serialize;
use function sys_get_temp_dir;

/**
 * Host kernel for the rule 6 tests (2.2): real DoctrineBundle on SQLite files, configurable managers and bundle
 * `doctrine` options, the bundle admin routes and two host routes (`/t/fail`, `/t/layout`).
 */
final class PersistenceTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $doctrine host `doctrine` config
     * @param array<string, mixed> $bundleDoctrine `nowo_page_layout_kit.doctrine`
     * @param bool $hostDefaultDriver the host gives its default manager's chain a catch-all default
     *                                driver (what DoctrineBundle 3.4 `auto_discover_entities` does)
     */
    public function __construct(
        private readonly array $doctrine,
        private readonly array $bundleDoctrine = [],
        private readonly bool $hostDefaultDriver = false,
    ) {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        yield new NowoUiKitBundle();
        yield new NowoFormKitBundle();
        yield new NowoPageLayoutKitBundle();
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir() . '/nowo-page-layout-kit-persistence';
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/' . md5(serialize([$this->doctrine, $this->bundleDoctrine, $this->hostDefaultDriver])) . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/log';
    }

    protected function build(ContainerBuilder $container): void
    {
        if (!$this->hostDefaultDriver) {
            return;
        }

        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                // An attribute driver answers "not transient" for any class carrying #[ORM\Entity], whatever its paths.
                $container->getDefinition('doctrine.orm.default_metadata_driver')
                    ->addMethodCall('setDefaultDriver', [new Definition(AttributeDriver::class, [[dirname(__DIR__, 2) . '/Fixtures/Host'], true])]);
            }
        });
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => 'persistence-test-secret',
            'test'                 => true,
            'http_method_override' => false,
            'router'               => ['utf8' => true],
            'csrf_protection'      => true,
            'form'                 => ['csrf_protection' => true],
            'session'              => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'default_locale'       => 'en',
        ]);
        $container->extension('security', [
            'firewalls' => ['main' => ['security' => false]],
        ]);
        $container->extension('doctrine', $this->doctrine);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->set(NoCsrfTypeExtension::class)
            ->tag('form.type_extension', ['priority' => -1024]);
        $container->services()->set(PersistenceTestController::class)
            ->autowire()
            ->public()
            ->tag('controller.service_arguments');
        $container->extension('nowo_page_layout_kit', [
            'pages'          => ['home'],
            'locales'        => ['en'],
            'default_locale' => 'en',
            'security'       => ['allow_unauthenticated' => true],
            'doctrine'       => $this->bundleDoctrine,
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@NowoPageLayoutKitBundle/Resources/config/routing.yaml');
        $routes->add('t_fail', '/t/fail')->controller([PersistenceTestController::class, 'fail']);
        $routes->add('t_layout', '/t/layout')->controller([PersistenceTestController::class, 'layout']);
    }
}
