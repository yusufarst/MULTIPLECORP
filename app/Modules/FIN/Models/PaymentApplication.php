<?php

namespace App\Modules\FIN\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FIN\PaymentApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money of a source applied to an invoice or backing a refund, of one company and client (DATABASE §4.12
 * `payment_applications`, SR; BR-FIN-03; C-14), ended once by a typed exit (L-46; C-44).
 *
 * @property int $id
 * @property int $company_id
 * @property int $client_organization_id
 * @property int|null $payment_id
 * @property int|null $opening_customer_credit_id
 * @property int|null $invoice_id
 * @property int|null $refund_disbursement_id
 * @property string $target_kind
 * @property string $amount
 * @property string $state
 * @property string|null $end_reason
 * @property int|null $end_contra_id
 * @property string|null $end_command_id
 * @property int|null $supersedes_application_id
 * @property int|null $resolves_application_id
 * @property CarbonImmutable $business_date
 * @property string|null $reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(PaymentApplicationFactory::class)]
class PaymentApplication extends Model
{
    /** @use HasFactory<PaymentApplicationFactory> */
    use HasFactory;

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

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Disbursement, $this> */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Disbursement::class, 'refund_disbursement_id');
    }

    /** @return BelongsTo<FinancialContra, $this> */
    public function endContra(): BelongsTo
    {
        return $this->belongsTo(FinancialContra::class);
    }

    /** @return BelongsTo<PaymentApplication, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(PaymentApplication::class, 'supersedes_application_id');
    }

    /** @return BelongsTo<PaymentApplication, $this> */
    public function resolves(): BelongsTo
    {
        return $this->belongsTo(PaymentApplication::class, 'resolves_application_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'client_organization_id' => 'integer',
            'payment_id' => 'integer',
            'opening_customer_credit_id' => 'integer',
            'invoice_id' => 'integer',
            'refund_disbursement_id' => 'integer',
            'amount' => 'decimal:2',
            'end_contra_id' => 'integer',
            'supersedes_application_id' => 'integer',
            'resolves_application_id' => 'integer',
            'business_date' => 'immutable_date',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
