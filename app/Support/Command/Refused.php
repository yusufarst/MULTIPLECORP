<?php

namespace App\Support\Command;

use Closure;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * A command's refusal in phase C or D (SQ-02–SQ-05, SQ-08, SQ-10): the envelope rolls back to its savepoint,
 * records the terminal outcome on the `command_log` row and commits that alone (D-CC-05) — with, where the rule
 * requires it, the writes its `then` makes after the rollback: ST-03's conditional STALE transition and its
 * audit, SQ-23's routed audit events.
 */
final class Refused extends RuntimeException
{
    /**
     * @param  (Closure(ConnectionInterface): void)|null  $then
     */
    private function __construct(public readonly Outcome $outcome, public readonly ?Closure $then = null)
    {
        parent::__construct("{$outcome->kind} {$outcome->code}");
    }

    /**
     * REJECTED: a precondition or a rule is false (SQ-02, SQ-04, SQ-05, SQ-10).
     *
     * @param  array<string, mixed>  $state
     * @param  (Closure(ConnectionInterface): void)|null  $then  writes committed with the refusal (SQ-23)
     */
    public static function rejected(string $rule, array $state = [], ?Closure $then = null): self
    {
        return new self(Outcome::rejected($rule, $state), $then);
    }

    /**
     * CONFLICT: stale state, a transition that affected no row, a business-identity key (SQ-03, SQ-08).
     *
     * @param  array<string, mixed>  $state
     * @param  (Closure(ConnectionInterface): void)|null  $then  writes committed with the refusal (ST-03)
     */
    public static function conflict(string $cause, array $state = [], ?Closure $then = null): self
    {
        return new self(Outcome::conflict($cause, $state), $then);
    }
}
