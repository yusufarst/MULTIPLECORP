<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Contracts\ExportFiles;
use App\Modules\DOC\Data\Download;
use App\Modules\DOC\Enums\FilePurpose;
use App\Modules\DOC\Models\FileObject;
use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * ExportFiles over the private storage (TASK-060; FL-07; FL-09): the bytes streamed under a generated key, never read
 * whole, then the `file_objects` row of purpose EXPORT, of no company and of its requester, registered in one
 * transaction with the request's own attach, so a request withheld or failed meanwhile leaves no row and no bytes.
 */
final class ExportFileStore implements ExportFiles
{
    public function __construct(private readonly FileStore $store) {}

    public function store(string $path, string $mimeType, int $requesterId, Closure $attach): bool
    {
        $key = $this->store->newKey();
        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new LogicException('An export file could not be read back.');
        }

        try {
            $this->store->writeStream($key, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        try {
            $attached = DB::transaction(function () use ($key, $path, $mimeType, $requesterId, $attach): bool {
                $file = $this->store->register($key, [
                    'purpose' => FilePurpose::Export->value,
                    'mime_type' => $mimeType,
                    'sha256' => (string) hash_file('sha256', $path),
                    'size_bytes' => (int) filesize($path),
                    'company_id' => null,
                    'uploaded_by' => $requesterId,
                    'original_filename' => null,
                    'sensitivity' => FilePurpose::Export->sensitivity(),
                ]);

                if (! $attach($file->id)) {
                    throw new ExportNotAttached;
                }

                return true;
            });
        } catch (ExportNotAttached) {
            $attached = false;
        } catch (Throwable $failure) {
            $this->store->disk()->delete($key);

            throw $failure;
        }

        if (! $attached) {
            $this->store->disk()->delete($key);
        }

        return $attached;
    }

    public function stream(int $fileId, int $requesterId): ?Download
    {
        $file = FileObject::query()->whereKey($fileId)->where('purpose', FilePurpose::Export->value)->where('uploaded_by', $requesterId)->first();

        return $file === null ? null : new Download($this->store->stream($file->storage_key, $file->storage_disk), $file->mime_type, $file->size_bytes, 'ekspor');
    }
}
