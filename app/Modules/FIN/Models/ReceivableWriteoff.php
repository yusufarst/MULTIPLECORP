<?php

namespace App\Modules\FIN\Models;

use App\Modules\IAM\Models\User;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\FIN\ReceivableWriteoffFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The Owner's disposal of an invoice's outstanding (DATABASE §4.12 `receivable_writeoffs`, AD; BR-FIN-08;
 * AX-24), no Cash-In and no profit.
 *
 * @property int $id
 * @property int $company_id
 * @property int $invoice_id
 * @property string $amount
 * @property string $reason
 * @property string $outstanding_before
 * @property string $outstanding_after
 * @property int $decided_by
 * @property CarbonImmutable $business_date
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(ReceivableWriteoffFactory::class)]
class ReceivableWriteoff extends Model
{
    use AppendOnly;

    /** @use HasFactory<ReceivableWriteoffFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
            'company_id' => 'integer',
            'invoice_id' => 'integer',
            'amount' => 'decimal:2',
            'outstanding_before' => 'decimal:2',
            'outstanding_after' => 'decimal:2',
            'decided_by' => 'integer',
            'business_date' => 'immutable_date',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
