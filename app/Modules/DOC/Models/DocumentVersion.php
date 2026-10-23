<?php

namespace App\Modules\DOC\Models;

use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\CompanyBankAccount;
use App\Modules\ORG\Models\NumberingScheme;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentVersionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A version of a document (DATABASE §4.10 `document_versions`, SR, immutable once issued; §10): a draft is
 * edited or deleted; an issued version keeps its number, payload and issue data and changes only by its
 * supersession or its void (BR-DOC-01/02; C-11). The DOC-05 amount is typed, never read from the payload.
 *
 * @property int $id
 * @property string $public_id
 * @property int $document_id
 * @property int $company_id
 * @property string $document_type_code
 * @property int $version_no
 * @property string $state
 * @property CarbonImmutable|null $business_date
 * @property int|null $numbering_scheme_id
 * @property string|null $period_key
 * @property int|null $sequence_no
 * @property int $revision_suffix
 * @property string|null $formatted_number
 * @property int|null $quotation_revision_id
 * @property int|null $invoice_version_id
 * @property string|null $kuitansi_amount
 * @property int|null $company_bank_account_id
 * @property array<string, mixed>|null $payload
 * @property string|null $payload_sha256
 * @property int|null $issued_by
 * @property string|null $issue_command_id
 * @property int|null $supersedes_version_id
 * @property int|null $voided_by
 * @property CarbonImmutable|null $voided_at
 * @property string|null $voided_reason
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(DocumentVersionFactory::class)]
class DocumentVersion extends Model
{
    /** @use HasFactory<DocumentVersionFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return HasMany<DocumentRendition, $this> */
    public function renditions(): HasMany
    {
        return $this->hasMany(DocumentRendition::class);
    }

    /** @return BelongsTo<NumberingScheme, $this> */
    public function numberingScheme(): BelongsTo
    {
        return $this->belongsTo(NumberingScheme::class);
    }

    /** @return BelongsTo<CompanyBankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class, 'company_bank_account_id');
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'supersedes_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return BelongsTo<User, $this> */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'document_id' => 'integer',
            'company_id' => 'integer',
            'version_no' => 'integer',
            'business_date' => 'immutable_date',
            'numbering_scheme_id' => 'integer',
            'sequence_no' => 'integer',
            'revision_suffix' => 'integer',
            'quotation_revision_id' => 'integer',
            'invoice_version_id' => 'integer',
            'kuitansi_amount' => 'decimal:2',
            'company_bank_account_id' => 'integer',
            'payload' => 'array',
            'issued_by' => 'integer',
            'supersedes_version_id' => 'integer',
            'voided_by' => 'integer',
            'voided_at' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
