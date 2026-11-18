<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PageLayoutKitBundle\DependencyInjection\Compiler\PageLayoutEntityManagerPass;
use Nowo\PageLayoutKitBundle\DependencyInjection\NowoPageLayoutKitExtension;
use Nowo\PageLayoutKitBundle\EventSubscriber\PageLayoutKitEntityManagerRecoverySubscriber;
use Nowo\PageLayoutKitBundle\Persistence\ExcludedEntityNamespaceDriver;
use Nowo\PageLayoutKitBundle\Persistence\PageLayoutTransaction;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PageLayoutEntityManagerPass::class)]
final class PageLayoutEntityManagerPassTest extends TestCase
{
    #[Test]
    public function doesNothingWithoutADedicatedManager(): void
    {
        $container = new ContainerBuilder();
        new PageLayoutEntityManagerPass()->process($container);
        $container->setParameter(NowoPageLayoutKitExtension::ENTITY_MANAGER_PARAMETER, null);
        new PageLayoutEntityManagerPass()->process($container);
        self::assertFalse($container->hasParameter('doctrine.entity_managers'));
    }

    #[Test]
    public function removesTheBundleMappingFromEveryOtherManager(): void
    {
        $container = $this->container(['default' => 'x', 'page_builder' => 'y', 'reporting' => 'z']);
        $container->setDefinition('doctrine.orm.default_metadata_driver', $this->chain(true, true));
        $container->setDefinition('doctrine.orm.page_builder_metadata_driver', $this->chain(true, false));

        new PageLayoutEntityManagerPass()->process($container);

        self::assertSame([['addDriver', PageLayoutEntityManagerPass::ENTITY_NAMESPACE], ['addDriver', 'App\\Entity']], $this->calls($container, 'default'), 'excluded first, host mapping kept');
        self::assertCount(1, $container->getDefinition('doctrine.orm.page_builder_metadata_driver')->getMethodCalls());
    }

    /**
     * DoctrineBundle 3.4 `auto_discover_entities` gives a manager without mappings a default driver that claims every
     * entity class: every other manager gets a driver that maps nothing for the bundle namespace, first in its chain.
     */
    #[Test]
    public function excludesTheEntitiesBeforeTheDefaultDriverOfEveryOtherManager(): void
    {
        $container = $this->container(['default' => 'x', 'page_builder' => 'y']);
        $container->setDefinition('doctrine.orm.default_metadata_driver', $this->chain(true, true)->addMethodCall('setDefaultDriver', [new Reference('auto_discover')]));
        $container->setDefinition('doctrine.orm.page_builder_metadata_driver', $this->chain(true, false));

        new PageLayoutEntityManagerPass()->process($container);

        $expected = [['addDriver', PageLayoutEntityManagerPass::ENTITY_NAMESPACE], ['addDriver', 'App\\Entity'], ['setDefaultDriver', null]];
        self::assertSame($expected, $this->calls($container, 'default'));
        $excluded = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls()[0][1][0];
        self::assertInstanceOf(Definition::class, $excluded);
        self::assertSame(ExcludedEntityNamespaceDriver::class, $excluded->getClass());
        self::assertSame([['addDriver', PageLayoutEntityManagerPass::ENTITY_NAMESPACE]], $this->calls($container, 'page_builder'), 'the configured manager keeps its own driver only');

        new PageLayoutEntityManagerPass()->process($container);
        self::assertSame($expected, $this->calls($container, 'default'), 'idempotent');
        self::assertSame([['addDriver', PageLayoutEntityManagerPass::ENTITY_NAMESPACE]], $this->calls($container, 'page_builder'), 'idempotent');
    }

