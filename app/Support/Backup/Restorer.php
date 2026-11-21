<?php

namespace App\Support\Backup;

use App\Modules\DOC\Models\DocumentRendition;
use App\Support\Database\DatabaseRole;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * The restore of BACKUP_RECOVERY §4 (BK-08; BKR-05; BKR-07; BKR-08; GAP-011), steps 2–5, run by the operator alone with
 * MULTIPLECORP in maintenance mode and its workers and scheduler stopped (step 1). The chosen generation verified — a
 * corrupt or missing one sends the operator to the next older verified one —; the dump restored into a new, empty
 * database of MULTIPLECORP's own instance and the files into a new directory, never over the live ones; the
 * generation's paired key checked against the restored TOTP ciphertext before anything reads it, a leaked key's
 * generation refused to AU-25's Scenario B; then the reconciliation — every file row with its bytes and every byte
 * file with its row, each issued version with a rendition, a missing one rendered again from the issued payload and
 * never reissued (GAP-007) — and `ReconcileGuards`' read, a difference reported, never repaired. The record holds the
 * recovery point, the elapsed time against the 4-hour RTO, the recovery point's age against the 24-hour RPO and
 * whether the restored state is usable (AC-18; AC-19).
 */
final class Restorer
{
    public function __construct(private readonly Reconciler $reconciler) {}

    /**
     * @param  callable(int): void  $render  JB-01's render of one rendition attempt, given by the console
     * @return array<string, mixed> the restore's record, without a secret
     */
    public function restore(string $sourceDirectory, string $generation, string $database, string $filesRoot, string $operator, bool $fallback, callable $render): array
    {
        $started = CarbonImmutable::now();
        $chosen = $this->choose($sourceDirectory, $generation, $fallback);
        $manifest = $chosen['manifest'];

        if (in_array($manifest->keyRegisterEntry, (array) config('backup.leaked_key_entries'), true)) {
            throw new RuntimeException("the generation is paired with the leaked key {$manifest->keyRegisterEntry}: restore it by AU-25's Scenario B with new keys (INFRASTRUCTURE §7.3 step 5), never with that key");
        }

        $this->refuseLiveTargets($database, $filesRoot);
        $target = PostgresTools::login($database);
        $this->assertEmpty($target);
        PostgresTools::assertVersion($target, config()->string('backup.pg_restore'));
        $this->restoreDump($target, $chosen['path'].'/'.Manifest::DUMP);

        if (! is_dir($filesRoot) && ! mkdir($filesRoot, 0700, true) && ! is_dir($filesRoot)) {
            throw new RuntimeException('the restored files\' directory cannot be created');
        }

        $extracted = TarArchive::extract($chosen['path'].'/'.Manifest::FILES, $filesRoot);
        $live = $this->switchTo($database, $filesRoot);

        try {
            $this->assertPairedKey($manifest->keyRegisterEntry);
            $files = $this->reconcileFiles($filesRoot);
            $renditions = $this->renderOwed($files['missing_ids'], $render);
            $guards = $this->guards();
        } finally {
            $this->switchBack($live);
        }

        $finished = CarbonImmutable::now();
        $elapsed = (int) $started->diffInSeconds($finished, true);
        $age = (int) $manifest->recoveryPoint->diffInSeconds($started, true);
        unset($files['missing_ids']);
        $usable = $files['missing_bytes'] === 0 && $files['mismatched_bytes'] === 0 && $renditions['failed'] === 0 && $guards['differences'] === [];

        $record = [
            'generation' => $chosen['generation'],
            'requested' => $generation,
            'source' => $sourceDirectory,
            'operator' => $operator,
            'key_register_entry' => $manifest->keyRegisterEntry,
            'recovery_point' => $manifest->recoveryPoint->toIso8601ZuluString(),
            'started_at' => $started->toIso8601ZuluString(),
            'finished_at' => $finished->toIso8601ZuluString(),
            'elapsed_seconds' => $elapsed,
            'within_rto' => $elapsed <= config()->integer('backup.rto_seconds'),
            'recovery_point_age_seconds' => $age,
            'within_rpo' => $age <= config()->integer('backup.rpo_seconds'),
            'database' => $database,
            'files_extracted' => $extracted,
            'files' => $files,
            'renditions' => $renditions,
            'guards' => $guards,
            'usable' => $usable,
        ];

        BackupLog::outcome($usable ? 'restore usable' : 'restore NOT usable', ['generation' => $chosen['generation'], 'database' => $database]);

        if (! $usable) {
            BackupLog::alert('the restored state is not usable', ['generation' => $chosen['generation'], 'database' => $database]);
        }

        return $record;
    }

