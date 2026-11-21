<?php

namespace App\Console\Commands;

use App\Modules\DOC\Jobs\RenderDocument;
use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Modules\IAM\Recovery\RecoveryRefused;
use App\Support\Backup\BackupLog;
use App\Support\Backup\Restorer;
use App\Support\Backup\Scalars;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * BACKUP_RECOVERY §4 steps 2–5 (TASK-074; BK-08; BKR-05): a verified generation restored into a new database and a new
 * files' directory, reconciled, and read by `ReconcileGuards`, with MULTIPLECORP in maintenance mode and its workers
 * and scheduler stopped. Its record — recovery point, elapsed time, RTO and RPO, reconciliation, guards, usable or not
 * — is kept in the backup directory without a secret. Exit 0 only when the restored state is usable.
 */
class BackupRestore extends Command
{
    protected $signature = 'backup:restore {generation} {--database= : the new, empty database} {--files-root= : the new files\' directory}
        {--source= : the directory holding the generation; the backup directory by default} {--fallback : take the next older verified generation when this one does not verify}
        {--operator= : the operator\'s stated name}';

    protected $description = 'Restore a backup generation into isolation, reconcile it and read the guards (operator only, maintenance window)';

    public function handle(Restorer $restorer): int
    {
        try {
            $operator = OperatorIdentity::stated(is_string($this->option('operator')) ? $this->option('operator') : null);
        } catch (RecoveryRefused $refused) {
            $this->error($refused->getMessage());

            return self::INVALID;
        }

        $directory = config()->string('backup.directory');

        try {
            $record = $restorer->restore(Scalars::text($this->option('source')) ?: $directory, Scalars::text($this->argument('generation')), Scalars::text($this->option('database')),
                Scalars::text($this->option('files-root')), $operator->describe(), (bool) $this->option('fallback'),
                function (int $attempt): void {
                    $this->laravel->call([new RenderDocument($attempt), 'handle']);
                });
        } catch (RuntimeException $refused) {
            BackupLog::alert('the restore was refused or failed', ['reason' => $refused->getMessage()]);
            $this->error('Restore refused: '.$refused->getMessage());

            return self::FAILURE;
        }

        $records = $directory.'/restores';

        if (! is_dir($records)) {
            mkdir($records, 0700, true);
        }

        $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $path = $records.'/'.str_replace([':', '-'], '', Scalars::text($record['started_at'])).'-'.Scalars::text($record['generation']).'.json';
        file_put_contents($path, $json."\n");
        chmod($path, 0600);
        $this->line($json);
        $this->line('Record: '.$path);

        return $record['usable'] === true ? self::SUCCESS : self::FAILURE;
    }
}
