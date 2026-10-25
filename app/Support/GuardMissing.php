<?php

namespace App\Support;

use RuntimeException;

/**
 * A guard update that affected fewer rows than it named (GU-02; SQ-18): the command ends FAILED, nothing
 * committed, with a guard-missing alert; it is never retried automatically and the row is never created here.
 */
final class GuardMissing extends RuntimeException
{
    public function __construct(public readonly string $table, int $named, int $affected)
    {
        parent::__construct("A guard update of {$table} named {$named} rows and affected {$affected} (GU-02; SQ-18).");
    }
}
