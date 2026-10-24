<?php

namespace App\Modules\FUL\Models;

use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\FUL\DeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The client-side record of goods handed to the client, never touching stock (DATABASE §4.9 `deliveries`,
 * AF; BR-INV-02; FS-03).
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property int $project_id
 * @property CarbonImmutable $business_date
 * @property string $recipient_name
 * @property string|null $condition_note
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(DeliveryFactory::class)]
class Delivery extends Model
{
    use AppendOnly;

    /** @use HasFactory<DeliveryFactory> */
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

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<DeliveryLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryLine::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'project_id' => 'integer',
            'business_date' => 'immutable_date',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
