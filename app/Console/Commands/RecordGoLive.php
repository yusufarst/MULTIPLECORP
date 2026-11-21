<?php

namespace App\Console\Commands;

use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Modules\IAM\Recovery\RecoveryRefused;
use App\Modules\ORG\Services\GoLiveRecording;
use Illuminate\Console\Command;

/**
 * CUT-07 (TASK-077; PRQ-07; HO-32): the operator's one-time recording of go-live — the day this database became the
 * system of record — as the number allocator's setting kept with the deployment. Console only, never a route; a second
 * recording is refused and writes nothing; a run after a failed commit writes the missing audit event from the file.
 */
class RecordGoLive extends Command
{
    use ReadsOperatorInput;

    protected $signature = 'numbering:go-live {day? : the go-live day, Y-m-d (WIB)} {--operator= : your name, the operator\'s, recorded in the audit event}';

    protected $description = 'Record go-live once, as the number allocator\'s setting (operator only; after every company\'s seeds have committed)';

    public function handle(GoLiveRecording $recording): int
    {
        try {
            $operator = OperatorIdentity::stated($this->optionText('operator'));
            $day = $this->argument('day');
            $result = $recording->record(is_string($day) && $day !== '' ? $day : null, $operator);
        } catch (RecoveryRefused $refused) {
            $this->error($refused->getMessage());

            return self::FAILURE;
        }

        $this->info($result['outcome'] === 'RECORDED'
            ? "Go-live recorded: {$result['day']}. The numbering command now starts and seeds by it (NM-03)."
            : "Go-live's audit event written from its file: {$result['day']}. Nothing else changed.");

        return self::SUCCESS;
    }
}
