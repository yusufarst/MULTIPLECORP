<?php

namespace App\Console\Commands;

use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Support\Backup\BackupRunner;
use App\Support\Backup\Scalars;
use Illuminate\Console\Command;

/**
 * BK-03, BK-04 (TASK-074): one backup run, by the operator account — daily at `<BACKUP_TIME>` from its cron entry, and
 * before every deployment, whose gate is this command's success (REL-08). Exit 0 only when the generation verified and
 * its cleanup completed; a failed, skipped or partial run is never reported successful (BKR-02).
 */
class BackupRun extends Command
{
    protected $signature = 'backup:run {--kind=daily : daily, predeploy, preimport or manual}';

    protected $description = 'Write and verify one backup generation, then remove the generations the retention lets expire (operator only)';

    public function handle(BackupRunner $runner): int
    {
        OperatorIdentity::ensureConsole();
        $outcome = $runner->run(Scalars::text($this->option('kind')));
        $this->line("Backup: {$outcome['status']} — ".($outcome['generation'] ?? 'no generation')." — {$outcome['reason']}");

        if ($outcome['disk'] !== null) {
            $this->line("Disk: {$outcome['disk']->level}, {$outcome['disk']->usedPct}% used");
        }

        if ($outcome['cleanup'] !== null) {
            $this->line("Cleanup: {$outcome['cleanup']['status']} — {$outcome['cleanup']['reason']}");
        }

        return $outcome['status'] === 'VERIFIED' && ($outcome['cleanup']['status'] ?? 'OK') === 'OK' ? self::SUCCESS : self::FAILURE;
    }
}
