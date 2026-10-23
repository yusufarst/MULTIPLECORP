<?php

namespace App\Modules\CST\Models;

use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\OPS\Models\ImportRow;
use App\Modules\ORG\Models\Company;
use App\Modules\PUR\Models\PurchaseCharge;
use App\Modules\PUR\Models\PurchaseChargeLineShare;
use App\Modules\PUR\Models\PurchaseLine;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\CST\LotCostEntryFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signed amount of a lot's acquisition cost with its component (DATABASE §4.8 `lot_cost_entries`, AF;
 * §6): one basis, one non-creditable tax row and one row per charge share per lot (BR-INV-04; BR-FIN-17).
 * The lot and the restoration are INV's records (TASK-008), related by their own models.
 *
 * @property int $id
 * @property int $lot_id
 * @property int $source_company_id
 * @property string $entry_kind
 * @property string $cost_component
 * @property string $amount
 * @property int|null $purchase_line_id
 * @property int|null $purchase_charge_id
 * @property int|null $purchase_charge_line_share_id
 * @property int|null $respread_from_purchase_line_id
 * @property int|null $allocation_version
 * @property int|null $purchase_line_adjustment_id
 * @property int|null $import_row_id
 * @property int|null $stock_restoration_id
 * @property int|null $correction_case_id
 * @property CarbonImmutable $business_date
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(LotCostEntryFactory::class)]
class LotCostEntry extends Model
{
    use AppendOnly;

    /** @use HasFactory<LotCostEntryFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Company, $this> */
    public function sourceCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

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

    /** @return BelongsTo<PurchaseChargeLineShare, $this> */
    public function share(): BelongsTo
    {
        return $this->belongsTo(PurchaseChargeLineShare::class, 'purchase_charge_line_share_id');
    }

    /** @return BelongsTo<ImportRow, $this> */
    public function importRow(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class);
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
            'lot_id' => 'integer',
            'source_company_id' => 'integer',
            'amount' => 'decimal:2',
            'purchase_line_id' => 'integer',
            'purchase_charge_id' => 'integer',
            'purchase_charge_line_share_id' => 'integer',
            'respread_from_purchase_line_id' => 'integer',
            'allocation_version' => 'integer',
            'purchase_line_adjustment_id' => 'integer',
            'import_row_id' => 'integer',
            'stock_restoration_id' => 'integer',
            'correction_case_id' => 'integer',
            'business_date' => 'immutable_date',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
