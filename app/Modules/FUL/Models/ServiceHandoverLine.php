<?php

namespace App\Modules\FUL\Models;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\Unit;
use App\Modules\PRJ\Models\ProjectLine;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\FUL\ServiceHandoverLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A demand line's quantity handed over, at its entered-unit snapshot (DATABASE §4.9
 * `service_handover_lines`, AF; BR-INV-03), reversed once and exactly (C-30).
 *
 * @property int $id
 * @property int $service_handover_id
 * @property int $company_id
 * @property int $project_line_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property string $entered_qty
 * @property int $entered_unit_id
 * @property int $ratio_to_base
 * @property string $qty_base
 * @property string|null $scope_note
 * @property int|null $reverses_line_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(ServiceHandoverLineFactory::class)]
class ServiceHandoverLine extends Model
{
    use AppendOnly;

    /** @use HasFactory<ServiceHandoverLineFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<ServiceHandover, $this> */
    public function handover(): BelongsTo
    {
        return $this->belongsTo(ServiceHandover::class, 'service_handover_id');
    }

    /** @return BelongsTo<ProjectLine, $this> */
    public function projectLine(): BelongsTo
    {
        return $this->belongsTo(ProjectLine::class);
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

    /** @return BelongsTo<ServiceHandoverLine, $this> */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(ServiceHandoverLine::class, 'reverses_line_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'service_handover_id' => 'integer',
            'company_id' => 'integer',
            'project_line_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'entered_qty' => 'decimal:3',
            'entered_unit_id' => 'integer',
            'ratio_to_base' => 'integer',
            'qty_base' => 'decimal:3',
            'reverses_line_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
