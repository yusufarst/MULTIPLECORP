<?php

namespace App\Modules\FIN\Models;

use App\Modules\ORG\Models\TaxTreatmentRule;
use App\Modules\ORG\Models\TaxType;
use Carbon\CarbonImmutable;
use Database\Factories\FIN\InvoiceTaxComponentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An output-tax component of an invoice version, never revenue (DATABASE §4.12 `invoice_tax_components`,
 * SN once issued, draft-editable; D-1).
 *
 * @property int $id
 * @property int $invoice_version_id
 * @property int $tax_type_id
 * @property int $treatment_rule_id
 * @property string $treatment
 * @property string $rate_percent
 * @property string $base_amount
 * @property string $amount
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(InvoiceTaxComponentFactory::class)]
class InvoiceTaxComponent extends Model
{
    /** @use HasFactory<InvoiceTaxComponentFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<InvoiceVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(InvoiceVersion::class, 'invoice_version_id');
    }

    /** @return BelongsTo<TaxType, $this> */
    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class);
    }

    /** @return BelongsTo<TaxTreatmentRule, $this> */
    public function treatmentRule(): BelongsTo
    {
        return $this->belongsTo(TaxTreatmentRule::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'invoice_version_id' => 'integer',
            'tax_type_id' => 'integer',
            'treatment_rule_id' => 'integer',
            'rate_percent' => 'decimal:6',
            'base_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
