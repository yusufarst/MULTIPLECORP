<?php

namespace App\Modules\DOC\Actions;

use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Data\LinkHandle;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Services\EvidenceRegistry;
use App\Modules\DOC\Services\EvidenceUse;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * `DOC UnlinkEvidence` (ROUTE-231; INT-231; NX-14; L-41): an evidence's link — named by its handle — removed with its
 * reason, set once and the row kept (DATABASE §19); the evidence, the record's anchor and what the participants lock
 * held, re-read under the locks; the requirements, settlements and completion that depended on it re-evaluated in the
 * same transaction through `DocumentChangeParticipant` (BR-ADM-03; architecture challenge 20a). No control deletes
 * evidence or a version.
 */
final class UnlinkEvidence
{
    public const TYPE = 'DOC.UnlinkEvidence';

    public function __construct(
        private readonly EvidenceRegistry $registry,
        private readonly EvidenceUse $use,
        private readonly EffectiveCapabilities $capabilities,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, EvidenceDocument $evidence, ?string $companyPublicId, string $handle, string $reason): Outcome
    {
        $actor = $this->capabilities->for($actorUserId);
        $this->use->ensureVisible($actor, $evidence);
        $request = $this->registry->request($commandId, self::TYPE, $actorUserId, $evidence->company_id, $companyPublicId ?? 'POOL',
            ['evidence' => $evidence->public_id, 'link' => $handle, 'reason' => $reason]);
        // CI-05: a replay answers its recorded outcome before the link its first execution removed is looked for.
        $replayed = $this->registry->replay($request);

        if ($replayed !== null) {
            return $replayed;
        }

        // PJ-08 row 95: a link to a record the actor may not see answers as unknown, whatever its evidence.
        if ($this->change($evidence, $handle, $actor)->links === []) {
            throw new ModelNotFoundException;
        }

        return $this->registry->run($request, function (CommandContext $context) use ($evidence, $handle, $reason, $actor): CommandResult {
            $context->lock($this->use->locksFor($this->change($evidence, $handle, $actor)));
            $authority = $this->registry->ensureHandles($context, $evidence->evidence_type, $evidence->company_id);
            // Data-dependent (§5 steps 3–4): the link and its anchor read again under the locks, by the re-read authority.
            $change = $this->change($evidence, $handle, $authority);
            $context->require($this->use->locksFor($change));
            $links = $change->links;

            if ($links === []) {
                throw Refused::rejected('NX-14', ['reason' => 'not_linked']);
            }

            $now = $context->now();
            DB::table('evidence_links')->whereIn('id', array_column($links, 'link'))
                ->update(['unlinked_at' => $now, 'unlinked_by' => $context->request->actorUserId, 'unlink_reason' => trim($reason)]);

            $this->audit->record(
                action: 'DOC.EvidenceUnlinked',
                authorityClass: AuthorityClass::Adm,
                actorUserId: $context->request->actorUserId,
                companyId: $evidence->company_id,
                entityType: 'evidence_document',
                entityId: $evidence->id,
                entityPublicId: $evidence->public_id,
                reason: trim($reason),
                commandId: $context->request->commandId,
                change: AuditChange::updated(['role' => $links[0]['role'], 'target' => $links[0]['target'], 'linked' => true], ['linked' => false]),
            );
            $this->use->changed($context, $change);

            return new CommandResult('evidence_document', $evidence->id);
        });
    }

    /** The unlink as a change: the evidence's active link the handle names, if the actor may see its record. */
    private function change(EvidenceDocument $evidence, string $handle, Authority $actor): DocumentChange
    {
        $links = array_values(array_filter($this->use->activeLinks($evidence->id), fn (array $link): bool => hash_equals(LinkHandle::of($link['link']), $handle)
            && EvidenceUse::targetVisible($actor, $link['target'], $evidence->company_id)));

        return new DocumentChange(DocumentChange::EVIDENCE_UNLINKED, $evidence->id, $evidence->company_id, $evidence->evidence_type, $links);
    }
}
