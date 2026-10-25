<?php

namespace App\Support\Command;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The `command_log` of CONCURRENCY_IDEMPOTENCY CI-03–CI-07 (DATABASE §4.13): the identity row inserted first in
 * a command's transaction at LK-00, IN_PROGRESS only inside it, and its terminal outcome written with its
 * completion and a 30-day expiry. The actor and company are plain values without foreign keys (CI-03).
 */
final class CommandLog
{
    public const RETENTION_DAYS = 30;

    /** The replay probe of phase A: the terminal row of the identity, if one is committed (CI-05). */
    public function probe(string $commandId): ?RecordedCommand
    {
        $row = DB::table('command_log')->where('command_id', $commandId)->first();

        return $row instanceof stdClass ? RecordedCommand::of($row) : null;
    }

    /**
     * The first statement of the command's transaction (CI-03): true when this transaction now holds the identity;
     * false when another holds or held it — a duplicate in flight waits here until the first ends (CI-04).
     */
    public function claim(ConnectionInterface $connection, CommandRequest $request): bool
    {
        return $connection->table('command_log')->insertOrIgnore([
            'command_id' => $request->commandId,
            'command_type' => $request->commandType,
            'actor_user_id' => $request->actorUserId,
            'company_id' => $request->companyId,
            'payload_fingerprint' => $request->fingerprint,
            'state' => 'IN_PROGRESS',
        ]) === 1;
    }

    /** The terminal outcome, written by the transaction that holds the identity (D-CC-05; CI-06, CI-07). */
    public function finish(ConnectionInterface $connection, string $commandId, Outcome $outcome): void
    {
        $now = CarbonImmutable::now();

        $connection->table('command_log')->where('command_id', $commandId)->where('state', 'IN_PROGRESS')->update([
            'state' => $outcome->kind,
            'result_entity_type' => $outcome->resultType,
            'result_entity_id' => $outcome->resultId,
            'outcome_code' => $outcome->code,
            'completed_at' => $now,
            'expires_at' => $now->addDays(self::RETENTION_DAYS),
        ]);
    }
}
