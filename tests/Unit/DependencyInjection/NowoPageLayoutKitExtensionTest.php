<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\DependencyInjection;

use Doctrine\ORM\Events;
use LogicException;
use Nowo\PageLayoutKitBundle\DependencyInjection\NowoPageLayoutKitExtension;
use Nowo\PageLayoutKitBundle\DependencyInjection\TablePrefixListener;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Security\AllowAllPageLayoutKitAccessChecker;
use Nowo\PageLayoutKitBundle\Security\ConfigurablePageLayoutKitAccessChecker;
use Nowo\PageLayoutKitBundle\Security\PageLayoutKitAccessCheckerInterface;
use Nowo\PageLayoutKitBundle\Security\PageLayoutProtection;
use Nowo\PageLayoutKitBundle\Security\PageLayoutProtectionConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

final class NowoPageLayoutKitExtensionTest extends TestCase
{
    public function testPrependSkipsWhenFormKitExtensionIsMissing(): void
    {
        $container = $this->getMockBuilder(ContainerBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['hasExtension', 'prependExtensionConfig'])
            ->getMock();
        $container->expects(self::once())
            ->method('hasExtension')
            ->with('nowo_form_kit')
            ->willReturn(false);
        $container->expects(self::never())->method('prependExtensionConfig');

        (new NowoPageLayoutKitExtension())->prepend($container);
    }

    public function testPrependSkipsWhenHostAlreadyDefinesTheProfile(): void
    {
        $container = $this->getMockBuilder(ContainerBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['hasExtension', 'getExtensionConfig', 'prependExtensionConfig'])
            ->getMock();
        $container->expects(self::once())
            ->method('hasExtension')
            ->with('nowo_form_kit')
            ->willReturn(true);
        $container->expects(self::once())
            ->method('getExtensionConfig')
            ->with('nowo_form_kit')
            ->willReturn([
                ['profiles' => ['page_layout_kit' => ['alias' => 'host']]],
            ]);
        $container->expects(self::never())->method('prependExtensionConfig');

        (new NowoPageLayoutKitExtension())->prepend($container);
    }

    public function testPrependPublishesTheDefaultFormKitProfileWhenMissing(): void
    {
        $container = $this->getMockBuilder(ContainerBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['hasExtension', 'getExtensionConfig', 'prependExtensionConfig'])
            ->getMock();
        $container->expects(self::once())
            ->method('hasExtension')
            ->with('nowo_form_kit')
            ->willReturn(true);
        $container->expects(self::once())
            ->method('getExtensionConfig')
            ->with('nowo_form_kit')
            ->willReturn([
                ['profiles' => ['other' => ['alias' => 'other']]],
            ]);
        $container->expects(self::once())
            ->method('prependExtensionConfig')
            ->with('nowo_form_kit', [
                'profiles' => [
                    'page_layout_kit' => [
                        'alias'              => 'page_layout_kit',
                        'translation_domain' => 'NowoPageLayoutKitBundle',
                        'defaults'           => [
                            'attr'     => ['class' => 'nowo-ui-input form-control'],
                            'row_attr' => ['class' => 'mb-2'],
                        ],
                    ],
                ],
            ]);

        (new NowoPageLayoutKitExtension())->prepend($container);
    }

