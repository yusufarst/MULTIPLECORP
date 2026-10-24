<?php

namespace App\Modules\FIN\Models;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\TaxTreatmentRule;
use App\Modules\ORG\Models\TaxType;
use Carbon\CarbonImmutable;
use Database\Factories\FIN\DeductionSettlementFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fee or tax the client deducted, anchored to its payment, invoice and evidence (DATABASE §4.12
 * `deduction_settlements`, AF with a state; L-28; BR-FIN-09/16), its state moving once (C-60).
 *
 * @property int $id
 * @property int $company_id
 * @property int $client_organization_id
 * @property int $invoice_id
 * @property int $payment_id
 * @property string $settlement_class
 * @property int|null $tax_type_id
 * @property string|null $fee_type
 * @property string|null $tax_direction
 * @property string $specific_type_key
 * @property int|null $treatment_rule_id
 * @property string $amount
 * @property int $evidence_document_id
 * @property string $evidence_type
 * @property CarbonImmutable $business_date
 * @property string $state
 * @property int|null $correction_case_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(DeductionSettlementFactory::class)]
class DeductionSettlement extends Model
{
    /** @use HasFactory<DeductionSettlementFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

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

    /** @return BelongsTo<TaxType, $this> */
    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class);
    }

    /** @return BelongsTo<TaxTreatmentRule, $this> */
    public function treatmentRule(): BelongsTo
    {
        return $this->belongsTo(TaxTreatmentRule::class);
    }

    /** @return BelongsTo<EvidenceDocument, $this> */
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(EvidenceDocument::class, 'evidence_document_id');
    }

    /** @return BelongsTo<CorrectionCase, $this> */
    public function correctionCase(): BelongsTo
    {
        return $this->belongsTo(CorrectionCase::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'client_organization_id' => 'integer',
            'invoice_id' => 'integer',
            'payment_id' => 'integer',
            'tax_type_id' => 'integer',
            'treatment_rule_id' => 'integer',
            'amount' => 'decimal:2',
            'evidence_document_id' => 'integer',
            'business_date' => 'immutable_date',
            'correction_case_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
