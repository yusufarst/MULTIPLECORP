<?php

namespace App\Modules\FIN\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The receivable guard of an invoice (DATABASE §4.12 `invoice_balances`, GB; BR-FIN-04; C-15). It has no
 * factory: a balance belongs to its posting service (ARCHITECTURE §7, §19).
 *
 * @property int $invoice_id
 * @property int $company_id
 * @property string $gross_value
 * @property string $sales_value
 * @property string $output_tax_value
 * @property string $applied_amount
 * @property string $fee_settled_amount
 * @property string $tax_settled_amount
 * @property string $collected_tax_settled_amount
 * @property string $written_off_amount
 * @property int|null $current_billing_act_id
 * @property CarbonImmutable|null $current_due_date
 * @property string $outstanding
 * @property CarbonImmutable $recorded_at
 */
class InvoiceBalance extends Model
{
    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    protected $primaryKey = 'invoice_id';

    public $incrementing = false;

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<InvoiceBillingAct, $this> */
    public function currentBillingAct(): BelongsTo
    {
        return $this->belongsTo(InvoiceBillingAct::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'company_id' => 'integer',
            'gross_value' => 'decimal:2',
            'sales_value' => 'decimal:2',
            'output_tax_value' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'fee_settled_amount' => 'decimal:2',
            'tax_settled_amount' => 'decimal:2',
            'collected_tax_settled_amount' => 'decimal:2',
            'written_off_amount' => 'decimal:2',
            'current_billing_act_id' => 'integer',
            'current_due_date' => 'immutable_date',
            'outstanding' => 'decimal:2',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
