<?php

namespace App\Modules\PUR\Models;

use App\Modules\ORG\Models\TaxTreatmentRule;
use App\Modules\ORG\Models\TaxType;
use Carbon\CarbonImmutable;
use Database\Factories\PUR\PurchaseLineTaxComponentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tax component of a purchase line as committed (DATABASE §4.6 `purchase_line_tax_components`, SN,
 * draft-editable with its line): the D-1 treatment recorded on the line (§14; L-43).
 *
 * @property int $id
 * @property int $purchase_line_id
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
#[UseFactory(PurchaseLineTaxComponentFactory::class)]
class PurchaseLineTaxComponent extends Model
{
    /** @use HasFactory<PurchaseLineTaxComponentFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<PurchaseLine, $this> */
    public function purchaseLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseLine::class);
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
            'purchase_line_id' => 'integer',
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
