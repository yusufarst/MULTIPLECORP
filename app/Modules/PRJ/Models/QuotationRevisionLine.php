<?php

namespace App\Modules\PRJ\Models;

use App\Modules\CAT\Models\Unit;
use Carbon\CarbonImmutable;
use Database\Factories\PRJ\QuotationRevisionLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quoted line of a revision (DATABASE §4.5 `quotation_revision_lines`, SN once SENT): its quantity at
 * the ratio snapshot (C-09), price and tax components, editable only while the revision is a DRAFT.
 *
 * @property int $id
 * @property int $quotation_revision_id
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
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(QuotationRevisionLineFactory::class)]
class QuotationRevisionLine extends Model
{
    /** @use HasFactory<QuotationRevisionLineFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<QuotationRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'quotation_revision_id');
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
            'quotation_revision_id' => 'integer',
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
            'recorded_by' => 'integer',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
