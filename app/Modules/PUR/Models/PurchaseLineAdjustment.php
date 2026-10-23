<?php

namespace App\Modules\PUR\Models;

use App\Modules\OPS\Models\CorrectionCase;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\PUR\PurchaseLineAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signed change of a purchase line's or a charge's value after its first effect (DATABASE §4.6
 * `purchase_line_adjustments`, AF): only the *_CORRECTION kinds drive the cost cascade (CM-17; AX-34).
 *
 * @property int $id
 * @property int|null $purchase_line_id
 * @property int|null $purchase_charge_id
 * @property int $company_id
 * @property string $kind
 * @property string $amount_delta
 * @property string $reason
 * @property int|null $correction_case_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(PurchaseLineAdjustmentFactory::class)]
class PurchaseLineAdjustment extends Model
{
    use AppendOnly;

    /** @use HasFactory<PurchaseLineAdjustmentFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<PurchaseLine, $this> */
    public function purchaseLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseLine::class);
    }

    /** @return BelongsTo<PurchaseCharge, $this> */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(PurchaseCharge::class, 'purchase_charge_id');
    }

    /** @return BelongsTo<CorrectionCase, $this> */
    public function correctionCase(): BelongsTo
    {
        return $this->belongsTo(CorrectionCase::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purchase_line_id' => 'integer',
            'purchase_charge_id' => 'integer',
            'company_id' => 'integer',
            'amount_delta' => 'decimal:2',
            'correction_case_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
