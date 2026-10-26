<?php

namespace App\Support\Guards;

/**
 * A guard family that is authoritative and never rebuilt, so `ReconcileGuards` verifies it instead of recomputing
 * it, and `GuardMaintenance` never touches it (DATABASE §3.1; DIR-098 reading 6): its check is a SELECT of the rows
 * that break the family's rule, each with its key, the stored value and what the facts require of it.
 */
final class Verification
{
    /**
     * @param  list<string>  $key
     */
    public function __construct(
        public readonly string $table,
        public readonly array $key,
        public readonly string $violations,
    ) {}
}
