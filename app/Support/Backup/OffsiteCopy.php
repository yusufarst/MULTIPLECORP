<?php

namespace App\Support\Backup;

use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * The off-server copy (BKR-09; INF-06; DIR-083 OE-09; architecture challenge 13a–13c): a verified generation only,
 * each of its files encrypted to the copy's public key into `<destination>/<generation>.partial/`, the encrypted
 * files' checksums read back at the destination, then the directory renamed — a copy counts only once it verifies
 * there. The destination OPI-32 names is a device or storage mounted by role; the transfer is this one step. The
 * copies keep the generations' retention; a failure is alerted and deletes no retained generation.
 */
final class OffsiteCopy
{
    public const RECORD = 'copy.json';

    public function __construct(private readonly Filesystem $files) {}

    /** @return array{status: string, generation: ?string, reason: string} */
    public function copy(string $backupDirectory, ?string $generation = null): array
    {
        $destination = config('backup.copy.destination');
        $publicKey = config('backup.copy.public_key');

        if (! is_string($destination) || $destination === '' || ! is_dir($destination) || ! is_writable($destination)) {
            return $this->failed($generation, 'the copy\'s destination is not mounted or not writable');
        }

        if (! is_string($publicKey) || $publicKey === '') {
            return $this->failed($generation, 'the copy\'s public key is not configured');
        }

        $generation ??= $this->newestVerifiedDaily($backupDirectory);

        if ($generation === null) {
            return $this->failed(null, 'no verified daily generation to copy');
        }

        $source = $backupDirectory.'/'.$generation;
        $verification = Generations::verify($source);

        if (! $verification['status']->verified()) {
            return $this->failed($generation, "only a verified generation is copied (13a): {$verification['status']->value}, {$verification['reason']}");
        }

        $target = $destination.'/'.$generation;

        if (is_dir($target)) {
            $existing = self::verifyCopy($target);

            return $existing === null ? ['status' => 'VERIFIED', 'generation' => $generation, 'reason' => 'already copied and verified at the destination']
                : $this->failed($generation, "a copy exists at the destination and does not verify: {$existing}");
        }

        $partial = $target.'.partial';

        try {
            if (is_dir($partial)) {
                $this->files->deleteDirectory($partial);
            }

            if (! mkdir($partial, 0700)) {
                throw new RuntimeException('the copy\'s directory cannot be created at the destination');
            }

            $record = ['format' => 1, 'generation' => $generation, 'created_at' => $verification['manifest']?->createdAt->toIso8601ZuluString(), 'copied_at' => CarbonImmutable::now()->toIso8601ZuluString(), 'files' => []];

            foreach ([Manifest::FILE, Manifest::DUMP, Manifest::FILES] as $name) {
                $hash = SealedStream::encrypt($source.'/'.$name, $partial.'/'.$name.'.enc', $publicKey);
                $record['files'][] = ['name' => $name.'.enc', 'sha256' => $hash, 'bytes' => (int) filesize($partial.'/'.$name.'.enc')];
            }

            file_put_contents($partial.'/'.self::RECORD, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            $problem = self::verifyCopy($partial);

            if ($problem !== null) {
                throw new RuntimeException("the copy does not verify at the destination: {$problem}");
            }

            if (! rename($partial, $target)) {
                throw new RuntimeException('the verified copy cannot be put in place at the destination');
            }
        } catch (Throwable $failure) {
            if (is_dir($partial)) {
                $this->files->deleteDirectory($partial);
            }

            return $this->failed($generation, $failure->getMessage());
        }

        BackupLog::outcome('off-server copy verified', ['generation' => $generation]);
        $this->expire($destination);

        return ['status' => 'VERIFIED', 'generation' => $generation, 'reason' => 'encrypted, copied and verified at the destination'];
    }

    /** Null when every encrypted file of a copy matches its record, or what does not. */
    public static function verifyCopy(string $copy): ?string
    {
        try {
            $record = json_decode((string) @file_get_contents($copy.'/'.self::RECORD), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'its record is missing or unreadable';
        }

        if (! is_array($record) || ! is_array($record['files'] ?? null) || count($record['files']) !== 3) {
            return 'its record does not list the manifest, the dump and the files';
        }

        foreach ($record['files'] as $file) {
            $name = is_array($file) && is_string($file['name'] ?? null) ? $file['name'] : '';
            $path = $copy.'/'.$name;

            if ($name === '' || ! is_file($path) || filesize($path) !== ($file['bytes'] ?? null) || hash_file('sha256', $path) !== ($file['sha256'] ?? null)) {
                return "{$name} is missing or fails its checksum";
            }
        }

        return null;
    }

    /**
     * Brings a copy back from its destination (BACKUP_RECOVERY §5's copy drill): its checksums verified, each file
     * decrypted with the secret key the operator gives this run — off the VPS — into a generation directory, which is
     * then verified like any other.
     *
     * @return array{status: GenerationStatus, reason: string, generation: string}
     */
    public static function fetch(string $copy, string $secretKey, string $into): array
    {
        $problem = self::verifyCopy($copy);
        $generation = basename($copy);

        if ($problem !== null || preg_match(Generations::NAME, $generation) !== 1) {
            return ['status' => GenerationStatus::Corrupt, 'reason' => $problem ?? 'the copy is not named by a generation', 'generation' => $generation];
        }

        $target = $into.'/'.$generation;

        if (file_exists($target) || ! mkdir($target, 0700, true)) {
            return ['status' => GenerationStatus::Corrupt, 'reason' => 'the generation exists already where the copy is brought back', 'generation' => $generation];
        }

        try {
            foreach ([Manifest::FILE, Manifest::DUMP, Manifest::FILES] as $name) {
                SealedStream::decrypt($copy.'/'.$name.'.enc', $target.'/'.$name, $secretKey);
            }
        } catch (Throwable $failure) {
            return ['status' => GenerationStatus::Corrupt, 'reason' => $failure->getMessage(), 'generation' => $generation];
        }

        $verification = Generations::verify($target);

        return ['status' => $verification['status'], 'reason' => $verification['reason'], 'generation' => $generation];
    }

    private function newestVerifiedDaily(string $backupDirectory): ?string
    {
        foreach (Generations::in($backupDirectory) as $generation) {
            $verification = Generations::verify($generation['path']);

            if ($verification['status']->verified() && $verification['manifest']?->kind === 'daily') {
                return $generation['name'];
            }
        }

        return null;
    }

    /** The copies keep the generations' retention, judged by their own verification at the destination. */
    private function expire(string $destination): void
    {
        $copies = [];

        foreach (Generations::in($destination) as $copy) {
            $copies[] = ['name' => $copy['name'], 'at' => $copy['at'], 'kind' => 'daily', 'verified' => self::verifyCopy($copy['path']) === null];
        }

        foreach (RetentionRule::configured()->apply($copies)['expire'] as $name) {
            if (! $this->files->delete($destination.'/'.$name.'/'.self::RECORD) || ! $this->files->deleteDirectory($destination.'/'.$name)) {
                BackupLog::alert('the off-server copies\' cleanup failed', ['copy' => $name]);

                return;
            }
        }
    }

    /** @return array{status: string, generation: ?string, reason: string} */
    private function failed(?string $generation, string $reason): array
    {
        BackupLog::alert('the off-server copy failed', ['generation' => $generation, 'reason' => $reason]);

        return ['status' => 'FAILED', 'generation' => $generation, 'reason' => $reason.'; no retained generation was deleted'];
    }
}
