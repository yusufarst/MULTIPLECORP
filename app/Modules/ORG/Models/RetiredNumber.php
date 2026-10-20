<?php

namespace App\Modules\ORG\Models;

use App\Modules\IAM\Models\User;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\ORG\RetiredNumberFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The tombstone of a discarded draft project's number (DATABASE §4.2 `retired_numbers`, AF; L-37): the
 * number stays consumed, explained and never reissued (C-10).
 *
 * @property int $id
 * @property int $company_id
 * @property string $sequence_type
 * @property string $period_key
 * @property int $sequence_no
 * @property string $formatted_number
 * @property string $retired_kind
 * @property string $source_public_id
 * @property string $reason
 * @property int $retired_by
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(RetiredNumberFactory::class)]
class RetiredNumber extends Model
{
    use AppendOnly;

    /** @use HasFactory<RetiredNumberFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function retiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'sequence_no' => 'integer',
            'retired_by' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
