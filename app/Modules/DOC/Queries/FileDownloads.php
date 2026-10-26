<?php

namespace App\Modules\DOC\Queries;

use App\Modules\DOC\Data\Download;
use App\Modules\DOC\Enums\DetectedType;
use App\Modules\DOC\Jobs\ProcessImage;
use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Services\FileStore;
use App\Modules\IAM\Services\EffectiveCapabilities;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The reads behind `DOC DownloadFile` and `DOC ShowThumbnail` (ROUTE-226, ROUTE-227): a file opened only after its
 * owning record authorizes the viewer at request time (DP-05; {@see FileAccess}), and an image's derivative — a
 * thumbnail for a list (PF-31) or the re-encoded preview of an evidence photo (FL-03) —, one still missing owed to
 * JB-04 and answered as absent, so the page shows PT-22's placeholder.
 */
final class FileDownloads
{
    public function __construct(
        private readonly FileAccess $access,
        private readonly FileStore $store,
        private readonly EffectiveCapabilities $capabilities,
    ) {}

    public function download(Authenticatable $viewer, string $id): Download
    {
        $file = $this->access->find($this->capabilities->for($viewer), $id);

        return new Download($this->store->stream($file->storage_key), $file->mime_type, $file->size_bytes, $this->name($file));
    }

    public function derivative(Authenticatable $viewer, string $id, string $variant): Download
    {
        $file = $this->access->find($this->capabilities->for($viewer), $id);

        if (DetectedType::tryFrom($file->mime_type)?->isImage() !== true) {
            throw new NotFoundHttpException;
        }

        $key = $this->store->derivativeKey($file, $variant);

        if (! $this->store->disk()->exists($key)) {
            ProcessImage::dispatch($file->id, $variant);

            throw new NotFoundHttpException;
        }

        return new Download($this->store->stream($key), DetectedType::Webp->value, $this->store->disk()->size($key), "{$variant}.webp");
    }

    /** The download's name: the sanitized original, or for a rendition its document's number. */
    private function name(FileObject $file): string
    {
        $type = DetectedType::tryFrom($file->mime_type);
        $extension = $type === null ? 'bin' : $type->extensions()[0];

        if ($file->original_filename !== null) {
            return $file->original_filename;
        }

        $number = DB::table('document_renditions as r')->join('document_versions as v', 'v.id', '=', 'r.document_version_id')
            ->where('r.file_object_id', $file->id)->value('v.formatted_number');

        return (is_string($number) ? str_replace(['/', '\\'], '-', $number) : 'berkas').'.'.$extension;
    }
}
