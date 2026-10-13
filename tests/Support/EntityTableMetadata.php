<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Support;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ClassMetadata;
use ReflectionClass;

/**
 * Real (unprefixed) ClassMetadata for EntityManager mocks used by native-SQL repositories.
 */
final class EntityTableMetadata
{
    /**
     * @param class-string $entityClass
     *
     * @return ClassMetadata<object>
     */
    public static function for(string $entityClass): ClassMetadata
    {
        $table    = (new ReflectionClass($entityClass))->getAttributes(ORM\Table::class)[0]->newInstance();
        $metadata = new ClassMetadata($entityClass);
        $metadata->setPrimaryTable(['name' => (string) $table->name]);

        return $metadata;
    }
}
