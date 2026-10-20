<?php

namespace App\Modules\ORG\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ORG\CompanyBankAccountFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company's own bank account (DATABASE §4.2 `company_bank_accounts`, MD with immutable detail): once a
 * fact references it, its details never change — a change is a new row plus deactivation (BR-SN-02).
 *
 * @property int $id
 * @property int $company_id
 * @property string $bank_name
 * @property string $account_number
 * @property string $account_holder
 * @property string|null $branch
 * @property bool $is_active
 * @property CarbonImmutable|null $deactivated_at
 * @property int|null $deactivated_by
 * @property string|null $deactivated_reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(CompanyBankAccountFactory::class)]
class CompanyBankAccount extends Model
{
    /** @use HasFactory<CompanyBankAccountFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'is_active' => 'boolean',
            'deactivated_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
