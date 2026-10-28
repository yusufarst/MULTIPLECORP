<?php

namespace App\Modules\DOC\Actions;

use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Services\DocumentAccess;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentSubjects;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * `DOC VoidDocument` (ROUTE-224; INT-224; NX-12; CM-26): "Batalkan Dokumen" with its reason (IP-09) — the issued
 * version and its number retired forever, its row, rendition, reason, actor and link kept (C-11; C-12; AC-12), never
 * cascading to the business record (BR-CR-06; L-41): the requirements and completion that depended on the version are
 * re-evaluated inside the same transaction through `DocumentChangeParticipant` (BR-ADM-03; SF-REVAL; architecture
 * challenge 7b). A revision's draft of the voided version has nothing left to revise and is removed with it.
 * `documents.correct` with the family's view (ADM_PLUS); never a quotation (L-25) or an invoice layout (CM-23).
 */
final class VoidDocument
{
    public const TYPE = 'DOC.VoidDocument';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly AuditRecorder $audit,
        private readonly DocumentSubjects $subjects,
        private readonly DocumentChain $chain,
    ) {}

    /** @param  ?string  $versionId  the issued version the page showed and the actor confirmed to void (ST-02) */
    public function run(string $commandId, int $actorUserId, Document $document, string $reason, ?string $versionId = null): Outcome
    {
        $subject = DocumentSubject::of($document);
        $provider = $this->subjects->of($subject);
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $document->company_id, DocumentAccess::CORRECT,
            Fingerprint::of(self::TYPE, DocumentChain::companyKey($document->company_id), ['document' => $document->public_id, 'reason' => $reason, 'version' => $versionId]));

        return $this->envelope->run($request, function (CommandContext $context) use ($document, $subject, $provider, $reason, $versionId): CommandResult {
            $context->lock($this->locks($document, $provider->locks($subject)));
            DocumentAccess::ensureCorrect($context->authority(), $document->document_type_code, $document->company_id);

            if (! DocumentAccess::correctedHere($document->document_type_code)) {
                throw Refused::rejected($document->document_type_code === 'DOC-01' ? 'L-25' : 'CM-23', ['reason' => 'corrected_at_record']);
            }

            $issued = $this->chain->issuedOf($document->id);

            if ($issued === null) {
                throw Refused::conflict('ST-02', ['state' => 'VOIDED']);
            }

            $context->require($this->locks($document, $provider->locks($subject)));

            if ($versionId !== null && $issued->public_id !== $versionId) {
                throw Refused::conflict('ST-02', ['state' => 'SUPERSEDED']);
            }
            $now = $context->now();
            $draft = $this->chain->draftOf($document->id);

            if ($draft !== null && $draft->supersedes_version_id === $issued->id) {
                DB::table('document_versions')->where('id', $draft->id)->where('state', 'DRAFT')->delete();
            }

            $voided = DB::table('document_versions')->where('id', $issued->id)->where('state', 'ISSUED')->update([
                'state' => 'VOIDED', 'voided_by' => $context->request->actorUserId, 'voided_at' => $now, 'voided_reason' => trim($reason),
                'lock_version' => $issued->lock_version + 1, 'updated_at' => $now,
            ]);

            if ($voided !== 1) {
                throw Refused::conflict('ST-02', ['state' => 'VOIDED']);
            }

            $this->audit->record(
                action: 'DOC.DocumentVoided',
                authorityClass: AuthorityClass::AdmPlus,
                actorUserId: $context->request->actorUserId,
                companyId: $document->company_id,
                entityType: 'document_version',
                entityId: $issued->id,
                entityPublicId: $issued->public_id,
                reason: trim($reason),
                commandId: $context->request->commandId,
                change: AuditChange::updated(['state' => 'ISSUED'], ['state' => 'VOIDED']),
                linkage: AuditLinkage::make()->link(AuditRelation::Voids, 'document_version', $issued->public_id),
                metadata: ['document_type' => $document->document_type_code, 'version_no' => $issued->version_no, 'number' => $issued->formatted_number, 'revision_draft_removed' => $draft !== null && $draft->supersedes_version_id === $issued->id],
            );
            $this->chain->changed($context, $this->chain->change(DocumentChange::DOCUMENT_VOIDED, $document, $issued->id, null));

            return new CommandResult('document', $document->id);
        });
    }

    /** NX-12's lock set: the subject's anchors, the issued version's requirement projects and the chain, and what the participants lock. */
    private function locks(Document $document, LockSet $anchors): LockSet
    {
        $issued = $this->chain->issuedOf($document->id);
        $draft = $this->chain->draftOf($document->id);
        $set = $anchors->union($this->chain->locks($document, array_values(array_filter([$issued?->id, $draft?->id]))));

        return $issued === null ? $set : $set->union($this->chain->participantLocks($this->chain->change(DocumentChange::DOCUMENT_VOIDED, $document, $issued->id, null)));
    }
}
