<?php

namespace App\Modules\FUL\Models;

use App\Modules\CAT\Models\Product;
use App\Modules\IAM\Models\User;
use App\Modules\INV\Models\DispatchLine;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\PRJ\Models\ProjectLine;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\FUL\DeliveryClosureFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The formal closure of a shipped quantity the client never received (DATABASE §4.9 `delivery_closures`,
 * AD; WF-FUL-02; CM-06/07), superseded once when decided in error (L-32).
 *
 * @property int $id
 * @property int $project_line_id
 * @property int $company_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property int|null $dispatch_line_id
 * @property int|null $dropship_confirmation_line_id
 * @property string $closed_qty_base
 * @property string $closure_kind
 * @property string $reason
 * @property int|null $correction_case_id
 * @property int $decided_by
 * @property CarbonImmutable $business_date
 * @property int|null $supersedes_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(DeliveryClosureFactory::class)]
class DeliveryClosure extends Model
{
    use AppendOnly;

    /** @use HasFactory<DeliveryClosureFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

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

    /** @return BelongsTo<DispatchLine, $this> */
    public function dispatchLine(): BelongsTo
    {
        return $this->belongsTo(DispatchLine::class);
    }

    /** @return BelongsTo<DropshipConfirmationLine, $this> */
    public function dropshipLine(): BelongsTo
    {
        return $this->belongsTo(DropshipConfirmationLine::class, 'dropship_confirmation_line_id');
    }

    /** @return BelongsTo<CorrectionCase, $this> */
    public function correctionCase(): BelongsTo
    {
        return $this->belongsTo(CorrectionCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return BelongsTo<DeliveryClosure, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(DeliveryClosure::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'project_line_id' => 'integer',
            'company_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'dispatch_line_id' => 'integer',
            'dropship_confirmation_line_id' => 'integer',
            'closed_qty_base' => 'decimal:3',
            'correction_case_id' => 'integer',
            'decided_by' => 'integer',
            'business_date' => 'immutable_date',
            'supersedes_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
