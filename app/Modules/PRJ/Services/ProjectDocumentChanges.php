<?php

namespace App\Modules\PRJ\Services;

use App\Modules\DOC\Contracts\DocumentChangeParticipant;
use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Services\EvidenceUse;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use Illuminate\Support\Facades\DB;

/**
 * PRJ's part of DOC's `DocumentChangeParticipant` (ARCHITECTURE §3; L-41; architecture challenge 7b; SF-REVAL): a document void, a revision's issue or
 * an evidence replacement or unlink is material to every project it touches — the document's own project, or the projects of the records the evidence is
 * linked to —, so a completed one returns to ACTIVE inside DOC's transaction, one REVALIDATE transition per project linked to the change; a CANCELLED project
 * stays as it is (L-11). The projects are held at LK-01 before DOC's own anchors.
 */
final class ProjectDocumentChanges implements DocumentChangeParticipant
{
    /** WF-PRJ-03's collection documents for residual receivables. */
    private const COLLECTION = ['DOC-05', 'DOC-07', 'DOC-13'];

    public function __construct(private readonly Revalidation $revalidation) {}

    public function locks(DocumentChange $change): LockSet
    {
        $set = new LockSet;

        foreach (self::projects($change) as $project) {
            $set = $set->add('projects', ['id' => $project]);
        }

        return $set;
    }

    public function documentChanged(CommandContext $context, DocumentChange $change): void
    {
        // Always material: the documents predicate reads issued, unvoided and rendered versions (predicate 4), the administration predicate what
        // satisfies each item (predicate 5), the audit predicate the evidence present (predicate 10).
        $reference = $change->isDocument() ? DB::table('documents')->where('id', $change->documentId)->value('public_id') : DB::table('evidence_documents')->where('id', $change->evidenceId)->value('public_id');

        foreach (self::projects($change) as $project) {
            $this->revalidation->revalidate($context, $project, "DOC.{$change->kind}:".(is_string($reference) ? $reference : ''));
        }
    }

    /** @return list<int> the projects the change touches */
    private static function projects(DocumentChange $change): array
    {
        $projects = [];

        if ($change->isDocument()) {
            // A collection document — DOC-05 in either mode, DOC-07, DOC-13 — or an invoice's own is FIN's: its void or revision is a residual command or FIN's correction (L-11).
            $project = DB::table('documents')->where('id', $change->documentId)->whereNull('invoice_id')->whereNull('payment_id')->whereNotIn('document_type_code', self::COLLECTION)->value('project_id');
            $projects[] = is_numeric($project) ? (int) $project : 0;
        }

        foreach ($change->links as $link) {
            [$table, $anchor] = EvidenceUse::TARGETS[$link['target']] ?? [null, null];
            $found = match (true) {
                $link['target'] === 'project_id' => [$link['id']],
                $table !== null && $anchor === 'project' => [DB::table($table)->where('id', $link['id'])->value('project_id')],
                // A payment's proof is the audit predicate's input of every project its applications reach (predicate 10).
                $link['target'] === 'payment_id' => DB::table('payment_applications as a')->join('invoices as i', 'i.id', '=', 'a.invoice_id')->where('a.payment_id', $link['id'])->distinct()->pluck('i.project_id')->all(),
                $link['target'] === 'deduction_settlement_id' => DB::table('deduction_settlements as s')->join('invoices as i', 'i.id', '=', 's.invoice_id')->where('s.id', $link['id'])->pluck('i.project_id')->all(),
                $link['target'] === 'correction_case_id' => DB::table('correction_cases')->where('id', $link['id'])->selectRaw('coalesce(project_id, causal_project_id) as project')->pluck('project')->all(),
                default => [],
            };

            foreach ($found as $project) {
                $projects[] = is_numeric($project) ? (int) $project : 0;
            }
        }

        return array_values(array_unique(array_filter($projects, fn (int $id): bool => $id > 0)));
    }
}
