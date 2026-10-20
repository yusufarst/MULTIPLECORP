<?php

namespace App\Modules\DOC\Models;

use App\Modules\ORG\Models\Company;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An authentic external original, never generated (DATABASE §4.10 `evidence_documents`, SR; FS-12): its
 * identity per company versions it rather than duplicating it (L-45), and pool evidence of shared stock
 * belongs to no company (DIR-027; C-58).
 *
 * @property int $id
 * @property string $public_id
 * @property string $evidence_scope
 * @property int|null $company_id
 * @property string $evidence_type
 * @property string $declared_issuer
 * @property string|null $issuer_reference
 * @property CarbonImmutable $document_date
 * @property string $identity_key
 * @property string|null $proven_amount
 * @property string|null $deliverer
 * @property CarbonImmutable|null $received_date
 * @property int|null $current_version_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(EvidenceDocumentFactory::class)]
class EvidenceDocument extends Model
{
    /** @use HasFactory<EvidenceDocumentFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

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

    /** @return HasMany<EvidenceDocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(EvidenceDocumentVersion::class);
    }

    /** @return BelongsTo<EvidenceDocumentVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(EvidenceDocumentVersion::class, 'current_version_id');
    }

    /** @return HasOne<EvidenceBalance, $this> */
    public function balance(): HasOne
    {
        return $this->hasOne(EvidenceBalance::class);
    }

    /** @return HasMany<EvidenceLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(EvidenceLink::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'document_date' => 'immutable_date',
            'proven_amount' => 'decimal:2',
            'received_date' => 'immutable_date',
            'current_version_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
