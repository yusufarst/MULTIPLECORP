<?php

namespace App\Modules\PRJ\Models;

use App\Modules\CAT\Models\Unit;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\PRJ\ConfirmationLinePinFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The conversion, price and tax a confirmation pins for a line (DATABASE §4.5 `confirmation_line_pins`,
 * SN; BR-SN-01): immutable, a re-confirmation adding new pins (WF-PRJ-02).
 *
 * @property int $id
 * @property int $commercial_confirmation_id
 * @property int $company_id
 * @property int $project_line_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property string $description
 * @property string $entered_qty
 * @property int $entered_unit_id
 * @property int $ratio_to_base
 * @property string $qty_base
 * @property string $unit_price
 * @property string $sales_value
 * @property list<array<string, mixed>> $tax_components
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(ConfirmationLinePinFactory::class)]
class ConfirmationLinePin extends Model
{
    use AppendOnly;

    /** @use HasFactory<ConfirmationLinePinFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<CommercialConfirmation, $this> */
    public function confirmation(): BelongsTo
    {
        return $this->belongsTo(CommercialConfirmation::class, 'commercial_confirmation_id');
    }

    /** @return BelongsTo<ProjectLine, $this> */
    public function projectLine(): BelongsTo
    {
        return $this->belongsTo(ProjectLine::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function enteredUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'commercial_confirmation_id' => 'integer',
            'company_id' => 'integer',
            'project_line_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'entered_qty' => 'decimal:3',
            'entered_unit_id' => 'integer',
            'ratio_to_base' => 'integer',
            'qty_base' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'sales_value' => 'decimal:2',
            'tax_components' => 'array',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
