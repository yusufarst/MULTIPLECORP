<?php

namespace Tests\Support;

use App\Modules\DOC\Actions\RegisterEvidence;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;

/**
 * TASK-030's race steps (BD-14; BD-15; FU-10): autoloaded, so the sessions' own processes reach them — a closure's
 * test file is not loaded there. One registration's answer as a word, and a registration that holds one of its
 * registration keys first, until the other session waits on it, so the two overlap at LK-28 for certain.
 */
final class EvidenceRaces
{
    /** @param  array{file: string, type: string, issuer: string, reference: ?string, date: string}  $input */
    public static function register(int $actorId, int $companyId, string $companyPublicId, array $input): string
    {
        $outcome = app(RegisterEvidence::class)->run((string) Str::uuid(), $actorId, $companyId, $companyPublicId,
            [...$input, 'amount' => null, 'deliverer' => null, 'received' => null, 'acknowledged' => [], 'reason' => null]);

        return trim("{$outcome->kind} ".($outcome->kind === Outcome::COMMITTED ? '' : $outcome->code));
    }

    /**
     * Holds the key on its session — a session-level advisory lock is re-entrant there —, lets the other session
     * start and wait on it, then registers and releases.
     *
     * @param  array{int, int}  $key
     * @param  array{file: string, type: string, issuer: string, reference: ?string, date: string}  $input
     */
    public static function holdThenRegister(array $key, int $actorId, int $companyId, string $companyPublicId, array $input): string
    {
        DB::select('select pg_advisory_lock(?::integer, ?::integer)', $key);

        try {
            Barrier::await('the first registration holds its key');
            Sessions::awaitBlocked();

            return self::register($actorId, $companyId, $companyPublicId, $input);
        } finally {
            DB::select('select pg_advisory_unlock(?::integer, ?::integer)', $key);
        }
    }
}
