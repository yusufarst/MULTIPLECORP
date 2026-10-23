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

            if ($link->getAttribute('correction_case_id') === null && $link->getAttribute('import_batch_id') === null) {
                if ($link->company_id === null) {
                    $link->import_batch_id = ImportBatchFactory::new()->createOne()->id;
                } else {
                    $link->correction_case_id = CorrectionCaseFactory::new()->createOne(['company_id' => $link->company_id])->id;
                }
            }
        });
    }
}
