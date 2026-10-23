<?php

namespace App\Modules\INV\Models;

use App\Modules\CAT\Models\Product;
use App\Modules\PRJ\Models\ProjectLine;
use Carbon\CarbonImmutable;
use Database\Factories\INV\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A project line's claim on usable supply (DATABASE §4.7 `reservations`, SR; SM:Reservation): one ACTIVE
 * per line, never reactivated once ended (C-05; L-07).
 *
 * @property int $id
 * @property int $project_line_id
 * @property int $company_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property string $state
 * @property string $remaining_qty_base
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(ReservationFactory::class)]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

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

    /** @return HasMany<ReservationEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ReservationEvent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'project_line_id' => 'integer',
            'company_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'remaining_qty_base' => 'decimal:3',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
