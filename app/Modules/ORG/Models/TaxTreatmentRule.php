<?php

namespace App\Modules\ORG\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ORG\TaxTreatmentRuleFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A configured tax treatment for a transaction context and tax type by period (DATABASE §4.2
 * `tax_treatment_rules`, CF; §12): without a company it applies to every company, and a company-specific
 * rule overrides it.
 *
 * @property int $id
 * @property int|null $company_id
 * @property string $transaction_context
 * @property int $tax_type_id
 * @property string $treatment
 * @property bool $evidence_required
 * @property string $effective
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(TaxTreatmentRuleFactory::class)]
class TaxTreatmentRule extends Model
{
    /** @use HasFactory<TaxTreatmentRuleFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<TaxType, $this> */
    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'tax_type_id' => 'integer',
            'evidence_required' => 'boolean',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
