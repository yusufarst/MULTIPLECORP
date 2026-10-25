<?php

namespace App\Support\Command;

use RuntimeException;

/**
 * §5 step 4: the lock set re-derived under the locks grew, so the whole action rolls back and restarts from
 * phase A with the union of the sets derived so far (RY-03, RY-05).
 */
final class Restart extends RuntimeException
{
    public function __construct(public readonly LockSet $union)
    {
        parent::__construct('The lock set grew under its locks; the action restarts (CONCURRENCY_IDEMPOTENCY §5).');
    }
}
