<?php

namespace App\Modules\CST\Models;

use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PUR\Models\PurchaseCharge;
use App\Modules\PUR\Models\PurchaseLine;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\CST\CostAttributionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signed managerial cost — HPP, loss or supplier-return cost — from one consumption, drop-ship
 * confirmation line or non-stock purchase line (DATABASE §4.8 `cost_attributions`, AF; §6): attributed
 * once (C-25), allocated across companies only with its reason (L-44; C-28). The consumption, the
 * drop-ship line and the lot are INV's and FUL's records (TASK-008), related by their own models.
 *
 * @property int $id
 * @property string $cost_class
 * @property string $cause
 * @property string $amount
 * @property int|null $stock_consumption_id
 * @property int|null $dropship_confirmation_line_id
 * @property int|null $purchase_line_id
 * @property string|null $cost_component
 * @property int|null $purchase_charge_id
 * @property int|null $purchase_charge_line_share_id
 * @property int|null $respread_from_purchase_line_id
 * @property int|null $allocation_version
 * @property int|null $lot_id
 * @property int|null $source_company_id
 * @property int|null $stock_restoration_id
 * @property int|null $correction_case_id
 * @property int|null $case_company_id
 * @property int $bearer_company_id
 * @property int|null $bearer_project_id
 * @property string|null $allocation_reason
 * @property int|null $reverses_attribution_id
 * @property CarbonImmutable $business_date
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(CostAttributionFactory::class)]
class CostAttribution extends Model
{
    use AppendOnly;

    /** @use HasFactory<CostAttributionFactory> */
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

    /** @return BelongsTo<Company, $this> */
    public function sourceCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function bearerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function bearerProject(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<CorrectionCase, $this> */
    public function correctionCase(): BelongsTo
    {
        return $this->belongsTo(CorrectionCase::class);
    }

    /** @return BelongsTo<CostAttribution, $this> */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(CostAttribution::class, 'reverses_attribution_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'stock_consumption_id' => 'integer',
            'dropship_confirmation_line_id' => 'integer',
            'purchase_line_id' => 'integer',
            'purchase_charge_id' => 'integer',
            'purchase_charge_line_share_id' => 'integer',
            'respread_from_purchase_line_id' => 'integer',
            'allocation_version' => 'integer',
            'lot_id' => 'integer',
            'source_company_id' => 'integer',
            'stock_restoration_id' => 'integer',
            'correction_case_id' => 'integer',
            'case_company_id' => 'integer',
            'bearer_company_id' => 'integer',
            'bearer_project_id' => 'integer',
            'reverses_attribution_id' => 'integer',
            'business_date' => 'immutable_date',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
