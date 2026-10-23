<?php

namespace App\Modules\INV\Models;

use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use Carbon\CarbonImmutable;
use Database\Factories\INV\StockCountFindingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A count's surplus in one of its scopes (DATABASE §4.7 `stock_count_findings`, SR; AX-07): one OPEN per
 * scope and product (C-34), attributed by the Owner only.
 *
 * @property int $id
 * @property int $stock_count_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property int $location_id
 * @property string $condition
 * @property string $surplus_qty_base
 * @property string $state
 * @property int|null $attributed_company_id
 * @property string|null $flagged_cost_basis
 * @property int|null $decided_by
 * @property string|null $reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(StockCountFindingFactory::class)]
class StockCountFinding extends Model
{
    /** @use HasFactory<StockCountFindingFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<StockCount, $this> */
    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function attributedCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'stock_count_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'location_id' => 'integer',
            'surplus_qty_base' => 'decimal:3',
            'attributed_company_id' => 'integer',
            'flagged_cost_basis' => 'decimal:2',
            'decided_by' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
