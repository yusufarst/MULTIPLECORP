<?php

namespace Database\Factories\DOC;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceLink;
use Database\Factories\OPS\CorrectionCaseFactory;
use Database\Factories\OPS\ImportBatchFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A link of an invented evidence to a correction case of its company or, for pool evidence, to an
 * import batch — the two targets whose tables exist before TASK-007.
 *
 * @extends Factory<EvidenceLink>
 */
final class EvidenceLinkFactory extends Factory
{
    protected $model = EvidenceLink::class;

    /** The link's record kinds, an exclusive arc (DATABASE §4.10). */
    private const TARGETS = [
        'project_id', 'commercial_confirmation_id', 'purchase_id', 'stock_movement_id', 'delivery_id', 'dropship_confirmation_id',
        'service_handover_id', 'payment_id', 'other_cash_receipt_id', 'disbursement_id', 'expense_id', 'deduction_settlement_id',
        'receivable_writeoff_id', 'correction_case_id', 'admin_requirement_id', 'unattributed_loss_case_id', 'import_batch_id',
    ];

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'evidence_document_id' => EvidenceDocument::factory(),
            'role' => 'PRIMARY_PROOF',
        ];
    }

    /** The scope and the company are the evidence's own (C-58); a target is chosen for it. */
    public function configure(): static
    {
        return $this->afterMaking(function (EvidenceLink $link): void {
            if ($link->getAttribute('evidence_scope') === null) {
                $evidence = EvidenceDocument::query()->findOrFail($link->evidence_document_id);
                $link->evidence_scope = $evidence->evidence_scope;
                $link->company_id = $evidence->company_id;
            }

            if (array_filter($link->only(self::TARGETS), static fn (mixed $target): bool => $target !== null) === []) {
                if ($link->company_id === null) {
                    $link->import_batch_id = ImportBatchFactory::new()->createOne()->id;
                } else {
                    $link->correction_case_id = CorrectionCaseFactory::new()->createOne(['company_id' => $link->company_id])->id;
                }
            }
        });
    }
}
