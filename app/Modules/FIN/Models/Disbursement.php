<?php

namespace App\Modules\FIN\Models;

use App\Modules\CST\Models\Expense;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\CompanyBankAccount;
use App\Modules\ORG\Models\TaxTreatmentRule;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PUR\Models\Purchase;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\FIN\DisbursementFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The only Cash-Out, citing its purchase, expense or tax proof by kind, or backing a refund (DATABASE
 * §4.12 `disbursements`, AF; BR-FIN-11; L-43).
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property int|null $client_organization_id
 * @property CarbonImmutable $business_date
 * @property string $amount
 * @property string $kind
 * @property int $company_bank_account_id
 * @property int|null $purchase_id
 * @property int|null $expense_id
 * @property int|null $tax_evidence_id
 * @property string|null $tax_evidence_type
 * @property int|null $treatment_rule_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(DisbursementFactory::class)]
class Disbursement extends Model
{
    use AppendOnly;

    /** @use HasFactory<DisbursementFactory> */
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

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /** @return BelongsTo<EvidenceDocument, $this> */
    public function taxEvidence(): BelongsTo
    {
        return $this->belongsTo(EvidenceDocument::class);
    }

    /** @return BelongsTo<TaxTreatmentRule, $this> */
    public function treatmentRule(): BelongsTo
    {
        return $this->belongsTo(TaxTreatmentRule::class);
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
            'purchase_id' => 'integer',
            'expense_id' => 'integer',
            'tax_evidence_id' => 'integer',
            'treatment_rule_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
