<?php

namespace App\Modules\DOC\Models;

use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\CompanyBankAccount;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\BankStatementLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A line of a bank statement, the identity every bank Cash-In cites (DATABASE §4.10 `bank_statement_lines`,
 * AF with a void state; L-22; C-19): never changed or deleted, voided once when mistyped.
 *
 * @property int $id
 * @property int $company_id
 * @property int $company_bank_account_id
 * @property int $statement_evidence_id
 * @property string $statement_evidence_type
 * @property CarbonImmutable $business_date
 * @property string $amount
 * @property string $line_reference
 * @property string $state
 * @property int|null $voided_by
 * @property CarbonImmutable|null $voided_at
 * @property string|null $voided_reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(BankStatementLineFactory::class)]
class BankStatementLine extends Model
{
    /** @use HasFactory<BankStatementLineFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<CompanyBankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class, 'company_bank_account_id');
    }

    /** @return BelongsTo<EvidenceDocument, $this> */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(EvidenceDocument::class, 'statement_evidence_id');
    }

    /** @return HasOne<EvidenceBalance, $this> */
    public function balance(): HasOne
    {
        return $this->hasOne(EvidenceBalance::class);
    }

    /** @return BelongsTo<User, $this> */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'company_bank_account_id' => 'integer',
            'statement_evidence_id' => 'integer',
            'business_date' => 'immutable_date',
            'amount' => 'decimal:2',
            'voided_by' => 'integer',
            'voided_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
