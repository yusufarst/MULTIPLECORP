<?php

namespace App\Modules\INV\Models;

use App\Modules\CAT\Models\Unit;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Models\ProjectLine;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\INV\DispatchLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The outbound shipment of a demand line (DATABASE §4.7 `dispatch_lines`, AF; WF-INV-03) at its ratio
 * snapshot, consuming only its own line's reservation (C-05).
 *
 * @property int $id
 * @property int $stock_movement_id
 * @property int $project_line_id
 * @property int $project_id
 * @property int $company_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property string $entered_qty
 * @property int $entered_unit_id
 * @property int $ratio_to_base
 * @property string $qty_base
 * @property string $reserved_qty_base
 * @property string $unreserved_qty_base
 * @property int|null $reservation_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(DispatchLineFactory::class)]
class DispatchLine extends Model
{
    use AppendOnly;

    /** @use HasFactory<DispatchLineFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<StockMovement, $this> */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    /** @return BelongsTo<ProjectLine, $this> */
    public function projectLine(): BelongsTo
    {
        return $this->belongsTo(ProjectLine::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function enteredUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'stock_movement_id' => 'integer',
            'project_line_id' => 'integer',
            'project_id' => 'integer',
            'company_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'entered_qty' => 'decimal:3',
            'entered_unit_id' => 'integer',
            'ratio_to_base' => 'integer',
            'qty_base' => 'decimal:3',
            'reserved_qty_base' => 'decimal:3',
            'unreserved_qty_base' => 'decimal:3',
            'reservation_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