    #[Test]
    public function unknownManagerFailsEvenInCheckOnlyMode(): void
    {
        $container = $this->container(['default' => 'x']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('known: "default"');
        new PageLayoutEntityManagerPass(checkOnly: true)->process($container);
    }

    #[Test]
    public function checkOnlyModeLeavesTheMappingAlone(): void
    {
        $container = $this->container(['default' => 'x', 'page_builder' => 'y']);
        $container->setDefinition('doctrine.orm.default_metadata_driver', $this->chain(true, true));
        new PageLayoutEntityManagerPass(checkOnly: true)->process($container);
        self::assertCount(2, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());
    }

    #[Test]
    public function managerWithoutTheBundleMappingFails(): void
    {
        $container = $this->container(['default' => 'x', 'page_builder' => 'y']);
        $container->setDefinition('doctrine.orm.page_builder_metadata_driver', $this->chain(false, true));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('does not map');
        new PageLayoutEntityManagerPass()->process($container);
    }

    /**
     * 2.3 (REQ-RUNTIME-003 rule 2): the bundle's own manager gets the runner that may reset it after a failed unit;
     * the host's default manager keeps PageLayoutTransaction's forHostEntityManager() default.
     */
    #[Test]
    public function onlyTheBundleOwnManagerGetsTheResettingRunner(): void
    {
        $container = $this->container(['default' => 'x', 'page_builder' => 'y']);
        $container->setDefinition('doctrine.orm.page_builder_metadata_driver', $this->chain(true, false));
        $container->register(PageLayoutTransaction::class)->setArgument('$runner', null);
        $container->register(PageLayoutKitEntityManagerRecoverySubscriber::class);
        $container->setParameter('doctrine.default_entity_manager', 'default');

        new PageLayoutEntityManagerPass()->process($container);

        $runner = $container->getDefinition(PageLayoutTransaction::class)->getArgument('$runner');
        self::assertInstanceOf(Definition::class, $runner);
        self::assertSame([DoctrineTransactionRunner::class, 'forEntityManager'], $runner->getFactory());
        self::assertSame('doctrine.orm.page_builder_entity_manager', (string) $runner->getArgument(0));
        self::assertSame('doctrine', (string) $runner->getArgument(1));
        self::assertSame('page_builder', $runner->getArgument(2));
        self::assertSame('page_builder', $container->getDefinition(PageLayoutKitEntityManagerRecoverySubscriber::class)->getArgument('$managerName'));

        $host = $this->container(['default' => 'x']);
        $host->setParameter(NowoPageLayoutKitExtension::ENTITY_MANAGER_PARAMETER, 'default');
        $host->setParameter('doctrine.default_entity_manager', 'default');
        $host->setDefinition('doctrine.orm.default_metadata_driver', $this->chain(true, false));
        $host->register(PageLayoutTransaction::class)->setArgument('$runner', null);
        new PageLayoutEntityManagerPass()->process($host);
        self::assertNull($host->getDefinition(PageLayoutTransaction::class)->getArgument('$runner'), 'never the resetting runner on the host default manager');
    }

    /**
     * @param array<string, string> $managers
     */
    private function container(array $managers): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(NowoPageLayoutKitExtension::ENTITY_MANAGER_PARAMETER, 'page_builder');
        $container->setParameter('doctrine.entity_managers', $managers);

        return $container;
    }

    /**
     * @return list<array{string, mixed}> method name and namespace argument of each call of the manager's chain
     */
    private function calls(ContainerBuilder $container, string $manager): array
    {
        $calls = [];
        foreach ($container->getDefinition('doctrine.orm.' . $manager . '_metadata_driver')->getMethodCalls() as $call) {
            $calls[] = [$call[0], $call[1][1] ?? null];
        }

        return $calls;
    }

    private function chain(bool $bundle, bool $host): Definition
    {
        $chain = new Definition();
        if ($bundle) {
            $chain->addMethodCall('addDriver', [new Definition(), PageLayoutEntityManagerPass::ENTITY_NAMESPACE]);
        }
        if ($host) {
            $chain->addMethodCall('addDriver', [new Definition(), 'App\\Entity']);
        }

        return $chain;
    }
}
