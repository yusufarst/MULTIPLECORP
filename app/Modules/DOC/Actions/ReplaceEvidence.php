<?php

namespace App\Modules\DOC\Actions;

use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Services\EvidenceRegistry;
use App\Modules\DOC\Services\EvidenceUse;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * `DOC ReplaceEvidence` — "Jadikan Versi Berlaku" (ROUTE-231; INT-231; NX-14; L-41; IP-16 step 4): a registered
 * version of an evidence made current, the second of a replacement's two steps, each with its own answer (IP-19). The
 * evidence, the anchors of the records it is linked to and what the participants lock held — data-dependent, re-read
 * under the locks —; the requirements, settlements and completion that depended on it re-evaluated in the same
 * transaction through `DocumentChangeParticipant` (BR-ADM-03; architecture challenge 20a). No duplicate lookup runs.
 */
final class ReplaceEvidence
{
    public const TYPE = 'DOC.ReplaceEvidence';

    public function __construct(
        private readonly EvidenceRegistry $registry,
        private readonly EvidenceUse $use,
        private readonly EffectiveCapabilities $capabilities,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, EvidenceDocument $evidence, ?string $companyPublicId, int $versionNo, ?string $reason): Outcome
    {
        $this->use->ensureVisible($this->capabilities->for($actorUserId), $evidence);
        $request = $this->registry->request($commandId, self::TYPE, $actorUserId, $evidence->company_id, $companyPublicId ?? 'POOL',
            ['evidence' => $evidence->public_id, 'version' => $versionNo, 'reason' => $reason]);

        return $this->registry->run($request, function (CommandContext $context) use ($evidence, $versionNo, $reason): CommandResult {
            $context->lock($this->use->locksFor($this->change($evidence)));
            // Data-dependent (§5 steps 3–4): the links and their anchors read again under the locks.
            $links = $this->change($evidence)->links;
            $context->require($this->use->locksFor($this->change($evidence)));
            $this->registry->ensureHandles($context, $evidence->evidence_type, $evidence->company_id);
            $row = EvidenceDocument::query()->findOrFail($evidence->id);
            $version = DB::table('evidence_document_versions')->where('evidence_document_id', $row->id)->where('version_no', $versionNo)->value('id');

            if (! is_numeric($version)) {
                throw Refused::rejected('NX-14', ['reason' => 'no_version']);
            }

            if ((int) $version === $row->current_version_id) {
                throw Refused::rejected('NX-14', ['reason' => 'current']);
            }

            $before = $row->current_version_id;
            $row->forceFill(['current_version_id' => (int) $version, 'lock_version' => $row->lock_version + 1])->save();

            $this->audit->record(
                action: 'DOC.EvidenceReplaced',
                authorityClass: AuthorityClass::Adm,
                actorUserId: $context->request->actorUserId,
                companyId: $row->company_id,
                entityType: 'evidence_document',
                entityId: $row->id,
                entityPublicId: $row->public_id,
                reason: $reason === null || trim($reason) === '' ? null : trim($reason),
                commandId: $context->request->commandId,
                change: AuditChange::updated(['current_version_id' => $before], ['current_version_id' => (int) $version]),
                metadata: ['version_no' => $versionNo, 'links' => count($links)],
            );
            $this->use->changed($context, $this->change($evidence, $before, (int) $version));

            return new CommandResult('evidence_document', $row->id);
        });
    }

    /** The replacement as a change, with the evidence's active links. */
    private function change(EvidenceDocument $evidence, ?int $from = null, ?int $to = null): DocumentChange
    {
        return new DocumentChange(DocumentChange::EVIDENCE_REPLACED, $evidence->id, $evidence->company_id, $evidence->evidence_type, $this->use->activeLinks($evidence->id), $from, $to);
    }
}
