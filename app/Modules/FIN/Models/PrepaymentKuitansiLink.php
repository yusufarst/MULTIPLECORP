<?php

namespace App\Modules\FIN\Models;

use App\Modules\DOC\Models\Document;
use Carbon\CarbonImmutable;
use Database\Factories\FIN\PrepaymentKuitansiLinkFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The link of a pre-payment Kuitansi to the payment that settled it on an invoice (DATABASE §4.12
 * `prepayment_kuitansi_links`, SR; AX-31), never proof of receipt, unlinked once.
 *
 * @property int $id
 * @property int $company_id
 * @property int $document_id
 * @property int $invoice_id
 * @property int $payment_id
 * @property string $state
 * @property string|null $unlink_reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(PrepaymentKuitansiLinkFactory::class)]
class PrepaymentKuitansiLink extends Model
{
    /** @use HasFactory<PrepaymentKuitansiLinkFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'document_id' => 'integer',
            'invoice_id' => 'integer',
            'payment_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
