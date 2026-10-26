<?php

namespace App\Modules\DOC\Jobs;

use App\Modules\DOC\Enums\DetectedType;
use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Services\FileStore;
use App\Modules\DOC\Services\ImageCodec;
use App\Support\Jobs\PlatformJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;

/**
 * JB-04 (CONCURRENCY_IDEMPOTENCY §15; ARCHITECTURE §11): one metadata-free WebP derivative of an uploaded
 * image — the list thumbnail of PF-31 or an evidence photo's preview (FL-03) — at its deterministic key beside the
 * immutable original, written only when absent, so a re-run writes nothing that exists. Keyed by the file and
 * the variant; three attempts; a missing derivative is asked for again by its thumbnail request.
 */
final class ProcessImage extends PlatformJob implements ShouldBeUnique
{
    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $fileId, public readonly string $variant)
    {
        parent::__construct();
    }

    /** Owes every derivative of an uploaded image, each dispatched once its row commits. */
    public static function owedFor(FileObject $file): void
    {
        if (DetectedType::tryFrom($file->mime_type)?->isImage() === true) {
            foreach (array_keys(ImageCodec::VARIANTS) as $variant) {
                self::dispatch($file->id, $variant);
            }
        }
    }

    public function uniqueId(): string
    {
        return "{$this->fileId}:{$this->variant}";
    }

    public function handle(FileStore $store, ImageCodec $codec): void
    {
        $file = FileObject::query()->find($this->fileId);
        $type = $file === null ? null : DetectedType::tryFrom($file->mime_type);

        if ($file === null || $type === null || ! $type->isImage() || ! array_key_exists($this->variant, ImageCodec::VARIANTS)) {
            return;
        }

        $key = $store->derivativeKey($file, $this->variant);

        if ($store->disk($file->storage_disk)->exists($key)) {
            return;
        }

        // The decoder reads a local copy, so no stream wrapper or remote path ever reaches it.
        $local = tempnam(sys_get_temp_dir(), 'img-');

        try {
            file_put_contents($local, $store->stream($file->storage_key, $file->storage_disk));
            $store->writeOnce($key, $codec->derive($local, $type, $this->variant), $file->storage_disk);
        } finally {
            @unlink($local);
        }

        Log::info('An image derivative was written.', ['file' => $file->public_id, 'variant' => $this->variant]);
    }
}
