<?php

namespace App\Modules\FIN\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FIN\InvoiceVersionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A version of an invoice, immutable once issued (DATABASE §4.12 `invoice_versions`, SR; BR-CR-06): sales
 * value excluding output tax, the client as used, a downward revision's typed basis (CM-24; C-56).
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $company_id
 * @property int $version_no
 * @property string $state
 * @property string $kind
 * @property CarbonImmutable $business_date
 * @property string $sales_value
 * @property string $output_tax_value
 * @property string $gross_value
 * @property array<string, mixed> $client_snapshot
 * @property string|null $revision_reason
 * @property string $revision_basis
 * @property int|null $basis_correction_case_id
 * @property int|null $basis_confirmation_id
 * @property int|null $basis_cancellation_id
 * @property int|null $basis_project_transition_id
 * @property int|null $supersedes_version_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(InvoiceVersionFactory::class)]
class InvoiceVersion extends Model
{
    /** @use HasFactory<InvoiceVersionFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<InvoiceVersion, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(InvoiceVersion::class, 'supersedes_version_id');
    }

    /** @return HasMany<InvoiceVersionLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceVersionLine::class);
    }

    /** @return HasMany<InvoiceTaxComponent, $this> */
    public function taxComponents(): HasMany
    {
        return $this->hasMany(InvoiceTaxComponent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'company_id' => 'integer',
            'version_no' => 'integer',
            'business_date' => 'immutable_date',
            'sales_value' => 'decimal:2',
            'output_tax_value' => 'decimal:2',
            'gross_value' => 'decimal:2',
            'client_snapshot' => 'array',
            'basis_correction_case_id' => 'integer',
            'basis_confirmation_id' => 'integer',
            'basis_cancellation_id' => 'integer',
            'basis_project_transition_id' => 'integer',
            'supersedes_version_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
