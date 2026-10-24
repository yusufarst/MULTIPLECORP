<?php

namespace App\Modules\FIN\Models;

use App\Modules\ORG\Models\TaxType;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\FIN\PaymentClaimedDeductionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fee or tax the remittance advice says was deducted from a payment (DATABASE §4.12
 * `payment_claimed_deductions`, AF; L-28), corrected by a superseding row (C-59).
 *
 * @property int $id
 * @property int $payment_id
 * @property string $deduction_class
 * @property int|null $tax_type_id
 * @property string|null $fee_type
 * @property string $claimed_amount
 * @property int|null $supersedes_claimed_deduction_id
 * @property string|null $reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(PaymentClaimedDeductionFactory::class)]
class PaymentClaimedDeduction extends Model
{
    use AppendOnly;

    /** @use HasFactory<PaymentClaimedDeductionFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<TaxType, $this> */
    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class);
    }

    /** @return BelongsTo<PaymentClaimedDeduction, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(PaymentClaimedDeduction::class, 'supersedes_claimed_deduction_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'tax_type_id' => 'integer',
            'claimed_amount' => 'decimal:2',
            'supersedes_claimed_deduction_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
