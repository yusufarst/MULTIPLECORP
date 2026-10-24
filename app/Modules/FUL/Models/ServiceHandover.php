<?php

namespace App\Modules\FUL\Models;

use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\FUL\ServiceHandoverFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The SERVICE equivalent of a delivery (DATABASE §4.9 `service_handovers`, AF; L-39).
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property int $project_id
 * @property CarbonImmutable $business_date
 * @property string $recipient_name
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(ServiceHandoverFactory::class)]
class ServiceHandover extends Model
{
    use AppendOnly;

    /** @use HasFactory<ServiceHandoverFactory> */
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

    /** @return HasMany<ServiceHandoverLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ServiceHandoverLine::class);
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
