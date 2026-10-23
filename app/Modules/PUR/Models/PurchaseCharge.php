<?php

namespace App\Modules\PUR\Models;

use App\Modules\CST\Models\PurchaseChargeBalance;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\PUR\PurchaseChargeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A charge on a purchase as recorded (DATABASE §4.6 `purchase_charges`, AF; BR-PUR-05; SF-CHARGE): its
 * source bill a SUPPLIER_BILL of the purchase's company (L-43; C-58), corrected by CHARGE_CORRECTION
 * adjustments.
 *
 * @property int $id
 * @property int $purchase_id
 * @property int $company_id
 * @property int $source_bill_evidence_id
 * @property string $source_bill_evidence_type
 * @property string $classification
 * @property string $amount
 * @property string|null $allocation_basis
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(PurchaseChargeFactory::class)]
class PurchaseCharge extends Model
{
    use AppendOnly;

    /** @use HasFactory<PurchaseChargeFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** @return BelongsTo<EvidenceDocument, $this> */
    public function sourceBill(): BelongsTo
    {
        return $this->belongsTo(EvidenceDocument::class, 'source_bill_evidence_id');
    }

    /** @return HasMany<PurchaseChargeLineShare, $this> */
    public function shares(): HasMany
    {
        return $this->hasMany(PurchaseChargeLineShare::class);
    }

    /** @return HasOne<PurchaseChargeBalance, $this> */
    public function balance(): HasOne
    {
        return $this->hasOne(PurchaseChargeBalance::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purchase_id' => 'integer',
            'company_id' => 'integer',
            'source_bill_evidence_id' => 'integer',
            'amount' => 'decimal:2',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
