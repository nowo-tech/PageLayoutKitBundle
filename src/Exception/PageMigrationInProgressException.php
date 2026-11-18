<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Exception;

use RuntimeException;
use Throwable;

/**
 * Another page block migration holds the migration lock (2.3, REQ-RUNTIME-003 rule 4): this run changed nothing.
 * Run the command again once the other one finished.
 */
final class PageMigrationInProgressException extends RuntimeException
{
    public const string MESSAGE = 'Another page block migration is running: nothing was changed. Run the migration again once it finished.';

    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(self::MESSAGE, 0, $previous);
    }
}