    /** @return array{generation: string, path: string, manifest: Manifest} */
    private function choose(string $sourceDirectory, string $generation, bool $fallback): array
    {
        $verification = Generations::verify($sourceDirectory.'/'.$generation);

        if ($verification['status']->verified() && $verification['manifest'] !== null) {
            return ['generation' => $generation, 'path' => $sourceDirectory.'/'.$generation, 'manifest' => $verification['manifest']];
        }

        foreach (Generations::in($sourceDirectory) as $older) {
            if (strcmp($older['name'], $generation) >= 0) {
                continue;
            }

            $next = Generations::verify($older['path']);

            if ($next['status']->verified() && $next['manifest'] !== null) {
                if (! $fallback) {
                    throw new RuntimeException("{$generation} is {$verification['status']->value} ({$verification['reason']}); the next older verified generation is {$older['name']} — restore it, or pass --fallback");
                }

                return ['generation' => $older['name'], 'path' => $older['path'], 'manifest' => $next['manifest']];
            }
        }

        throw new RuntimeException("{$generation} is {$verification['status']->value} ({$verification['reason']}) and no older generation verifies");
    }

    private function refuseLiveTargets(string $database, string $filesRoot): void
    {
        if (preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $database) !== 1) {
            throw new RuntimeException('name the new database in lower case letters, digits and underscores');
        }

        foreach ((array) config('database.connections') as $connection) {
            if (is_array($connection) && ($connection['database'] ?? null) === $database) {
                throw new RuntimeException('the restore never goes into the live database: name a new one (BKR-05)');
            }
        }

        $root = rtrim(str_replace('\\', '/', $filesRoot), '/');

        foreach (array_keys((array) config('filesystems.disks')) as $disk) {
            $live = config("filesystems.disks.{$disk}.root");
            $live = is_string($live) ? rtrim(str_replace('\\', '/', $live), '/') : null;

            if ($live !== null && $live !== '' && ($root === $live || str_starts_with($root.'/', $live.'/') || str_starts_with($live.'/', $root.'/'))) {
                throw new RuntimeException('the restore never goes into or over a live files\' directory: name a new one (BKR-05)');
            }
        }

