<?php

namespace App\Modules\PUR\Models;

use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\PUR\PurchaseChargeLineShareFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A charge's share on a line in one allocation version (DATABASE §4.6 `purchase_charge_line_shares`, AF,
 * versioned; BR-FIN-13): a correction adds a version, never edits one (CM-17, CM-35).
 *
 * @property int $id
 * @property int $purchase_charge_id
 * @property int $purchase_line_id
 * @property int $company_id
 * @property string $basis_value
 * @property string $share_amount
 * @property int $allocation_version
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(PurchaseChargeLineShareFactory::class)]
class PurchaseChargeLineShare extends Model
{
    use AppendOnly;

    /** @use HasFactory<PurchaseChargeLineShareFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<PurchaseCharge, $this> */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(PurchaseCharge::class, 'purchase_charge_id');
    }

    /** @return BelongsTo<PurchaseLine, $this> */
    public function purchaseLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseLine::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purchase_charge_id' => 'integer',
            'purchase_line_id' => 'integer',
            'company_id' => 'integer',
            'basis_value' => 'decimal:3',
            'share_amount' => 'decimal:2',
            'allocation_version' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
