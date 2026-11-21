<?php

namespace App\Console\Commands;

use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Support\Backup\OffsiteCopy;
use Illuminate\Console\Command;

/** BKR-09 (TASK-074): the encrypted off-server copy of a verified generation — the newest verified daily one by default. */
class BackupCopy extends Command
{
    protected $signature = 'backup:copy {generation? : the generation to copy; the newest verified daily one by default}';

    protected $description = 'Copy a verified backup generation, encrypted, to the off-server destination and verify it there (operator only)';

    public function handle(OffsiteCopy $copy): int
    {
        OperatorIdentity::ensureConsole();
        $generation = $this->argument('generation');
        $outcome = $copy->copy(config()->string('backup.directory'), is_string($generation) ? $generation : null);
        $this->line("Off-server copy: {$outcome['status']} — ".($outcome['generation'] ?? 'no generation')." — {$outcome['reason']}");

        return $outcome['status'] === 'VERIFIED' ? self::SUCCESS : self::FAILURE;
    }
}
