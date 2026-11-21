<?php

namespace App\Modules\ORG\Services;

use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Modules\IAM\Recovery\RecoveryRefused;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use Illuminate\Support\Facades\DB;

/**
 * The operator's one-time recording of go-live (TASK-077; PRQ-07; HO-32; NM-03; INFRASTRUCTURE §4.2, DIR-101): the
 * store's file created with exclusive creation, then the audit event `ORG.NumberingGoLiveRecorded` committed with the
 * operator's identity and no application actor. A run that finds the file and no event writes the event from the file
 * and changes nothing else — the repair of a commit that failed —; a run that finds both is refused and writes nothing.
 * Go-live is recorded only once every import batch is committed or aborted, so every company's seeds have committed
 * first (CUT-05–CUT-07; architecture challenge 14b).
 */
final class GoLiveRecording
{
    public const ACTION = 'ORG.NumberingGoLiveRecorded';

    /** The advisory lock every recording and repair holds from its first read to its commit, so two runs never both write. */
    private const LOCK = 2_026_112_077;

    public function __construct(private readonly AuditRecorder $audit) {}

    /** @return array{outcome: 'RECORDED'|'REPAIRED', day: string} */
    public function record(?string $day, OperatorIdentity $operator, ?FileGoLive $store = null): array
    {
        OperatorIdentity::ensureConsole();
        $store ??= FileGoLive::configured();

        return DB::transaction(function () use ($day, $operator, $store): array {
            DB::select('select pg_advisory_xact_lock(?)', [self::LOCK]);

            return $this->underLock($day, $operator, $store);
        });
    }

    /** @return array{outcome: 'RECORDED'|'REPAIRED', day: string} */
    private function underLock(?string $day, OperatorIdentity $operator, FileGoLive $store): array
    {
        $recorded = DB::table('audit_events')->where('action', self::ACTION)->exists();

        if (is_file($store->path())) {
            $kept = $store->day();

            if ($kept === null) {
                throw new RecoveryRefused('The go-live file exists but does not hold a Y-m-d day; nothing was written — the operator inspects it.');
            }

            if ($recorded) {
                throw new RecoveryRefused("Go-live is recorded already: {$kept}. A second recording writes nothing (HO-32).");
            }

            if ($day !== null && $day !== $kept) {
                throw new RecoveryRefused("The go-live file holds {$kept}; run again with that day to write its audit event. Nothing was written.");
            }

            $this->event($kept, $operator, repaired: true);

            return ['outcome' => 'REPAIRED', 'day' => $kept];
        }

        if ($recorded) {
            throw new RecoveryRefused('Go-live\'s audit event exists but its file does not: the store is missing. Nothing was written — the operator restores the file to the event\'s day.');
        }

        $valid = $day === null ? null : FileGoLive::valid($day);

        if ($valid === null) {
            throw new RecoveryRefused('Give go-live\'s day as a real Y-m-d date (WIB).');
        }

        $pending = DB::table('import_batches')->whereIn('state', ['PREPARED', 'DRY_RUN_PASSED', 'SIGNED_OFF'])->count();

        if ($pending > 0) {
            throw new RecoveryRefused("{$pending} import batch(es) are neither committed nor aborted: go-live is recorded after every company's seeds have committed (CUT-07). Nothing was written.");
        }

        $directory = dirname($store->path());

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RecoveryRefused('The go-live file\'s directory cannot be created on the private storage.');
        }

        $file = @fopen($store->path(), 'x');

        if ($file === false) {
            throw new RecoveryRefused('The go-live file could not be created exclusively: another run may have created it. Run again to see its state.');
        }

        try {
            fwrite($file, $valid."\n");
            fflush($file);
            fsync($file);
        } finally {
            fclose($file);
        }

        chmod($store->path(), 0600);
        $this->event($valid, $operator, repaired: false);

        return ['outcome' => 'RECORDED', 'day' => $valid];
    }

    private function event(string $day, OperatorIdentity $operator, bool $repaired): void
    {
        $this->audit->record(
            action: self::ACTION,
            authorityClass: AuthorityClass::System,
            actorUserId: null,
            companyId: null,
            entityType: 'numbering_go_live',
            metadata: ['operator' => $operator->toArray(), 'go_live' => $day, 'repaired' => $repaired],
        );
    }
}
