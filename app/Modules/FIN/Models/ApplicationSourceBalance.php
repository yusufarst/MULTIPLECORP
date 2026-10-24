<?php

namespace App\Modules\FIN\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The guard of a payment or an opening credit as an application source (DATABASE §4.12
 * `application_source_balances`, GB; BR-FIN-03; C-13, C-44). It has no factory: a balance belongs to its
 * posting service (ARCHITECTURE §7, §19).
 *
 * @property int $id
 * @property int|null $payment_id
 * @property int|null $opening_customer_credit_id
 * @property int $company_id
 * @property int $client_organization_id
 * @property string $gross_amount
 * @property string $contra_amount
 * @property string $invoice_applied_amount
 * @property string $refund_backed_amount
 * @property string $refund_released_amount
 * @property string $refund_shortfall_amount
 * @property string $settled_amount
 * @property string $claimed_deductions_amount
 * @property string $net_amount
 * @property string $capacity_amount
 * @property CarbonImmutable $recorded_at
 */
class ApplicationSourceBalance extends Model
{
    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<OpeningCustomerCredit, $this> */
    public function openingCredit(): BelongsTo
    {
        return $this->belongsTo(OpeningCustomerCredit::class, 'opening_customer_credit_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'opening_customer_credit_id' => 'integer',
            'company_id' => 'integer',
            'client_organization_id' => 'integer',
            'gross_amount' => 'decimal:2',
            'contra_amount' => 'decimal:2',
            'invoice_applied_amount' => 'decimal:2',
            'refund_backed_amount' => 'decimal:2',
            'refund_released_amount' => 'decimal:2',
            'refund_shortfall_amount' => 'decimal:2',
            'settled_amount' => 'decimal:2',
            'claimed_deductions_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'capacity_amount' => 'decimal:2',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
