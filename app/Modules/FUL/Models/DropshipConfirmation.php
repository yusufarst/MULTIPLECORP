<?php

namespace App\Modules\FUL\Models;

use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PUR\Models\Purchase;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\FUL\DropshipConfirmationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A supplier's direct shipment to the client, confirmed once per command with no warehouse event (DATABASE
 * §4.9 `dropship_confirmations`, AF; BR-INV-11), identified once per purchase by its shipment reference
 * (C-49).
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property int $project_id
 * @property int $purchase_id
 * @property string $supplier_shipment_reference
 * @property CarbonImmutable $business_date
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(DropshipConfirmationFactory::class)]
class DropshipConfirmation extends Model
{
    use AppendOnly;

    /** @use HasFactory<DropshipConfirmationFactory> */
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

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** @return HasMany<DropshipConfirmationLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(DropshipConfirmationLine::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'project_id' => 'integer',
            'purchase_id' => 'integer',
            'business_date' => 'immutable_date',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
