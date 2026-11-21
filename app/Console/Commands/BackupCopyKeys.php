<?php

namespace App\Console\Commands;

use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Support\Backup\Scalars;
use App\Support\Backup\SealedStream;
use Illuminate\Console\Command;

/**
 * BKR-09 (TASK-074): the copy's key pair, made on the operator's own machine, never on the VPS — the secret key written
 * to the file named for the secret store, the public key printed for `BACKUP_COPY_PUBLIC_KEY`.
 */
class BackupCopyKeys extends Command
{
    protected $signature = 'backup:copy-keys {--secret-out= : the new file the secret key is written to, off the VPS}';

    protected $description = 'Make the off-server copy\'s key pair, off the VPS (operator only)';

    public function handle(): int
    {
        OperatorIdentity::ensureConsole();
        $out = Scalars::text($this->option('secret-out'));

        if ($out === '' || file_exists($out)) {
            $this->error('Name a new file for the secret key with --secret-out.');

            return self::INVALID;
        }

        $pair = SealedStream::keyPair();
        file_put_contents($out, $pair['secret']."\n");
        chmod($out, 0600);
        $this->line("BACKUP_COPY_PUBLIC_KEY={$pair['public']}");
        $this->line('The secret key is in the file named; keep it in the secret store, apart from the VPS and the copies.');

        return self::SUCCESS;
    }
}
