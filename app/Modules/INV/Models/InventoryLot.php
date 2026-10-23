<?php

namespace App\Modules\INV\Models;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\Unit;
use App\Modules\OPS\Models\ImportRow;
use App\Modules\ORG\Models\Company;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\INV\InventoryLotFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One lot per acquisition (DATABASE §4.7 `inventory_lots`, AF; §5.4; BR-INV-04): its source company
 * keyed to the receipt, import row, drop-ship return or count finding that created it (C-08, C-28).
 *
 * @property int $id
 * @property string $lot_code
 * @property int $product_id
 * @property int $quantity_scale
 * @property int $source_company_id
 * @property string $acquisition_kind
 * @property int|null $receiving_line_id
 * @property int|null $import_row_id
 * @property int|null $restoration_id
 * @property int|null $stock_count_finding_id
 * @property string $entered_qty
 * @property int $entered_unit_id
 * @property int $ratio_to_base
 * @property string $acquired_qty_base
 * @property CarbonImmutable $business_date
 * @property bool $cost_basis_flagged
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(InventoryLotFactory::class)]
class InventoryLot extends Model
{
    use AppendOnly;

    /** @use HasFactory<InventoryLotFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function sourceCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<ReceivingLine, $this> */
    public function receivingLine(): BelongsTo
    {
        return $this->belongsTo(ReceivingLine::class);
    }

    /** @return BelongsTo<ImportRow, $this> */
    public function importRow(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class);
    }

    /** @return BelongsTo<StockRestoration, $this> */
    public function restoration(): BelongsTo
    {
        return $this->belongsTo(StockRestoration::class);
    }

    /** @return BelongsTo<StockCountFinding, $this> */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(StockCountFinding::class, 'stock_count_finding_id');
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
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'source_company_id' => 'integer',
            'receiving_line_id' => 'integer',
            'import_row_id' => 'integer',
            'restoration_id' => 'integer',
            'stock_count_finding_id' => 'integer',
            'entered_qty' => 'decimal:3',
            'entered_unit_id' => 'integer',
            'ratio_to_base' => 'integer',
            'acquired_qty_base' => 'decimal:3',
            'business_date' => 'immutable_date',
            'cost_basis_flagged' => 'boolean',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
