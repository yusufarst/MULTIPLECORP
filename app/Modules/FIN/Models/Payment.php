<?php

namespace App\Modules\FIN\Models;

use App\Modules\DOC\Models\BankStatementLine;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\CompanyBankAccount;
use App\Modules\PTY\Models\ClientOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\FIN\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An immutable client Cash-In fact (DATABASE §4.12 `payments`, AF; BR-FIN-06; AX-18), its bank-line
 * citation set once and corrected only with reason (C-19).
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property int $client_organization_id
 * @property CarbonImmutable $business_date
 * @property string $amount
 * @property string $method
 * @property int|null $company_bank_account_id
 * @property int|null $bank_statement_line_id
 * @property CarbonImmutable|null $bank_citation_corrected_at
 * @property int|null $bank_citation_corrected_by
 * @property string|null $bank_citation_corrected_reason
 * @property string|null $payer_intermediary
 * @property string|null $reference
 * @property string|null $unevidenced_reason
 * @property int|null $recovery_for_invoice_id
 * @property int|null $rerecords_payment_id
 * @property int|null $transfer_from_disbursement_id
 * @property string|null $transfer_from_disbursement_kind
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(PaymentFactory::class)]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<ClientOrganization, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ClientOrganization::class, 'client_organization_id');
    }

    /** @return BelongsTo<CompanyBankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class, 'company_bank_account_id');
    }

    /** @return BelongsTo<BankStatementLine, $this> */
    public function statementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'bank_statement_line_id');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function recoveryFor(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'recovery_for_invoice_id');
    }

    /** @return BelongsTo<Payment, $this> */
    public function rerecords(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'rerecords_payment_id');
    }

    /** @return BelongsTo<Disbursement, $this> */
    public function transferFrom(): BelongsTo
    {
        return $this->belongsTo(Disbursement::class, 'transfer_from_disbursement_id');
    }

    /** @return HasMany<PaymentApplication, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(PaymentApplication::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'client_organization_id' => 'integer',
            'business_date' => 'immutable_date',
            'amount' => 'decimal:2',
            'company_bank_account_id' => 'integer',
            'bank_statement_line_id' => 'integer',
            'bank_citation_corrected_at' => 'immutable_datetime',
            'bank_citation_corrected_by' => 'integer',
            'recovery_for_invoice_id' => 'integer',
            'rerecords_payment_id' => 'integer',
            'transfer_from_disbursement_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
