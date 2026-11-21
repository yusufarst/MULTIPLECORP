<?php

namespace App\Support\Backup;

use Carbon\CarbonImmutable;

/**
 * The generations of a backup directory and their verification (BK-05; BK-07; BKR-02). A generation is a directory
 * `<timestamp>-<commit>/` holding the dump, the files' archive and the manifest; its status is read from the bytes
 * on disk each time, never remembered.
 */
final class Generations
{
    public const NAME = '/^(\d{8}T\d{6}Z)-([0-9a-z]{1,40})$/';

    /** @return array{status: GenerationStatus, reason: string, manifest: ?Manifest} */
    public static function verify(string $directory, bool $checksums = true): array
    {
        if (! is_dir($directory)) {
            return ['status' => GenerationStatus::Missing, 'reason' => 'the generation directory does not exist', 'manifest' => null];
        }

        $manifest = Manifest::read($directory);

        if ($manifest === null) {
            return is_file($directory.'/'.Manifest::FILE)
                ? ['status' => GenerationStatus::Corrupt, 'reason' => 'the manifest is unreadable', 'manifest' => null]
                : ['status' => GenerationStatus::Incomplete, 'reason' => 'the run did not complete: no manifest', 'manifest' => null];
        }

        if ($manifest->generation !== basename($directory)) {
            return ['status' => GenerationStatus::Corrupt, 'reason' => 'the manifest names another generation', 'manifest' => $manifest];
        }

        $names = array_column($manifest->parts, 'name');
        sort($names);

        if ($names !== [Manifest::DUMP, Manifest::FILES]) {
            return ['status' => GenerationStatus::Corrupt, 'reason' => 'the manifest does not list the dump and the files', 'manifest' => $manifest];
        }

        foreach ($manifest->parts as $part) {
            $path = $directory.'/'.$part['name'];

            if (! is_file($path)) {
                return ['status' => GenerationStatus::Corrupt, 'reason' => "{$part['name']} is missing", 'manifest' => $manifest];
            }

            if (filesize($path) !== $part['bytes']) {
                return ['status' => GenerationStatus::Corrupt, 'reason' => "{$part['name']} has another size", 'manifest' => $manifest];
            }

            if ($checksums && hash_file('sha256', $path) !== $part['sha256']) {
                return ['status' => GenerationStatus::Corrupt, 'reason' => "{$part['name']} fails its checksum", 'manifest' => $manifest];
            }
        }

        return ['status' => GenerationStatus::Verified, 'reason' => $checksums ? 'every checksum verifies' : 'every part has its size', 'manifest' => $manifest];
    }

    /**
     * The generations of a backup directory, newest first, each with its time read from its name.
     *
     * @return list<array{name: string, path: string, at: CarbonImmutable}>
     */
    public static function in(string $backupDirectory): array
    {
        $found = [];

        foreach (is_dir($backupDirectory) ? (scandir($backupDirectory) ?: []) : [] as $name) {
            if (preg_match(self::NAME, $name, $match) === 1 && is_dir($backupDirectory.'/'.$name)) {
                $at = CarbonImmutable::createFromFormat('Ymd\THis\Z', $match[1], 'UTC');

                if ($at instanceof CarbonImmutable) {
                    $found[] = ['name' => $name, 'path' => $backupDirectory.'/'.$name, 'at' => $at];
                }
            }
        }

        usort($found, fn (array $a, array $b): int => strcmp($b['name'], $a['name']));

        return $found;
    }

    /** A new generation's name: its UTC time and the deployed commit's short form. */
    public static function name(CarbonImmutable $at, string $commit): string
    {
        $short = strtolower(substr(preg_replace('/[^0-9a-zA-Z]/', '', $commit) ?: 'unknown', 0, 12));

        return $at->utc()->format('Ymd\THis\Z').'-'.$short;
    }
}
