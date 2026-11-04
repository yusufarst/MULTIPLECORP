<?php

namespace App\Modules\ImportOrchestration\Services;

use RuntimeException;

/** A row its commit cannot run — a reference that resolves to nothing in its company —, with the column and reason. */
final class RowRefused extends RuntimeException
{
    public function __construct(public readonly string $field, public readonly string $reason)
    {
        parent::__construct("The import row was refused: {$field} {$reason}.");
    }
}
