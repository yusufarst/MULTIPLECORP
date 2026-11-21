<?php

namespace App\Support\Backup;

use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * One backup run (BKR-01; BK-01–BK-07; BK-11): the recovery point is the custom-format dump, consistent at its one
 * snapshot, and, after it, the bytes of every file row the dump itself holds — exports left out (GT-06) —, each checked
 * against its row's size and SHA-256, so the dump never names a file the generation lacks. The parts are checked after
 * writing, the manifest written last, and the generation verified from disk before the run is reported VERIFIED and
 * cleanup may run. Anything else is FAILED or SKIPPED, alerted, and deletes nothing; a generation is never written
 * over another. Files and directories are the operator account's alone (SX-07).
 */
final class BackupRunner
{
    public function __construct(private readonly DiskProbe $disk, private readonly Cleanup $cleanup) {}

    /** @return array{status: string, generation: ?string, reason: string, disk: ?DiskPressure, cleanup: ?array{status: string, removed: list<string>, reason: string}} */
    public function run(string $kind): array
    {
        $directory = config()->string('backup.directory');
        $entry = config('backup.key_register_entry');

        if (! in_array($kind, Manifest::KINDS, true)) {
            return $this->failed(null, 'the kind is one of '.implode(', ', Manifest::KINDS));
        }

        if (! is_string($entry) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/', $entry) !== 1 || str_starts_with($entry, 'base64')) {
            return $this->failed(null, 'name the key-register entry of the current APP_KEY (BKR-08) — its name, never the key');
        }

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            return $this->failed(null, 'the backup directory cannot be created');
        }

        try {
            $login = PostgresTools::login();
            PostgresTools::assertVersion($login, config()->string('backup.pg_dump'));
            $space = $this->disk->measure($directory);
            $pressure = DiskPressure::assess($space['total'], $space['free'], config()->integer('backup.disk.warn_pct'), config()->integer('backup.disk.critical_pct'), $this->estimate($directory, $login));
        } catch (Throwable $failure) {
            return $this->failed(null, $failure->getMessage());
        }

        if ($pressure->level !== DiskPressure::OK) {
            BackupLog::alert("the backup disk is at its {$pressure->level} level", ['used_pct' => $pressure->usedPct]);
        }

        if (! $pressure->mayStart) {
            BackupLog::alert('the backup run was skipped: no space for a new generation beside the retained ones', ['used_pct' => $pressure->usedPct, 'free_bytes' => $pressure->freeBytes, 'needed_bytes' => $pressure->neededBytes]);

            return ['status' => 'SKIPPED', 'generation' => null, 'reason' => "the disk is at its {$pressure->level} level and a new generation does not fit; nothing was deleted", 'disk' => $pressure, 'cleanup' => null];
        }

        $now = CarbonImmutable::now();
        $name = Generations::name($now, config()->string('backup.commit'));
        $path = $directory.'/'.$name;
        $umask = umask(0077);

        try {
            if (file_exists($path) || ! mkdir($path, 0700)) {
                throw new RuntimeException("the generation {$name} exists already; a run never writes over one");
            }

            $manifest = $this->write($login, $path, $name, $kind, $entry, $now);
            $verification = Generations::verify($path);

            if (! $verification['status']->verified()) {
                throw new RuntimeException('the written generation does not verify: '.$verification['reason']);
            }
        } catch (Throwable $failure) {
            return $this->failed($name, $failure->getMessage(), $pressure);
        } finally {
            umask($umask);
        }

        BackupLog::outcome('generation verified', ['generation' => $name, 'kind' => $kind, 'bytes' => $manifest->bytes(), 'files' => $manifest->fileCount]);
        $cleanup = $this->cleanup->expired($directory);

        return ['status' => 'VERIFIED', 'generation' => $name, 'reason' => 'every part written and every checksum verified', 'disk' => $pressure, 'cleanup' => $cleanup];
    }

    private function write(Connection $login, string $path, string $name, string $kind, string $entry, CarbonImmutable $now): Manifest
    {
        $postgresql = Scalars::text($login->scalar('show server_version'));
        $dump = $path.'/'.Manifest::DUMP;
        PostgresTools::run($login, config()->string('backup.pg_dump'), ['--format=custom', '--file='.$dump]);
        $created = PostgresTools::createdAt($dump);
        $recoveryPoint = $created === null ? $now : CarbonImmutable::parse($created);
        $entries = [];

        // The file rows the dump itself holds, at its own snapshot: each one's bytes, read after that snapshot.
        foreach (PostgresTools::rows($dump, 'file_objects', ['storage_disk', 'storage_key', 'sha256', 'size_bytes', 'purpose']) as $row) {
            if ($row['purpose'] === 'EXPORT') {
                continue;
            }

            $disk = (string) $row['storage_disk'];
            $key = (string) $row['storage_key'];
            $source = Storage::disk($disk)->path($key);

            if (! is_file($source) || filesize($source) !== Scalars::int($row['size_bytes']) || hash_file('sha256', $source) !== $row['sha256']) {
                throw new RuntimeException("the file row {$disk}/{$key} has no matching bytes, so the recovery point would not be coherent (BKR-01)");
            }

            $entries[] = ['name' => $disk.'/'.$key, 'path' => $source];
        }

        TarArchive::write($path.'/'.Manifest::FILES, $entries);
        $parts = [];

        foreach ([Manifest::DUMP, Manifest::FILES] as $part) {
            chmod($path.'/'.$part, 0600);
            $parts[] = ['name' => $part, 'sha256' => (string) hash_file('sha256', $path.'/'.$part), 'bytes' => (int) filesize($path.'/'.$part)];
        }

        $manifest = new Manifest($name, $kind, $now, $recoveryPoint, config()->string('backup.commit'), $postgresql, $entry, $parts, count($entries));
        $manifest->write($path);

        return $manifest;
    }

    /** The space a new generation needs: the newest verified one's, or the database's and the files' size. */
    private function estimate(string $directory, Connection $login): int
    {
        foreach (Generations::in($directory) as $generation) {
            $verification = Generations::verify($generation['path'], checksums: false);

            if ($verification['status']->verified() && $verification['manifest'] !== null) {
                return $verification['manifest']->bytes();
            }
        }

        return Scalars::int($login->scalar("select pg_database_size(current_database()) + coalesce((select sum(size_bytes) from file_objects where purpose <> 'EXPORT'), 0)"));
    }

    /** @return array{status: string, generation: ?string, reason: string, disk: ?DiskPressure, cleanup: null} */
    private function failed(?string $generation, string $reason, ?DiskPressure $pressure = null): array
    {
        BackupLog::alert('the backup run failed', ['generation' => $generation, 'reason' => $reason]);

        return ['status' => 'FAILED', 'generation' => $generation, 'reason' => $reason.'; nothing was deleted', 'disk' => $pressure, 'cleanup' => null];
    }
}
