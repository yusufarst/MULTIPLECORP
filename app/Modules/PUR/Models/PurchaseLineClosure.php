<?php

namespace App\Modules\PUR\Models;

use App\Modules\IAM\Models\User;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\PUR\PurchaseLineClosureFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The closure of a purchase line's remainder (DATABASE §4.6 `purchase_line_closures`, AD; BR-PUR-04;
 * AX-32): once per line per command, superseded once with the exact inverse posting (CM-27; C-59).
 *
 * @property int $id
 * @property int $purchase_line_id
 * @property int $company_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property string $closed_qty_base
 * @property string $reason
 * @property int $decided_by
 * @property CarbonImmutable $business_date
 * @property int|null $supersedes_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(PurchaseLineClosureFactory::class)]
class PurchaseLineClosure extends Model
{
    use AppendOnly;

    /** @use HasFactory<PurchaseLineClosureFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<PurchaseLine, $this> */
    public function purchaseLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseLine::class);
    }

    /** @return BelongsTo<PurchaseLineClosure, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(PurchaseLineClosure::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purchase_line_id' => 'integer',
            'company_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'closed_qty_base' => 'decimal:3',
            'decided_by' => 'integer',
            'business_date' => 'immutable_date',
            'supersedes_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
