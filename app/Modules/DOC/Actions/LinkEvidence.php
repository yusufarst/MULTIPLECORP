<?php

namespace App\Modules\DOC\Actions;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceLink;
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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * `DOC LinkEvidence` (ROUTE-231; INT-231; NX-14): an evidence linked to a record or requirement in a role — company
 * evidence to its company's records by composite keys, pool evidence to the pool's only (C-58) —, the evidence and
 * the record's anchor locked (LK-29; LK-01 and the others of §7.3), the anchor re-read under the locks. A link never
 * widens visibility (PJ-08 row 95), and an evidence of a family the actor may not view answers as unknown (TM-55). A
 * reproduction may be linked as a client original, recorded as an ordinary link, and never satisfies it (FS-12;
 * GAP-034). No duplicate lookup runs here (H6-09).
 */
final class LinkEvidence
{
    public const TYPE = 'DOC.LinkEvidence';

    public function __construct(
        private readonly EvidenceRegistry $registry,
        private readonly EvidenceUse $use,
        private readonly EffectiveCapabilities $capabilities,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, EvidenceDocument $evidence, ?string $companyPublicId, string $target, int $targetId, string $targetPublicId, string $role): Outcome
    {
        $actor = $this->capabilities->for($actorUserId);
        $this->use->ensureVisible($actor, $evidence);
        $pool = $evidence->company_id === null;

        // A link never widens visibility: its record's family the actor's too (PJ-08 row 95).
        if (! isset(EvidenceUse::TARGETS[$target]) || ($pool && ! in_array($target, EvidenceUse::POOL_TARGETS, true))
            || ! EvidenceUse::targetVisible($actor, $target, $evidence->company_id)) {
            throw new ModelNotFoundException;
        }

        if (! $this->belongs($target, $targetId, $evidence->company_id)) {
            throw new ModelNotFoundException;
        }

        $link = [['target' => $target, 'id' => $targetId]];
        $request = $this->registry->request($commandId, self::TYPE, $actorUserId, $evidence->company_id, $companyPublicId ?? 'POOL',
            ['evidence' => $evidence->public_id, 'target' => $target, 'record' => $targetPublicId, 'role' => $role]);

        return $this->registry->run($request, function (CommandContext $context) use ($evidence, $link, $target, $targetId, $targetPublicId, $role): CommandResult {
            $context->lock($this->use->anchors($link)->add('evidence_documents', ['id' => $evidence->id]));
            $context->require($this->use->anchors($link));
            $this->registry->ensureHandles($context, $evidence->evidence_type, $evidence->company_id);
            $linked = DB::table('evidence_links')->where('evidence_document_id', $evidence->id)->where($target, $targetId)
                ->where('role', $role)->whereNull('unlinked_at')->exists();

            if ($linked) {
                throw Refused::rejected('NX-14', ['reason' => 'linked']);
            }

            $now = $context->now();
            $row = new EvidenceLink;
            $row->forceFill([
                'evidence_document_id' => $evidence->id,
                'evidence_scope' => $evidence->evidence_scope,
                'company_id' => $evidence->company_id,
                'role' => $role,
                $target => $targetId,
                'command_id' => $context->request->commandId,
                'command_line_no' => 1,
                'recorded_at' => $now,
                'recorded_by' => $context->request->actorUserId,
            ])->save();

            $this->audit->record(
                action: 'DOC.EvidenceLinked',
                authorityClass: AuthorityClass::Adm,
                actorUserId: $context->request->actorUserId,
                companyId: $evidence->company_id,
                entityType: 'evidence_document',
                entityId: $evidence->id,
                entityPublicId: $evidence->public_id,
                commandId: $context->request->commandId,
                change: AuditChange::created(['role' => $role, 'target' => $target, 'record' => $targetPublicId]),
            );

            return new CommandResult('evidence_document', $evidence->id);
        });
    }

    /**
     * C-58: company evidence links to a record of its own company — an import batch whose companies include it —, and
     * pool evidence to a pool-wide movement, an unattributed-loss case or an import batch, never a company's record.
     */
    private function belongs(string $target, int $targetId, ?int $companyId): bool
    {
        $owner = DB::table(EvidenceUse::TARGETS[$target][0])->where('id', $targetId)->first();

        if (! is_object($owner)) {
            return false;
        }

        if ($target === 'import_batch_id') {
            return $companyId === null || in_array((string) $companyId, explode(',', trim(is_string($owner->company_scope ?? null) ? $owner->company_scope : '', '{}')), true);
        }

        if ($target === 'unattributed_loss_case_id') {
            return $companyId === null;
        }

        $company = property_exists($owner, 'company_id') && is_numeric($owner->company_id) ? (int) $owner->company_id : null;

        return $company === $companyId;
    }
}