        if (is_dir($filesRoot) && (scandir($filesRoot) ?: []) !== ['.', '..']) {
            throw new RuntimeException('the restored files\' directory must be new or empty');
        }
    }

    private function assertEmpty(Connection $target): void
    {
        $relations = Scalars::int($target->scalar("select count(*) from pg_class c join pg_namespace n on n.oid = c.relnamespace
            where n.nspname = 'public' and c.relkind in ('r', 'p', 'v', 'm', 'S')
                and not exists (select from pg_depend d where d.classid = 'pg_class'::regclass and d.objid = c.oid and d.deptype = 'e')"));

        if ($relations > 0) {
            throw new RuntimeException('the new database is not empty: a restore goes into an empty database the operator created for it');
        }
    }

    /**
     * The dump restored in one transaction, stopping at the first error. Its extensions are the database's own: each
     * is created first where the restoring role may — the trusted ones —, else the operator creates it as the
     * superuser before the restore; their entries are then left out of the restore's list.
     */
    private function restoreDump(Connection $target, string $dump): void
    {
        $tool = config()->string('backup.pg_restore');
        $listing = Process::timeout(600)->run([$tool, '--list', $dump]);

        if (! $listing->successful()) {
            throw new RuntimeException('the dump\'s contents cannot be listed: '.trim(mb_substr($listing->errorOutput(), 0, 400)));
        }

        $kept = [];

        foreach (explode("\n", $listing->output()) as $line) {
            if (preg_match('/^\d+; \d+ \d+ EXTENSION - (\S+)/', $line, $match) === 1) {
                $this->ensureExtension($target, $match[1]);

                continue;
            }

            if (preg_match('/^\d+; \d+ \d+ COMMENT - EXTENSION /', $line) === 1) {
                continue;
            }

            $kept[] = $line;
        }

        $list = tempnam(sys_get_temp_dir(), 'restore-list-');

        if ($list === false) {
            throw new RuntimeException('the restore\'s list cannot be written');
        }

        try {
            file_put_contents($list, implode("\n", $kept));
            PostgresTools::run($target, $tool, ['--exit-on-error', '--single-transaction', '--use-list='.$list, $dump]);
        } finally {
            @unlink($list);
        }
    }

    private function ensureExtension(Connection $target, string $extension): void
    {
        if ((bool) $target->scalar('select exists (select from pg_extension where extname = ?)', [$extension])) {
            return;
        }

        try {
            $target->statement('create extension if not exists '.$target->getQueryGrammar()->wrap($extension));
        } catch (Throwable) {
            throw new RuntimeException("create the extension {$extension} in the new database as the superuser first, then restore again");
        }
    }

    /**
     * The application's runtime pointed at the restored database and files for the reconciliation, the live values
     * kept to be put back.
     *
     * @return array<string, mixed>
     */
    private function switchTo(string $database, string $filesRoot): array
    {
        $live = [];

        foreach ([Scalars::text(config('database.default')), DatabaseRole::ADMINISTRATION_CONNECTION] as $connection) {
            $live["database.connections.{$connection}.database"] = config("database.connections.{$connection}.database");
            config(["database.connections.{$connection}.database" => $database]);
            DB::purge($connection);
        }

        foreach (array_keys((array) config('filesystems.disks')) as $disk) {
            if (config("filesystems.disks.{$disk}.driver") === 'local') {
                $live["filesystems.disks.{$disk}.root"] = config("filesystems.disks.{$disk}.root");
                config(["filesystems.disks.{$disk}.root" => $filesRoot.'/'.$disk]);
                Storage::forgetDisk($disk);
            }
        }

        return $live;
    }

    /** @param  array<string, mixed>  $live */
    private function switchBack(array $live): void
    {
        foreach ($live as $key => $value) {
            config([$key => $value]);
        }

        foreach ([Scalars::text(config('database.default')), DatabaseRole::ADMINISTRATION_CONNECTION] as $connection) {
            DB::purge($connection);
        }

        foreach (array_keys((array) config('filesystems.disks')) as $disk) {
            Storage::forgetDisk($disk);
        }
    }

    /** BKR-08: the installed key opens the restored TOTP ciphertext before anything else reads it. */
    private function assertPairedKey(string $entry): void
    {
        foreach (DB::table('users')->whereNotNull('two_factor_secret')->orderBy('id')->limit(20)->pluck('two_factor_secret') as $secret) {
            try {
                Crypt::decryptString(Scalars::text($secret));
            } catch (DecryptException) {
                throw new RuntimeException("the installed APP_KEY does not open the restored second-factor secrets: install the key of the register's entry {$entry} for the restored environment (BKR-08)");
            }
        }
    }

    /** @return array{file_rows: int, exports_excluded: int, missing_bytes: int, mismatched_bytes: int, orphan_files: int, missing_ids: list<int>} */
    private function reconcileFiles(string $filesRoot): array
    {
        $known = [];
        $missing = [];
        $mismatched = 0;
        $exports = 0;
        $rows = 0;

        foreach (DB::table('file_objects')->lazyById(1000) as $read) {
            $row = Scalars::row($read);

            if (($row['purpose'] ?? null) === 'EXPORT') {
                $exports++;

                continue;
            }

            $rows++;
            $name = Scalars::text($row['storage_disk'] ?? null).'/'.Scalars::text($row['storage_key'] ?? null);
            $known[$name] = true;
            $path = $filesRoot.'/'.$name;

            if (! is_file($path)) {
                $missing[] = Scalars::int($row['id'] ?? null);
            } elseif (filesize($path) !== Scalars::int($row['size_bytes'] ?? null) || hash_file('sha256', $path) !== ($row['sha256'] ?? null)) {
                $mismatched++;
            }
        }

        $orphans = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filesRoot, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $name = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen(rtrim(str_replace('\\', '/', $filesRoot), '/'))), '/');
                $orphans += isset($known[$name]) ? 0 : 1;
            }
        }

        return ['file_rows' => $rows, 'exports_excluded' => $exports, 'missing_bytes' => count($missing), 'mismatched_bytes' => $mismatched, 'orphan_files' => $orphans, 'missing_ids' => $missing];
    }

    /**
     * Each issued version without a rendition whose bytes are restored gets a new attempt, rendered here from its issued
     * payload by JB-01's own job — the version, its number and its counter untouched (GAP-007; BR-DOC-03).
     *
     * @param  list<int>  $missingFiles
     * @param  callable(int): void  $render
     * @return array{owed: int, rendered: int, failed: int}
     */
    private function renderOwed(array $missingFiles, callable $render): array
    {
        $latest = DB::table('document_renditions as r')
            ->whereRaw('r.attempt_no = (select max(x.attempt_no) from document_renditions x where x.document_version_id = r.document_version_id)')
            ->where(fn (Builder $query) => $query->where('r.state', '<>', 'READY')->orWhereIn('r.file_object_id', $missingFiles === [] ? [0] : $missingFiles))
            ->orderBy('r.document_version_id')
            ->get(['r.id', 'r.document_version_id', 'r.attempt_no', 'r.state']);
        $rendered = 0;
        $failed = 0;

        foreach ($latest as $read) {
            $attempt = Scalars::row($read);
            $next = DB::transaction(function () use ($attempt): int {
                if (($attempt['state'] ?? null) !== 'PENDING') {
                    DB::table('document_renditions')->where('id', $attempt['id'] ?? null)->update([
                        'state' => 'FAILED', 'error_summary' => 'Owed after the restore; rendered again from the issued payload (BACKUP_RECOVERY §4 step 4).', 'updated_at' => CarbonImmutable::now(),
                    ]);
                    $added = new DocumentRendition;
                    $added->forceFill(['document_version_id' => $attempt['document_version_id'] ?? null, 'attempt_no' => Scalars::int($attempt['attempt_no'] ?? null) + 1, 'state' => 'PENDING', 'updated_at' => CarbonImmutable::now()])->save();

                    return Scalars::int($added->id);
                }

                return Scalars::int($attempt['id'] ?? null);
            });

            $render($next);
            $state = DB::table('document_renditions')->where('id', $next)->value('state');
            $state === 'READY' ? $rendered++ : $failed++;
        }

        return ['owed' => count($latest), 'rendered' => $rendered, 'failed' => $failed];
    }

    /** @return array{families: int, differences: array<string, int>} */
    private function guards(): array
    {
        // Restore verification runs under the administration role (INFRASTRUCTURE §4.4), its credential given to this run.
        foreach (['username' => 'DB_ADMIN_USERNAME', 'password' => 'DB_ADMIN_PASSWORD'] as $key => $variable) {
            $given = getenv($variable);

            if (is_string($given) && $given !== '') {
                config(['database.connections.'.DatabaseRole::ADMINISTRATION_CONNECTION.".{$key}" => $given]);
            }
        }

        DB::purge(DatabaseRole::ADMINISTRATION_CONNECTION);
        $administration = DB::connection(DatabaseRole::ADMINISTRATION_CONNECTION);
        $differences = [];

        foreach (Families::all() as $family) {
            $count = count($this->reconciler->differences($administration, $family));

            if ($count > 0) {
                $differences[$family->table()] = $count;
            }
        }

        return ['families' => count(Families::all()), 'differences' => $differences];
    }
}
