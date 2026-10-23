<?php

namespace App\Modules\DOC\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentRenditionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An attempt to render an issued version (DATABASE §4.10 `document_renditions`, OP; §10): rendering never
 * reissues or renumbers — a retry is a new attempt (BR-DOC-03; C-12).
 *
 * @property int $id
 * @property int $document_version_id
 * @property int $attempt_no
 * @property string $state
 * @property int|null $file_object_id
 * @property string|null $renderer_version
 * @property string|null $error_summary
 * @property CarbonImmutable $recorded_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(DocumentRenditionFactory::class)]
class DocumentRendition extends Model
{
    /** @use HasFactory<DocumentRenditionFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<DocumentVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    /** @return BelongsTo<FileObject, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_object_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'document_version_id' => 'integer',
            'attempt_no' => 'integer',
            'file_object_id' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
