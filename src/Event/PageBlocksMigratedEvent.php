<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Event;

/**
 * Legacy page content was migrated into typed page blocks (`nowo:page-layout-kit:migrate` / `PageBlockMigrator`).
 *
 * Plain value object (scalars only): dispatched after the migration is committed (2.2, rules 4/7).
 */
final readonly class PageBlocksMigratedEvent
{
    /**
     * @param list<string> $pageKeys Pages whose layout was (re)built
     */
    public function __construct(
        public array $pageKeys,
        public bool $forced,
    ) {
    }
}
