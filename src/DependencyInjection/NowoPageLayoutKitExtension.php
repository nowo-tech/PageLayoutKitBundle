<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\DependencyInjection;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use LogicException;
use Nowo\PageLayoutKitBundle\DependencyInjection\Configuration as BundleConfiguration;
use Nowo\PageLayoutKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageLayoutKitBundle\EventListener\PageLayoutConflictListener;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Persistence\PageLayoutEntityManagerFactory;
use Nowo\PageLayoutKitBundle\Security\AllowAllPageLayoutKitAccessChecker;
use Nowo\PageLayoutKitBundle\Security\ConfigurablePageLayoutKitAccessChecker;
use Nowo\PageLayoutKitBundle\Security\PageLayoutKitAccessCheckerInterface;
use Nowo\PageLayoutKitBundle\Security\PageLayoutProtection;
use Nowo\PageLayoutKitBundle\Security\PageLayoutProtectionConfig;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\Dbal\CommitTrackingMiddleware;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelEvents;

use function array_key_exists;
use function is_array;
use function is_string;
use function sprintf;
use function str_starts_with;

/**
 * Loads PageLayoutKit services and publishes configuration parameters.
 */
final class NowoPageLayoutKitExtension extends Extension implements PrependExtensionInterface
{
    /** Container parameter holding `doctrine.entity_manager` (null: the manager that maps the bundle entities). */
    public const string ENTITY_MANAGER_PARAMETER = 'nowo_page_layout_kit.doctrine.entity_manager';

    /** Entity manager used by every bundle read and write (alias of the configured one, or resolved by entity class). */
    public const string ENTITY_MANAGER_SERVICE = 'nowo_page_layout_kit.entity_manager';

    /** 2.3: real commits of the bundle's connection, counted by {@see self::COMMIT_TRACKING_MIDDLEWARE}, read by the conflict answer. */
    public const string COMMIT_LEDGER_SERVICE = 'nowo_page_layout_kit.commit_ledger';

