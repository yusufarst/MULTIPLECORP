<?php

namespace App\Modules\IAM\Services;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Support\Database\Transactions;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The in-command re-authorization of CONCURRENCY_IDEMPOTENCY RV-01 (H6-01; PERMISSIONS_MATRIX AZ-05): inside
 * its transaction a command locks its actor's `users` row (LK-03), then re-reads the account's active flag,
 * role and grants and re-checks scope and capability with its prerequisites. An access change locks the
 * same row (RV-02), so a command waits for a change in flight and is then judged on the committed state
 * (RV-03). The target's company outside scope answers as not found, the capability missing as forbidden
 * (AZ-11); both are security events. The command envelope (TASK-013) calls it in its lock order, before
 * any precondition is read.
 */
final class CommandAuthorization
{
    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly SecurityEventRecorder $events,
    ) {}

    /**
     * @param  int|null  $companyId  the command's company (CS-03), null for a command without one
     */
    public function reauthorize(int $actorUserId, string $capability, ?int $companyId = null): Authority
    {
        if (! CapabilityCatalogue::has($capability)) {
            throw new InvalidArgumentException("{$capability} is not a capability of PERMISSIONS_MATRIX §4.");
        }

        if (! Transactions::applicationOpen()) {
            throw new LogicException('A command re-authorizes inside its own transaction (RV-01).');
        }

        // LR-03's one requested mode; an access change takes the same row in the same mode (RV-02).
        DB::table('users')->where('id', $actorUserId)->lock('for no key update')->value('id');

        return $this->reauthorizeLocked($actorUserId, $capability, $companyId);
    }

    /**
     * The re-check of RV-01 for a command whose envelope already holds the actor's row in its lock order
     * (CONCURRENCY_IDEMPOTENCY §6 phase B).
     */
    public function reauthorizeLocked(int $actorUserId, string $capability, ?int $companyId = null): Authority
    {
        if (! CapabilityCatalogue::has($capability)) {
            throw new InvalidArgumentException("{$capability} is not a capability of PERMISSIONS_MATRIX §4.");
        }

        $authority = $this->capabilities->fresh($actorUserId);

        if (! $authority->active) {
            $this->deny($actorUserId, 'INACTIVE', $capability);

            throw new AuthorizationException;
        }

        if ($companyId !== null && ! $authority->scope->includes($companyId)) {
            $this->deny($actorUserId, 'OUT_OF_SCOPE', $capability);

            // Answered as a missing record (AZ-11): the exception handler names no model (WS-10).
            throw new ModelNotFoundException;
        }

        if (! $authority->has($capability)) {
            $this->deny($actorUserId, 'CAPABILITY', $capability);

            throw new AuthorizationException;
        }

        return $authority;
    }

    private function deny(int $userId, string $reason, string $capability): void
    {
        $this->events->record(SecurityEventKind::AccessDenied, userId: $userId, reasonCode: $reason, details: ['capability' => $capability]);
    }
}
