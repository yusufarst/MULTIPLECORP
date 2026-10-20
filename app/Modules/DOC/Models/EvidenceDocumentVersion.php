<?php

namespace App\Modules\DOC\Models;

use App\Modules\IAM\Models\User;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\EvidenceDocumentVersionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A version of an evidence over an immutable file (DATABASE §4.10 `evidence_document_versions`, AF): a
 * replacement keeps the history (L-41).
 *
 * @property int $id
 * @property int $evidence_document_id
 * @property int $version_no
 * @property int $file_object_id
 * @property string|null $reason
 * @property int $uploaded_by
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(EvidenceDocumentVersionFactory::class)]
class EvidenceDocumentVersion extends Model
{
    use AppendOnly;

    /** @use HasFactory<EvidenceDocumentVersionFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<EvidenceDocument, $this> */
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(EvidenceDocument::class, 'evidence_document_id');
    }

    /** @return BelongsTo<FileObject, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_object_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'evidence_document_id' => 'integer',
            'version_no' => 'integer',
            'file_object_id' => 'integer',
            'uploaded_by' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
