<?php

namespace App\Modules\DOC\Models;

use App\Support\Database\CodeList;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A type of the bounded catalogue of fourteen documents, keyed by its code (DATABASE §4.10
 * `document_types`, CF; CAP-08).
 *
 * @property string $code
 * @property string $name
 * @property string $subject_kind
 * @property bool $is_numbered
 * @property list<string> $allowed_modes
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(DocumentTypeFactory::class)]
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'document_type_code', 'code');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_numbered' => 'boolean',
            'allowed_modes' => CodeList::class,
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
