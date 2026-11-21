<?php

namespace App\Support\Backup;

use Carbon\CarbonImmutable;
use JsonException;

/**
 * A generation's manifest (BK-07; BKR-02; BKR-08): its identifier, kind, time, recovery point and commit, each part's
 * SHA-256 and size, and the key-register entry of the `APP_KEY` valid at its time — the entry's name, never a key.
 * Written last, by rename, so a manifest exists only for a run that wrote every part.
 */
final readonly class Manifest
{
    public const FILE = 'manifest.json';

    public const DUMP = 'database.dump';

    public const FILES = 'files.tar';

    public const KINDS = ['daily', 'predeploy', 'preimport', 'manual'];

    /** @param  list<array{name: string, sha256: string, bytes: int}>  $parts */
    public function __construct(
        public string $generation,
        public string $kind,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $recoveryPoint,
        public string $commit,
        public string $postgresql,
        public string $keyRegisterEntry,
        public array $parts,
        public int $fileCount,
    ) {}

    /** The manifest of a generation directory, or null when it is absent or unreadable. */
    public static function read(string $directory): ?self
    {
        $path = $directory.'/'.self::FILE;

        if (! is_file($path)) {
            return null;
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data) || ($data['format'] ?? null) !== 1 || ! is_array($data['parts'] ?? null)) {
            return null;
        }

        $parts = [];

        foreach ($data['parts'] as $part) {
            if (! is_array($part) || ! is_string($part['name'] ?? null) || ! is_string($part['sha256'] ?? null) || ! is_int($part['bytes'] ?? null)) {
                return null;
            }

            $parts[] = ['name' => $part['name'], 'sha256' => $part['sha256'], 'bytes' => $part['bytes']];
        }

        foreach (['generation', 'kind', 'created_at', 'recovery_point', 'commit', 'postgresql', 'key_register_entry'] as $field) {
            if (! is_string($data[$field] ?? null)) {
                return null;
            }
        }

        return new self($data['generation'], $data['kind'], CarbonImmutable::parse($data['created_at']), CarbonImmutable::parse($data['recovery_point']),
            $data['commit'], $data['postgresql'], $data['key_register_entry'], $parts, is_int($data['file_count'] ?? null) ? $data['file_count'] : 0);
    }

    /** Writes the manifest into its generation directory, readable by the operator account alone. */
    public function write(string $directory): void
    {
        $temporary = $directory.'/'.self::FILE.'.partial';
        file_put_contents($temporary, json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        chmod($temporary, 0600);
        rename($temporary, $directory.'/'.self::FILE);
    }

    public function bytes(): int
    {
        return array_sum(array_column($this->parts, 'bytes'));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'format' => 1,
            'generation' => $this->generation,
            'kind' => $this->kind,
            'created_at' => $this->createdAt->toIso8601ZuluString(),
            'recovery_point' => $this->recoveryPoint->toIso8601ZuluString('microsecond'),
            'commit' => $this->commit,
            'postgresql' => $this->postgresql,
            'key_register_entry' => $this->keyRegisterEntry,
            'parts' => $this->parts,
            'file_count' => $this->fileCount,
        ];
    }
}
