<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Support;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\PageLayoutKitBundle\DependencyInjection\TablePrefixListener;

use const CASE_LOWER;

/**
 * In-memory SQLite EntityManager with the bundle schema, mirroring a Symfony host
 * (underscore naming strategy, optional {@see TablePrefixListener}).
 */
final class SqliteEntityManagerFactory
{
    /**
     * @param callable(EventManager): void|null $configureEvents
     */
    public static function create(string $tablePrefix = '', ?callable $configureEvents = null): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../../src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        $config->enableNativeLazyObjects(true);

        $events = new EventManager();
        $events->addEventListener(Events::loadClassMetadata, new TablePrefixListener($tablePrefix));
        if ($configureEvents !== null) {
            $configureEvents($events);
        }

        $connection    = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $entityManager = new EntityManager($connection, $config, $events);
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }
}
