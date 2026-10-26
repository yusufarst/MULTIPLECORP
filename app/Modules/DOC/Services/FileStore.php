<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Models\FileObject;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

/**
 * The private storage of ARCHITECTURE §12 and DATABASE §22 behind the framework's filesystem abstraction: bytes
 * under a generated key — no request value ever reaches a path, so traversal is impossible by construction
 * (FL-07) —, never overwritten, and a `file_objects` row in its own short transaction. A derivative of JB-04
 * lives beside its original under a deterministic key.
 */
final class FileStore
{
    /** The configured private disk, or the disk a stored file's row names (ER-06). */
    public function disk(?string $name = null): Filesystem
    {
        return Storage::disk($name ?? $this->diskName());
    }

    public function diskName(): string
    {
        $disk = config('files.disk');

        return is_string($disk) ? $disk : 'files';
    }

    /** A fresh key: a date folder and a UUIDv7, never the uploaded name. */
    public function newKey(): string
    {
        return now()->format('Y/m').'/'.Str::uuid7()->toString();
    }

    public function derivativeKey(FileObject $file, string $variant): string
    {
        return "{$file->storage_key}.{$variant}.webp";
    }

    /** Writes bytes under a key that holds none yet: bytes are immutable (DATABASE §22). */
    public function write(string $key, string $bytes): void
    {
        if ($this->disk()->exists($key)) {
            throw new LogicException('Stored bytes are never overwritten (DATABASE §22).');
        }

        $this->disk()->put($key, $bytes);
    }

    /** Writes a derivative only when it is absent, so a re-run writes nothing that exists (JB-04). */
    public function writeOnce(string $key, string $bytes, ?string $disk = null): bool
    {
        if ($this->disk($disk)->exists($key)) {
            return false;
        }

        $this->disk($disk)->put($key, $bytes);

        return true;
    }

    /**
     * The metadata row, in its own short transaction (FL-01).
     *
     * @param  array{purpose: string, mime_type: string, sha256: string, size_bytes: int, company_id: ?int, uploaded_by: ?int, original_filename: ?string, sensitivity: string}  $attributes
     */
    public function register(string $key, array $attributes): FileObject
    {
        return DB::transaction(fn (): FileObject => FileObject::query()->forceCreate([
            ...$attributes,
            'storage_disk' => $this->diskName(),
            'storage_key' => $key,
        ]));
    }

    /** @return resource */
    public function stream(string $key, ?string $disk = null)
    {
        return $this->disk($disk)->readStream($key) ?? throw new LogicException('A stored file is missing from its disk.');
    }

    /** The sanitized original name, display metadata only: no control characters, quotes or path separators. */
    public static function displayName(string $name): ?string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F"\'\/\\\\]+/u', '', $name);
        $name = trim(mb_substr($name, 0, 200));

        return $name === '' ? null : $name;
    }
}
