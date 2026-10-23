<?php

namespace App\Modules\ORG\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ORG\NumberingSchemeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company's numbering rule for a sequence type from its effective date (DATABASE §4.2
 * `numbering_schemes`, CF; WF-MD-01). The counters themselves are `number_sequences`, written only by the
 * allocator's single statement (CONCURRENCY_IDEMPOTENCY NM-02; TASK-021), so they have no model.
 *
 * @property int $id
 * @property int $company_id
 * @property string $sequence_type
 * @property string $period_granularity
 * @property string $format_template
 * @property string $revision_format
 * @property CarbonImmutable $effective_from
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(NumberingSchemeFactory::class)]
class NumberingScheme extends Model
{
    /** @use HasFactory<NumberingSchemeFactory> */
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
            'effective_from' => 'immutable_date',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
