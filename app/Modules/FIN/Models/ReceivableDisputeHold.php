<?php

namespace App\Modules\FIN\Models;

use App\Modules\IAM\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\FIN\ReceivableDisputeHoldFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dispute that keeps a receivable active and aging (DATABASE §4.12 `receivable_dispute_holds`, SR;
 * BR-FIN-08; C-42), cleared once.
 *
 * @property int $id
 * @property int $invoice_id
 * @property string $reason
 * @property int $set_by
 * @property CarbonImmutable $set_at
 * @property int|null $cleared_by
 * @property CarbonImmutable|null $cleared_at
 * @property string|null $clear_reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(ReceivableDisputeHoldFactory::class)]
class ReceivableDisputeHold extends Model
{
    /** @use HasFactory<ReceivableDisputeHoldFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    /** @return BelongsTo<User, $this> */
    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'set_by' => 'integer',
            'set_at' => 'immutable_datetime',
            'cleared_by' => 'integer',
            'cleared_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
