<?php

namespace App\Modules\FIN\Models;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\Unit;
use App\Modules\PRJ\Models\ProjectLine;
use Carbon\CarbonImmutable;
use Database\Factories\FIN\InvoiceVersionLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An itemized line of an invoice version, or a value-only line with no quantity (DATABASE §4.12
 * `invoice_version_lines`, SN once issued, draft-editable).
 *
 * @property int $id
 * @property int $invoice_version_id
 * @property int $company_id
 * @property int|null $project_line_id
 * @property int|null $product_id
 * @property int|null $quantity_scale
 * @property string $description
 * @property string|null $entered_qty
 * @property int|null $entered_unit_id
 * @property int|null $ratio_to_base
 * @property string|null $qty_base
 * @property string|null $unit_price
 * @property string $line_sales_value
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(InvoiceVersionLineFactory::class)]
class InvoiceVersionLine extends Model
{
    /** @use HasFactory<InvoiceVersionLineFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<InvoiceVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(InvoiceVersion::class, 'invoice_version_id');
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'invoice_version_id' => 'integer',
            'company_id' => 'integer',
            'project_line_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'entered_qty' => 'decimal:3',
            'entered_unit_id' => 'integer',
            'ratio_to_base' => 'integer',
            'qty_base' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_sales_value' => 'decimal:2',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
