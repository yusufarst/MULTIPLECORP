<?php

namespace App\Modules\DOC\Queries;

use App\Modules\DOC\Data\FileFamilies;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Services\Renditions;
use App\Modules\IAM\Queries\ScopedRecords;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\Status\StatusRead;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The rendition group a document's page polls through ROUTE-285 (IP-12; QS-08): the latest attempt's state —
 * *PDF sedang dibuat* while PENDING or RENDERING, *PDF siap*, *PDF gagal dibuat* with its attempt and whether the
 * attempts are spent — under the document's metadata view (`projects.view` within its company; PJ-15), and the
 * rendered file's id only to its family's viewers.
 */
final class RenditionStatus implements StatusRead
{
    public function __construct(private readonly ScopedRecords $records, private readonly EffectiveCapabilities $capabilities) {}

    public function kind(): string
    {
        return 'rendisi';
    }

    public function read(Authenticatable $user, string $id): array
    {
        $viewer = $this->capabilities->for($user);
        $query = DocumentVersion::query()->where('state', '<>', 'DRAFT');

        if (! $viewer->has('projects.view')) {
            $query->whereRaw('false');
        }

        /** @var DocumentVersion $version */
        $version = $this->records->find($query, $id, $viewer);
        $latest = DocumentRendition::query()->where('document_version_id', $version->id)->orderByDesc('attempt_no')->first();
        $family = FileFamilies::document($version->document_type_code);
        $fileId = null;

        if ($latest !== null && $latest->state === 'READY' && $family !== null && $viewer->allows($family, $version->company_id)) {
            $fileId = DocumentRendition::query()->whereKey($latest->id)->join('file_objects', 'file_objects.id', '=', 'document_renditions.file_object_id')->value('file_objects.public_id');
        }

        return [
            'state' => $latest === null ? 'PENDING' : $latest->state,
            'attempt' => $latest === null ? 0 : $latest->attempt_no,
            'attempts' => Renditions::attempts(),
            'exhausted' => $latest !== null && $latest->state === 'FAILED' && $latest->attempt_no >= Renditions::attempts(),
            'file' => is_string($fileId) ? $fileId : null,
        ];
    }
}
