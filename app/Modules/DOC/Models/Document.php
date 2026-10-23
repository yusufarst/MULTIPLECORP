<?php

namespace App\Modules\DOC\Models;

use App\Modules\ORG\Models\Company;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One chain of versions of a document on one record-bound subject (DATABASE §4.10 `documents`, SR; §10):
 * a layout of a business record, never a second transaction (BR-DOC-04). The subjects other than the
 * parent document are the records of later modules, related by their own models.
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property string $document_type_code
 * @property string $mode
 * @property int|null $project_id
 * @property int|null $purchase_id
 * @property int|null $dispatch_movement_id
 * @property int|null $dropship_confirmation_id
 * @property int|null $invoice_id
 * @property int|null $payment_id
 * @property int|null $parent_document_id
 * @property int|null $delivery_id
 * @property int|null $service_handover_id
 * @property int|null $receiving_movement_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(DocumentFactory::class)]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<DocumentType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_code', 'code');
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'parent_document_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'project_id' => 'integer',
            'purchase_id' => 'integer',
            'dispatch_movement_id' => 'integer',
            'dropship_confirmation_id' => 'integer',
            'invoice_id' => 'integer',
            'payment_id' => 'integer',
            'parent_document_id' => 'integer',
            'delivery_id' => 'integer',
            'service_handover_id' => 'integer',
            'receiving_movement_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
