<?php

namespace App\Modules\FIN\Services;

use App\Modules\DOC\Contracts\DocumentChangeParticipant;
use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * The settlements' side of an evidence replacement (L-41; C-18; ARCHITECTURE §3; architecture challenge 20a), inside DOC's transaction: the settlements citing the evidence read under its guard, what they cite held to its proven
 * amount, each re-evaluated on the Owner's review trail with the version it now stands on, or the replacement refused. A replacement never moves a citation by itself: a wrong settlement is corrected by its contra (CM-22).
 */
final class SettlementDocumentChanges implements DocumentChangeParticipant
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function locks(DocumentChange $change): LockSet
    {
        if ($change->kind !== DocumentChange::EVIDENCE_REPLACED) {
            return new LockSet;
        }

        $citing = self::citing($change->evidenceId);

        if ($citing === []) {
            return new LockSet;
        }

        $set = EvidenceGuard::locks($change->evidenceId);

        foreach ($citing as $id) {
            $set = $set->add('deduction_settlements', ['id' => $id]);
        }

        return $set;
    }

    public function documentChanged(CommandContext $context, DocumentChange $change): void
    {
        if ($change->kind !== DocumentChange::EVIDENCE_REPLACED) {
            return;
        }

        $settlements = self::citing($change->evidenceId);

        if ($settlements === []) {
            return;
        }

        $balance = DB::selectOne('select proven_amount, cited_amount from evidence_balances where evidence_document_id = ?', [$change->evidenceId]);

        if (is_object($balance) && Money::of(Row::text($balance, 'cited_amount'))->compareTo(Money::of(Row::text($balance, 'proven_amount'))) > 0) {
            throw Refused::rejected('C-18', ['reason' => 'above_evidence', 'uncited' => '0.00']);
        }

        foreach ($settlements as $id) {
            $this->audit->record(action: 'FIN.SettlementEvidenceReplaced', authorityClass: AuthorityClass::AdmPlus, actorUserId: $context->request->actorUserId, companyId: $change->companyId, entityType: 'deduction_settlement', entityId: $id, entityPublicId: RowHandle::of('deduction_settlements', $id),
                reason: null, commandId: $context->request->commandId, change: AuditChange::updated(['version' => $change->previousVersionId], ['version' => $change->currentVersionId]), metadata: ['evidence' => RowHandle::of('evidence_documents', $change->evidenceId)]);
        }
    }

    /** @return list<int> the settlements citing the evidence, a residual keeping its citation (§11.4) */
    private static function citing(int $evidenceId): array
    {
        $ids = [];

        foreach (DB::table('deduction_settlements')->where('evidence_document_id', $evidenceId)->whereIn('state', ['ACTIVE', 'RESIDUAL'])->orderBy('id')->pluck('id') as $id) {
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }
}
