<?php

namespace App\Modules\DOC\Models;

use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\OPS\Models\ImportBatch;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\EvidenceLinkFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A typed link from an evidence to the one record it proves (DATABASE §4.10 `evidence_links`, SR; D-DB-10):
 * an exclusive arc, unlinked once with its reason. The targets other than a correction case and an import
 * batch are the records of later modules, related by their own models.
 *
 * @property int $id
 * @property int $evidence_document_id
 * @property string $evidence_scope
 * @property int|null $company_id
 * @property string $role
 * @property int|null $project_id
 * @property int|null $commercial_confirmation_id
 * @property int|null $purchase_id
 * @property int|null $stock_movement_id
 * @property int|null $delivery_id
 * @property int|null $dropship_confirmation_id
 * @property int|null $service_handover_id
 * @property int|null $payment_id
 * @property int|null $other_cash_receipt_id
 * @property int|null $disbursement_id
 * @property int|null $expense_id
 * @property int|null $deduction_settlement_id
 * @property int|null $receivable_writeoff_id
 * @property int|null $correction_case_id
 * @property int|null $admin_requirement_id
 * @property int|null $unattributed_loss_case_id
 * @property int|null $import_batch_id
 * @property CarbonImmutable|null $unlinked_at
 * @property int|null $unlinked_by
 * @property string|null $unlink_reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(EvidenceLinkFactory::class)]
class EvidenceLink extends Model
{
    /** @use HasFactory<EvidenceLinkFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

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

    /** @return BelongsTo<ImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function unlinkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlinked_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'evidence_document_id' => 'integer',
            'company_id' => 'integer',
            'project_id' => 'integer',
            'commercial_confirmation_id' => 'integer',
            'purchase_id' => 'integer',
            'stock_movement_id' => 'integer',
            'delivery_id' => 'integer',
            'dropship_confirmation_id' => 'integer',
            'service_handover_id' => 'integer',
            'payment_id' => 'integer',
            'other_cash_receipt_id' => 'integer',
            'disbursement_id' => 'integer',
            'expense_id' => 'integer',
            'deduction_settlement_id' => 'integer',
            'receivable_writeoff_id' => 'integer',
            'correction_case_id' => 'integer',
            'admin_requirement_id' => 'integer',
            'unattributed_loss_case_id' => 'integer',
            'import_batch_id' => 'integer',
            'unlinked_at' => 'immutable_datetime',
            'unlinked_by' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
