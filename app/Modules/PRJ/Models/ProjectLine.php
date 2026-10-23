<?php

namespace App\Modules\PRJ\Models;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\Unit;
use Carbon\CarbonImmutable;
use Database\Factories\PRJ\ProjectLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A project's demand line (DATABASE §4.5 `project_lines`, SR; §9): the spine every reservation, purchase
 * link, dispatch, delivery and invoice line cites; its draft values pinned by each confirmation, the
 * current pin its pointer.
 *
 * @property int $id
 * @property int $project_id
 * @property int $company_id
 * @property int $line_no
 * @property string $kind
 * @property int $product_id
 * @property int $quantity_scale
 * @property string $description
 * @property string $entered_qty
 * @property int $entered_unit_id
 * @property int $ratio_to_base
 * @property string $qty_base
 * @property string|null $unit_price
 * @property string $fulfillment_mode
 * @property int|null $split_from_line_id
 * @property int|null $current_pin_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(ProjectLineFactory::class)]
class ProjectLine extends Model
{
    /** @use HasFactory<ProjectLineFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function enteredUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<ConfirmationLinePin, $this> */
    public function currentPin(): BelongsTo
    {
        return $this->belongsTo(ConfirmationLinePin::class);
    }

    /** @return BelongsTo<ProjectLine, $this> */
    public function splitFrom(): BelongsTo
    {
        return $this->belongsTo(ProjectLine::class, 'split_from_line_id');
    }

    /** @return HasMany<ConfirmationLinePin, $this> */
    public function pins(): HasMany
    {
        return $this->hasMany(ConfirmationLinePin::class);
    }

    /** @return HasOne<ProjectLineBalance, $this> */
    public function balance(): HasOne
    {
        return $this->hasOne(ProjectLineBalance::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'company_id' => 'integer',
            'line_no' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'entered_qty' => 'decimal:3',
            'entered_unit_id' => 'integer',
            'ratio_to_base' => 'integer',
            'qty_base' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'split_from_line_id' => 'integer',
            'current_pin_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
