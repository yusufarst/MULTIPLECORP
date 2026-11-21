<?php

namespace App\Console\Commands;

use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Support\Backup\OffsiteCopy;
use App\Support\Backup\Scalars;
use Illuminate\Console\Command;

/**
 * BKR-09's drill (TASK-074; BACKUP_RECOVERY §5): an off-server copy brought back — verified, decrypted with the secret
 * key the operator gives this run from the secret store, and verified as a generation — for §4's restore, unchanged.
 */
class BackupFetch extends Command
{
    protected $signature = 'backup:fetch {copy : the copy\'s directory at its destination} {--secret-key-file= : the copy\'s secret key, from the operator\'s secret store} {--into= : where the generation is brought back}';

    protected $description = 'Bring an off-server copy back as a verified backup generation (operator only)';

    public function handle(): int
    {
        OperatorIdentity::ensureConsole();
        $keyFile = Scalars::text($this->option('secret-key-file'));
        $into = Scalars::text($this->option('into')) ?: config()->string('backup.directory');

        if ($keyFile === '' || ! is_file($keyFile)) {
            $this->error('Give the copy\'s secret key with --secret-key-file, from the secret store; it is never kept on the VPS.');

            return self::INVALID;
        }

        $outcome = OffsiteCopy::fetch(Scalars::text($this->argument('copy')), trim((string) file_get_contents($keyFile)), $into);
        $this->line("Fetched: {$outcome['generation']} — {$outcome['status']->value} — {$outcome['reason']}");

        return $outcome['status']->verified() ? self::SUCCESS : self::FAILURE;
    }
}
