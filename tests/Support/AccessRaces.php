<?php

namespace Tests\Support;

use App\Modules\IAM\Services\CommandAuthorization;
use App\Support\Command\Outcome;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;

/**
 * PG-060's race tests' steps (TASK-024; RV-01–RV-05; M-20–M-22): autoloaded, so the sessions' own processes reach them
 * — a closure's test file is not loaded there.
 */
final class AccessRaces
{
    /** An NX-02 command's answer as a word: its outcome kind and code, or the denial a stale actor gets (AZ-11). */
    public static function outcome(Closure $command): string
    {
        try {
            $outcome = $command();

            return $outcome instanceof Outcome ? trim("{$outcome->kind} {$outcome->code}") : 'none';
        } catch (AuthorizationException) {
            return 'denied';
        }
    }

    /** The target's command in flight: it holds its own row at LK-03 (RV-01) until another session waits on it. */
    public static function holdTarget(int $userId, string $capability, int $companyId): string
    {
        return DB::transaction(function () use ($userId, $capability, $companyId): string {
            app(CommandAuthorization::class)->reauthorize($userId, $capability, $companyId);
            Barrier::await('the target\'s command holds its row');
            Sessions::awaitBlocked();

            return 'committed';
        });
    }
}