    /** 2.3: transaction-doctrine's commit-tracking DBAL middleware on the bundle's connection (AfterCommitMiddlewarePass). */
    public const string COMMIT_TRACKING_MIDDLEWARE = 'nowo_page_layout_kit.commit_tracking_middleware';

    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_form_kit')) {
            return;
        }

        $hostHasProfile = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            /** @var array<string, mixed> $cfg */
            $profiles = $cfg['profiles'] ?? null;
            if (is_array($profiles) && array_key_exists('page_layout_kit', $profiles)) {
                $hostHasProfile = true;
            }
        }

        if ($hostHasProfile) {
            return;
        }

        $container->prependExtensionConfig('nowo_form_kit', [
            'profiles' => [
                'page_layout_kit' => [
                    'alias'              => 'page_layout_kit',
                    'translation_domain' => 'NowoPageLayoutKitBundle',
                    'defaults'           => [
                        // Theme-neutral (REQ-UI-003): UiKit styles nowo-ui-input; framework classes come from the host theme.
                        'attr'     => ['class' => 'nowo-ui-input'],
                        'row_attr' => [],
                    ],
                ],
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new BundleConfiguration();
        $config        = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $container->setParameter('nowo_page_layout_kit.default_locale', $config['default_locale']);
        $container->setParameter('nowo_page_layout_kit.locales', $config['locales']);
        $container->setParameter('nowo_page_layout_kit.pages', $config['pages']);
        $container->setParameter('nowo_page_layout_kit.doctrine.table_prefix', $config['doctrine']['table_prefix']);
        $container->setParameter('nowo_page_layout_kit.doctrine.connection', $config['doctrine']['connection']);
        $container->setParameter(self::ENTITY_MANAGER_PARAMETER, $config['doctrine']['entity_manager']);
        $this->registerEntityManager($container, $config['doctrine']['entity_manager']);
        $container->setParameter('nowo_page_layout_kit.security.access_roles', $config['security']['access_roles']);
        $container->setParameter('nowo_page_layout_kit.security.access_checker', $config['security']['access_checker']);
        $container->setParameter('nowo_page_layout_kit.security.allow_unauthenticated', $config['security']['allow_unauthenticated']);
        $container->setParameter('nowo_page_layout_kit.web_ui.layout_template', $config['web_ui']['layout_template']);
        $container->setParameter('nowo_page_layout_kit.web_ui.css_framework', $config['web_ui']['css_framework']);
        $container->setParameter('nowo_page_layout_kit.web_ui.page_routes', $config['web_ui']['page_routes']);
        $container->setParameter('nowo_page_layout_kit.web_ui.templates', $config['web_ui']['templates']);

        $container->getDefinition(PageLocales::class)
            ->setArgument('$defaultLocale', $config['default_locale'])
            ->setArgument('$locales', $config['locales']);

        if (
            !$config['security']['allow_unauthenticated']
            && !$this->isSecurityBundleAvailable($container)
        ) {
            throw new LogicException('NowoPageLayoutKitBundle admin UI requires symfony/security-bundle when security.allow_unauthenticated is false.');
        }

        $this->registerConflictAnswer($container, $config['conflict_response']);
        $this->registerAccessChecker($container, $config['security']);
        $this->registerPageLayoutProtection($container, $config);

        $tablePrefix = (string) $config['doctrine']['table_prefix'];
        if ($tablePrefix !== '') {
            $definition = new Definition(TablePrefixListener::class, [$tablePrefix]);
            $definition->addTag('doctrine.event_listener', ['event' => Events::loadClassMetadata]);
            $container->setDefinition(TablePrefixListener::class, $definition);
        }
    }

    /**
     * Wires the entity manager of the bundle entities (rule 6, 2.2): never the autowired default by accident.
     */
    private function registerEntityManager(ContainerBuilder $container, ?string $entityManagerName): void
    {
        if ($entityManagerName === null) {
            $container->register(self::ENTITY_MANAGER_SERVICE, EntityManagerInterface::class)
                ->setFactory([PageLayoutEntityManagerFactory::class, 'create'])
                ->setArguments([new Reference('doctrine')]);
        } else {
            // The recovery subscriber gets the name in PageLayoutEntityManagerPass, only when it is not the host default.
            $container->setAlias(self::ENTITY_MANAGER_SERVICE, sprintf('doctrine.orm.%s_entity_manager', $entityManagerName));
        }
    }

    /**
     * 2.3 (REQ-RUNTIME-003 rule 6): 409 (or 503) + CONFLICT_001 / CONFLICT_002 instead of a 500 for lock conflicts on
     * the bundle's routes. Unset options follow transaction-doctrine's policy service when its bundle is registered.
     *
     * @param array{status: int|null, retry_after: int|null} $conflictResponse
     */
    private function registerConflictAnswer(ContainerBuilder $container, array $conflictResponse): void
    {
        $container->setDefinition(self::COMMIT_LEDGER_SERVICE, new Definition(CommitLedger::class));
        $container->setDefinition(self::COMMIT_TRACKING_MIDDLEWARE, (new Definition(CommitTrackingMiddleware::class, [new Reference(self::COMMIT_LEDGER_SERVICE)]))
            ->addTag('doctrine.middleware'));

        $policy = $conflictResponse['status'] === null && $conflictResponse['retry_after'] === null
            ? new Reference(ConflictResponsePolicy::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)
            : new Definition(ConflictResponsePolicy::class, [
                $conflictResponse['status'] ?? ConflictResponsePolicy::STATUS_CONFLICT,
                $conflictResponse['retry_after'] ?? ConflictResponsePolicy::DEFAULT_RETRY_AFTER,
            ]);

        $container->setDefinition(PageLayoutConflictListener::class, (new Definition(PageLayoutConflictListener::class, [
            $policy,
            new Reference(self::COMMIT_LEDGER_SERVICE),
            new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]))
            ->addTag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onRequest', 'priority' => PageLayoutConflictListener::REQUEST_PRIORITY])
            ->addTag('kernel.event_listener', ['event' => KernelEvents::EXCEPTION, 'method' => 'onException', 'priority' => PageLayoutConflictListener::PRIORITY]));
    }

    public function getAlias(): string
    {
        return BundleConfiguration::ALIAS;
    }

    /**
     * @param array{access_checker: ?string, access_roles: list<string>, allow_unauthenticated: bool} $security
     */
    private function registerAccessChecker(ContainerBuilder $container, array $security): void
    {
        if ($security['allow_unauthenticated']) {
            $this->refuseAllowUnauthenticatedInProduction($container);
            $id = 'nowo_page_layout_kit.access_checker.allow_all';
            $container->setDefinition($id, new Definition(AllowAllPageLayoutKitAccessChecker::class));
            $container->setAlias(PageLayoutKitAccessCheckerInterface::class, $id);

            return;
        }

        $custom = $security['access_checker'] ?? null;
        if (is_string($custom) && $custom !== '') {
            $container->setAlias(PageLayoutKitAccessCheckerInterface::class, $custom);

            return;
        }

        if ($security['access_roles'] === []) {
            throw new LogicException('NowoPageLayoutKitBundle: security.access_roles is empty, so nobody could use the admin UI (an empty list denies). Configure at least one role, set security.access_checker, or set security.allow_unauthenticated for a trusted demo.');
        }

        $id         = 'nowo_page_layout_kit.access_checker.default';
        $definition = new Definition(ConfigurablePageLayoutKitAccessChecker::class);
        $definition->setArgument('$accessRoles', $security['access_roles']);
        $definition->setArgument('$authorizationChecker', new Reference('security.authorization_checker'));
        $container->setDefinition($id, $definition);
        $container->setAlias(PageLayoutKitAccessCheckerInterface::class, $id);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function registerPageLayoutProtection(ContainerBuilder $container, array $config): void
    {
        /** @var array<string, mixed> $html */
        $html            = $config['html']['sanitize'];
        $strategy        = HtmlSanitizeStrategy::from((string) $html['strategy']);
        $customSanitizer = $this->optionalServiceId($html['service'] ?? null);

        if ($strategy === HtmlSanitizeStrategy::Service && $customSanitizer === null) {
            throw new LogicException('NowoPageLayoutKitBundle: html.sanitize.strategy is "service" but html.sanitize.service is empty. Set the id of a service implementing PageLayoutHtmlSanitizerInterface, or use the "allowlist" strategy.');
        }

        $container->register(PageLayoutProtectionConfig::class)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                $strategy,
                $customSanitizer,
            ]);

        $container->register(PageLayoutProtection::class)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                new Reference(PageLayoutProtectionConfig::class),
                $customSanitizer !== null ? new Reference($customSanitizer) : null,
            ]);
    }

    private function optionalServiceId(mixed $serviceId): ?string
    {
        if (!is_string($serviceId) || $serviceId === '') {
            return null;
        }

        return $serviceId;
    }

    private function isSecurityBundleAvailable(ContainerBuilder $container): bool
    {
        if ($container->hasExtension('security')) {
            return true;
        }

        if (!$container->hasParameter('kernel.bundles')) {
            return false;
        }

        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        return isset($bundles['SecurityBundle']);
    }

    /**
     * `allow_unauthenticated` opens the layout admin to anonymous users: a dev / demo switch, refused in the prod
     * environment and in any non-debug kernel outside the test environments (test*), as in ContactFormBundle.
     */
    private function refuseAllowUnauthenticatedInProduction(ContainerBuilder $container): void
    {
        $environment = $container->hasParameter('kernel.environment') ? $container->getParameter('kernel.environment') : '';
        $environment = is_string($environment) ? $environment : '';
        $debug       = $container->hasParameter('kernel.debug') ? (bool) $container->getParameter('kernel.debug') : true;

        if ($environment === 'prod' || (!$debug && !str_starts_with($environment, 'test'))) {
            throw new LogicException('nowo_page_layout_kit.security.allow_unauthenticated is a dev/demo switch: it opens the layout admin to anonymous users and is refused in the prod environment and in non-debug kernels (test* environments excepted).');
        }
    }
}
