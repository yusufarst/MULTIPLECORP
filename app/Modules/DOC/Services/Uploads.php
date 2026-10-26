<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Enums\FilePurpose;
use App\Modules\DOC\Models\FileObject;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The upload pipeline of ARCHITECTURE §12 and SECURITY FL-01, outside every business transaction: the file,
 * already streamed to temporary storage by the request, is inspected (FL-02–FL-06), hashed, stored under a
 * generated key and registered in its own short transaction; the business command then references the file id.
 * The calling action then owes an image's derivatives to JB-04 (`ProcessImage::owedFor`), and a derivative still
 * missing is made on demand by its thumbnail request. A refusal stores nothing and is written as an
 * UPLOAD_REJECTED security event (LG-02). The route, its limiter and the purpose's capability are the calling
 * command's (ROUTE-228, TASK-030).
 */
final class Uploads
{
    public function __construct(
        private readonly UploadInspection $inspection,
        private readonly FileStore $store,
        private readonly SecurityEventRecorder $events,
    ) {}

    public function accept(string $path, string $clientName, FilePurpose $purpose, ?int $companyId, int $uploaderId): FileObject
    {
        try {
            $inspected = $this->inspection->inspect($path, $clientName, $purpose);
        } catch (UploadRefused $refused) {
            $this->events->record(SecurityEventKind::UploadRejected, userId: $uploaderId, reasonCode: strtoupper($refused->reason), details: ['purpose' => $purpose->value]);

            throw $refused;
        }

        $bytes = $inspected->reencoded ?? (string) file_get_contents($path);
        $key = $this->store->newKey();
        $this->store->write($key, $bytes);

        try {
            $file = $this->store->register($key, [
                'purpose' => $purpose->value,
                'mime_type' => $inspected->type->value,
                'sha256' => hash('sha256', $bytes),
                'size_bytes' => strlen($bytes),
                'company_id' => $companyId,
                'uploaded_by' => $uploaderId,
                'original_filename' => FileStore::displayName($clientName),
                'sensitivity' => $purpose->sensitivity(),
            ]);
        } catch (Throwable $failure) {
            // The row was not written, so nothing references the bytes: they go with it.
            $this->store->disk()->delete($key);

            throw $failure;
        }

        Log::info('A file was uploaded.', ['file' => $file->public_id, 'purpose' => $purpose->value, 'type' => $inspected->type->value]);

        return $file;
    }
}