    public function testLoadPublishesParametersAndRegistersAllowAllAccessChecker(): void
    {
        $container = new ContainerBuilder();

        (new NowoPageLayoutKitExtension())->load([
            [
                'default_locale' => 'en',
                'locales'        => ['en', 'es'],
                'pages'          => ['landing'],
                'security'       => [
                    'allow_unauthenticated' => true,
                    'access_roles'          => ['ROLE_EDITOR'],
                    'access_checker'        => null,
                ],
                'web_ui' => [
                    'layout_template' => '@App/layout.html.twig',
                    'css_framework'   => 'custom',
                ],
                'doctrine' => [
                    'table_prefix' => 'acme_',
                    'connection'   => 'reporting',
                ],
            ],
        ], $container);

        self::assertSame('en', $container->getParameter('nowo_page_layout_kit.default_locale'));
        self::assertSame(['en', 'es'], $container->getParameter('nowo_page_layout_kit.locales'));
        self::assertSame(['landing'], $container->getParameter('nowo_page_layout_kit.pages'));
        self::assertSame('acme_', $container->getParameter('nowo_page_layout_kit.doctrine.table_prefix'));
        self::assertSame('reporting', $container->getParameter('nowo_page_layout_kit.doctrine.connection'));
        self::assertSame(['ROLE_EDITOR'], $container->getParameter('nowo_page_layout_kit.security.access_roles'));
        self::assertNull($container->getParameter('nowo_page_layout_kit.security.access_checker'));
        self::assertTrue($container->getParameter('nowo_page_layout_kit.security.allow_unauthenticated'));
        self::assertSame('@App/layout.html.twig', $container->getParameter('nowo_page_layout_kit.web_ui.layout_template'));
        self::assertSame('custom', $container->getParameter('nowo_page_layout_kit.web_ui.css_framework'));

        $pageLocales = $container->getDefinition(PageLocales::class);
        self::assertSame('en', $pageLocales->getArgument('$defaultLocale'));
        self::assertSame(['en', 'es'], $pageLocales->getArgument('$locales'));

        $alias = (string) $container->getAlias(PageLayoutKitAccessCheckerInterface::class);
        self::assertSame('nowo_page_layout_kit.access_checker.allow_all', $alias);
        self::assertSame(
            AllowAllPageLayoutKitAccessChecker::class,
            $container->getDefinition($alias)->getClass(),
        );

        $listener = $container->getDefinition(TablePrefixListener::class);
        self::assertSame(TablePrefixListener::class, $listener->getClass());
        self::assertSame('acme_', $listener->getArgument(0));
        self::assertSame([
            ['event' => Events::loadClassMetadata],
        ], $listener->getTag('doctrine.event_listener'));

        self::assertTrue($container->hasDefinition(PageLayoutProtectionConfig::class));
        self::assertTrue($container->hasDefinition(PageLayoutProtection::class));
    }

    public function testLoadUsesCustomAccessCheckerAliasWhenConfigured(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', [
            'SecurityBundle' => SecurityBundle::class,
        ]);

        (new NowoPageLayoutKitExtension())->load([
            [
                'security' => [
                    'allow_unauthenticated' => false,
                    'access_roles'          => ['ROLE_ADMIN'],
                    'access_checker'        => 'app.page_layout_checker',
                ],
            ],
        ], $container);

        self::assertSame('app.page_layout_checker', (string) $container->getAlias(PageLayoutKitAccessCheckerInterface::class));
        self::assertFalse($container->hasDefinition('nowo_page_layout_kit.access_checker.default'));
        self::assertFalse($container->hasDefinition(TablePrefixListener::class));
    }

    public function testLoadRegistersDefaultConfigurableAccessCheckerWhenNeeded(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', [
            'SecurityBundle' => SecurityBundle::class,
        ]);

        (new NowoPageLayoutKitExtension())->load([
            [
                'security' => [
                    'allow_unauthenticated' => false,
                    'access_roles'          => ['ROLE_MANAGER', 'ROLE_EDITOR'],
                    'access_checker'        => null,
                ],
            ],
        ], $container);

        $id         = 'nowo_page_layout_kit.access_checker.default';
        $definition = $container->getDefinition($id);
        self::assertSame(ConfigurablePageLayoutKitAccessChecker::class, $definition->getClass());
        self::assertSame(['ROLE_MANAGER', 'ROLE_EDITOR'], $definition->getArgument('$accessRoles'));
        self::assertEquals(new Reference('security.authorization_checker'), $definition->getArgument('$authorizationChecker'));
        self::assertSame($id, (string) $container->getAlias(PageLayoutKitAccessCheckerInterface::class));
    }

    public function testLoadTreatsRegisteredSecurityExtensionAsAvailable(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'security';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });

        (new NowoPageLayoutKitExtension())->load([
            [
                'security' => [
                    'allow_unauthenticated' => false,
                    'access_roles'          => ['ROLE_ADMIN'],
                    'access_checker'        => null,
                ],
            ],
        ], $container);

        self::assertSame(
            'nowo_page_layout_kit.access_checker.default',
            (string) $container->getAlias(PageLayoutKitAccessCheckerInterface::class),
        );
    }

    public function testLoadRequiresSecurityBundleWhenAnonymousAccessIsDisabled(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('NowoPageLayoutKitBundle admin UI requires symfony/security-bundle when security.allow_unauthenticated is false.');

        (new NowoPageLayoutKitExtension())->load([
            [
                'security' => [
                    'allow_unauthenticated' => false,
                    'access_roles'          => ['ROLE_ADMIN'],
                    'access_checker'        => null,
                ],
            ],
        ], new ContainerBuilder());
    }

    public function testAliasMatchesBundleConfigurationAlias(): void
    {
        self::assertSame('nowo_page_layout_kit', (new NowoPageLayoutKitExtension())->getAlias());
    }
}
