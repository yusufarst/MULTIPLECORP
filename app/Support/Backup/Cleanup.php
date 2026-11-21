<?php

namespace App\Support\Backup;

use Illuminate\Filesystem\Filesystem;

/**
 * Cleanup strictly by retention (BK-12; BKR-04): run only after the newest generation verified, it removes the verified
 * generations the retention lets expire — never an unverified one, the last verified recovery point or a kept
 * pre-deployment or pre-import generation —, each manifest first, so a removal that stops halfway leaves an
 * incomplete generation, never one that looks verified. A failure stops it, reported failed, nothing else removed.
 */
final class Cleanup
{
    public function __construct(private readonly Filesystem $files) {}

    /** @return array{status: string, removed: list<string>, reason: string} */
    public function expired(string $directory): array
    {
        $generations = [];

        foreach (Generations::in($directory) as $generation) {
            $verification = Generations::verify($generation['path']);
            $generations[] = ['name' => $generation['name'], 'at' => $generation['at'], 'kind' => $verification['manifest']->kind ?? 'daily', 'verified' => $verification['status']->verified()];
        }

        $removed = [];

        foreach (RetentionRule::configured()->apply($generations)['expire'] as $name) {
            $path = $directory.'/'.$name;

            if (! $this->files->delete($path.'/'.Manifest::FILE) || ! $this->files->deleteDirectory($path)) {
                BackupLog::alert('cleanup failed', ['generation' => $name, 'removed' => $removed]);

                return ['status' => 'FAILED', 'removed' => $removed, 'reason' => "{$name} could not be removed; nothing else was"];
            }

            $removed[] = $name;
        }

        return ['status' => 'OK', 'removed' => $removed, 'reason' => $removed === [] ? 'no generation expired' : count($removed).' expired generation(s) removed'];
    }
}
