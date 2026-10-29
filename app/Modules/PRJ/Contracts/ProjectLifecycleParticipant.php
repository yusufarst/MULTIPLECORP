<?php

namespace App\Modules\PRJ\Contracts;

use App\Support\Command\CommandContext;

/**
 * PRJ's inversion contract for what a project's lifecycle triggers in a higher tier (ARCHITECTURE §3): INV, FUL, FIN
 * and ADM each implement it in their own Task and register it under {@see self::TAG}. PRJ's action calls every
 * participant inside its own transaction, the project held at LK-01 first (CONCURRENCY_IDEMPOTENCY §7) — a
 * confirmation giving the project its default invoice requirement (L-27; TASK-035), a re-confirmation adjusting
 * reservations (AX-13; TASK-034), a cancellation releasing them (AX-27; TASK-056), a revalidation reopening
 * requirements (SF-REVAL; TASK-055).
 */
interface ProjectLifecycleParticipant
{
    public const TAG = 'prj.lifecycle-participants';

    /** A confirmation, first or later, committed the project's pins and guards (AX-13). */
    public function confirmed(CommandContext $context, int $projectId): void;

    /** A later confirmation superseded the project's pinned demand (AX-13). */
    public function reconfirmed(CommandContext $context, int $projectId): void;

    /**
     * Lines' unfilled remainder was cancelled or split off to another mode (AX-14; SF-CANCEL-LINE; L-21): the reservation
     * above each line's new remainder is released before PRJ lowers its guard.
     *
     * @param  array<int, string>  $released  by project line, the base quantity leaving its remainder
     */
    public function scopeReduced(CommandContext $context, int $projectId, array $released): void;

    /** The project was cancelled (AX-27; BR-PRJ-01). */
    public function cancelled(CommandContext $context, int $projectId): void;

    /** A material correction returned the completed project to ACTIVE (SF-REVAL; BR-CR-05). */
    public function revalidated(CommandContext $context, int $projectId): void;
}
