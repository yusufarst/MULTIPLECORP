<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Contracts\DocumentChangeParticipant;
use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\IAM\Data\Authority;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Where an evidence is used and what NX-14 locks for it (L-41; CONCURRENCY_IDEMPOTENCY §7.3): its links' arc of
 * DATABASE §4.10 — company evidence to its company's records by composite keys, pool evidence to the pool's records
 * only (C-58) —, the anchor each linked record is locked by — its project at LK-01, its import batch, correction case
 * or unattributed-loss case, purchase, payment or settlement, or money-fact guard —, whether it is linked or cited, so
 * a new version becomes current only through a replacement (NX-07), and the `DocumentChangeParticipant`s a replacement
 * or an unlink reaches inside DOC's transaction (ARCHITECTURE §3).
 */
final class EvidenceUse
{
    /** Each link target: its table, and how its anchor is locked — its project, its own row, or its money-fact guard. */
    public const TARGETS = [
        'project_id' => ['projects', 'self'],
        'commercial_confirmation_id' => ['commercial_confirmations', 'project'],
        'purchase_id' => ['purchases', 'self'],
        'stock_movement_id' => ['stock_movements', 'project'],
        'delivery_id' => ['deliveries', 'project'],
        'dropship_confirmation_id' => ['dropship_confirmations', 'project'],
        'service_handover_id' => ['service_handovers', 'project'],
        'payment_id' => ['payments', 'self'],
        'other_cash_receipt_id' => ['other_cash_receipts', 'guard'],
        'disbursement_id' => ['disbursements', 'guard'],
        'expense_id' => ['expenses', 'guard'],
        'deduction_settlement_id' => ['deduction_settlements', 'self'],
        'receivable_writeoff_id' => ['receivable_writeoffs', 'guard'],
        'correction_case_id' => ['correction_cases', 'self'],
        'admin_requirement_id' => ['admin_requirements', 'project'],
        'unattributed_loss_case_id' => ['unattributed_loss_cases', 'self'],
        'import_batch_id' => ['import_batches', 'self'],
    ];

    /** The pool's records (C-58): a pool-wide movement, an unattributed-loss case, an import batch. */
    public const POOL_TARGETS = ['stock_movement_id', 'unattributed_loss_case_id', 'import_batch_id'];

    public const ROLES = ['PRIMARY_PROOF', 'SUPPORTING', 'CLIENT_ORIGINAL', 'REVIEWED'];

    public function __construct(private readonly Container $container) {}

    /** PJ-08: evidence the viewer may not see answers as unknown (AZ-11; TM-55). */
    public function ensureVisible(Authority $viewer, EvidenceDocument $evidence): void
    {
        if (! EvidenceTypes::mayView($viewer, $evidence->evidence_type, $evidence->company_id)) {
            throw (new ModelNotFoundException)->setModel(EvidenceDocument::class, [$evidence->public_id]);
        }
    }

    /** Linked to a record, or cited by an amount, a statement line among them — read under the evidence's lock. */
    public function inUse(int $evidenceId): bool
    {
        return DB::table('evidence_links')->where('evidence_document_id', $evidenceId)->whereNull('unlinked_at')->exists()
            || DB::table('evidence_balances')->where('evidence_document_id', $evidenceId)->whereRaw('(cited_amount > 0 or expensed_amount > 0)')->exists()
            || DB::table('bank_statement_lines')->where('statement_evidence_id', $evidenceId)->where('state', 'ACTIVE')->exists();
    }

    /**
     * The evidence's active links, each its arc column, record and role.
     *
     * @return list<array{link: int, target: string, id: int, role: string}>
     */
    public function activeLinks(int $evidenceId): array
    {
        $links = [];

        foreach (DB::table('evidence_links')->where('evidence_document_id', $evidenceId)->whereNull('unlinked_at')->orderBy('id')->get() as $row) {
            $values = (array) $row;

            foreach (array_keys(self::TARGETS) as $column) {
                if (is_numeric($values[$column] ?? null) && is_numeric($values['id'] ?? null)) {
                    $links[] = ['link' => (int) $values['id'], 'target' => $column, 'id' => (int) $values[$column], 'role' => is_string($values['role'] ?? null) ? $values['role'] : ''];
                }
            }
        }

        return $links;
    }

    /**
     * The anchors NX-14 locks for the linked records — data-dependent, read again under the locks (§5 steps 3–4).
     *
     * @param  list<array{target: string, id: int}>  $links
     */
    public function anchors(array $links): LockSet
    {
        $set = new LockSet;

        foreach ($links as $link) {
            [$table, $anchor] = self::TARGETS[$link['target']];

            if ($anchor === 'self') {
                $set = $set->add($table, ['id' => $link['id']]);
            } elseif ($anchor === 'guard') {
                $set = $set->add('money_fact_balances', [$link['target'] => $link['id']]);
            } else {
                $project = DB::table($table)->where('id', $link['id'])->value('project_id');

                if (is_numeric($project)) {
                    $set = $set->add('projects', ['id' => (int) $project]);
                }
            }
        }

        return $set;
    }

    /** NX-14's lock set of a change: the evidence at LK-29, its links' anchors and what the participants lock. */
    public function locksFor(DocumentChange $change): LockSet
    {
        return $this->anchors($change->links)->union($this->participantLocks($change))->add('evidence_documents', ['id' => $change->evidenceId]);
    }

    /** The participants' own locks for the change, before DOC takes its locks. */
    public function participantLocks(DocumentChange $change): LockSet
    {
        $set = new LockSet;

        foreach ($this->participants() as $participant) {
            $set = $set->union($participant->locks($change));
        }

        return $set;
    }

    /** L-41: what depended on the evidence re-evaluated in DOC's transaction, under the locks the change took. */
    public function changed(CommandContext $context, DocumentChange $change): void
    {
        foreach ($this->participants() as $participant) {
            $participant->documentChanged($context, $change);
        }
    }

    /** @return list<DocumentChangeParticipant> */
    private function participants(): array
    {
        $participants = [];

        foreach ($this->container->tagged(DocumentChangeParticipant::TAG) as $participant) {
            if ($participant instanceof DocumentChangeParticipant) {
                $participants[] = $participant;
            }
        }

        return $participants;
    }
}
