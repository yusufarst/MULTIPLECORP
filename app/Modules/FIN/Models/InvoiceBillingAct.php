<?php

namespace App\Modules\FIN\Models;

use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\ImportRow;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\FIN\InvoiceBillingActFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The billing of an invoice, once, with a linear correction chain (DATABASE §4.12 `invoice_billing_acts`,
 * AD; AX-17; C-41), a replacement inheriting the replaced invoice's current act (L-30).
 *
 * @property int $id
 * @property int $invoice_id
 * @property CarbonImmutable $billed_at
 * @property CarbonImmutable|null $due_date
 * @property string $kind
 * @property int|null $inherited_from_act_id
 * @property int|null $inherited_from_invoice_id
 * @property int|null $import_row_id
 * @property int|null $supersedes_act_id
 * @property string|null $reason
 * @property int $decided_by
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(InvoiceBillingActFactory::class)]
class InvoiceBillingAct extends Model
{
    use AppendOnly;

    /** @use HasFactory<InvoiceBillingActFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<InvoiceBillingAct, $this> */
    public function inheritedFrom(): BelongsTo
    {
        return $this->belongsTo(InvoiceBillingAct::class, 'inherited_from_act_id');
    }

    /** @return BelongsTo<InvoiceBillingAct, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(InvoiceBillingAct::class, 'supersedes_act_id');
    }

    /** @return BelongsTo<ImportRow, $this> */
    public function importRow(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class);
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
            'invoice_id' => 'integer',
            'billed_at' => 'immutable_date',
            'due_date' => 'immutable_date',
            'inherited_from_act_id' => 'integer',
            'inherited_from_invoice_id' => 'integer',
            'import_row_id' => 'integer',
            'supersedes_act_id' => 'integer',
            'decided_by' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
