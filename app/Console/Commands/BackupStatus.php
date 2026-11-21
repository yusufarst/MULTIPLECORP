<?php

namespace App\Console\Commands;

use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Support\Backup\BackupLog;
use App\Support\Backup\Generations;
use App\Support\Backup\GenerationStatus;
use App\Support\Backup\OffsiteCopy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * BK-04, BK-07, BK-10 (TASK-074): every generation's truthful status, read from its bytes; a corrupt generation, no
 * verified backup within 26 hours, or no verified off-server copy within 26 hours is a failure the operator is alerted
 * to (TASK-075 reads the alert), never reported healthy.
 */
class BackupStatus extends Command
{
    protected $signature = 'backup:status {--quick : check each part\'s size only, not its checksum}';

    protected $description = 'Report each backup generation\'s status and raise a stale or corrupt backup (operator only)';

    public function handle(): int
    {
        OperatorIdentity::ensureConsole();
        $staleAfter = config()->integer('backup.stale_hours');
        $newest = null;
        $healthy = true;

        foreach (Generations::in(config()->string('backup.directory')) as $generation) {
            $verification = Generations::verify($generation['path'], ! $this->option('quick'));
            $this->line("{$generation['name']}  ".($verification['manifest']->kind ?? '-')."  {$verification['status']->value}  {$verification['reason']}");

            if ($verification['status']->verified()) {
                $newest ??= $generation['at'];
            } elseif ($verification['status'] === GenerationStatus::Corrupt) {
                BackupLog::alert('a backup generation is corrupt', ['generation' => $generation['name'], 'reason' => $verification['reason']]);
                $healthy = false;
            }
        }

        if ($newest === null || $newest->lt(CarbonImmutable::now()->subHours($staleAfter))) {
            BackupLog::alert('no verified backup within the stale window', ['hours' => $staleAfter, 'newest' => $newest?->toIso8601ZuluString()]);
            $this->error("STALE: no verified backup within {$staleAfter} hours.");
            $healthy = false;
        }

        $destination = config('backup.copy.destination');

        if (is_string($destination) && $destination !== '') {
            $copy = null;

            foreach (Generations::in($destination) as $candidate) {
                if (OffsiteCopy::verifyCopy($candidate['path']) === null) {
                    $copy = $candidate['at'];

                    break;
                }
            }

            $this->line('Off-server copy: '.($copy?->toIso8601ZuluString() ?? 'none verified'));

            if ($copy === null || $copy->lt(CarbonImmutable::now()->subHours($staleAfter))) {
                BackupLog::alert('no verified off-server copy within the stale window', ['hours' => $staleAfter]);
                $this->error("STALE: no verified off-server copy within {$staleAfter} hours.");
                $healthy = false;
            }
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
