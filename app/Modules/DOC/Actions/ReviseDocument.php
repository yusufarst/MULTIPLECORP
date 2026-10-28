<?php

namespace App\Modules\DOC\Actions;

use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Services\DocumentAccess;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentSubjects;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;

/**
 * `DOC ReviseDocument` (ROUTE-223; INT-223; NX-12; CM-26): "Revisi" with its reason (IP-09) — the revision's draft, a
 * new version linked to the issued one it will supersede, which stays current and retrievable with its rendition until
 * the revision is issued (C-11; architecture challenge 7a). Issuing it is AX-15 (ROUTE-222), NEW_NUMBER or BASE_SUFFIX
 * as the company's scheme states (NM-07). `documents.correct` with the family's view (ADM_PLUS); a quotation is revised
 * through its own revision and an invoice layout through the invoice, never here (L-25; CM-23). The subject's anchors,
 * the issued version's requirement projects at LK-01, the chain at LK-30; an ADM_PLUS audit event with the reason.
 */
final class ReviseDocument
{
    public const TYPE = 'DOC.ReviseDocument';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly AuditRecorder $audit,
        private readonly DocumentSubjects $subjects,
        private readonly DocumentChain $chain,
    ) {}

    /** @param  ?string  $versionId  the issued version the page showed and the actor confirmed to revise (ST-02) */
    public function run(string $commandId, int $actorUserId, Document $document, string $reason, ?string $versionId = null): Outcome
    {
        $subject = DocumentSubject::of($document);
        $provider = $this->subjects->of($subject);
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $document->company_id, DocumentAccess::CORRECT,
            Fingerprint::of(self::TYPE, DocumentChain::companyKey($document->company_id), ['document' => $document->public_id, 'reason' => $reason, 'version' => $versionId]));

        return $this->envelope->run($request, function (CommandContext $context) use ($document, $subject, $provider, $reason, $versionId): CommandResult {
            $issued = $this->chain->issuedOf($document->id);
            $context->lock($provider->locks($subject)->union($this->chain->locks($document, $issued === null ? [] : [$issued->id])));
            DocumentAccess::ensureCorrect($context->authority(), $document->document_type_code, $document->company_id);

            if (! DocumentAccess::correctedHere($document->document_type_code)) {
                throw Refused::rejected($document->document_type_code === 'DOC-01' ? 'L-25' : 'CM-23', ['reason' => 'corrected_at_record']);
            }

            $issued = $this->chain->issuedOf($document->id);

            if ($issued === null) {
                throw Refused::rejected('NX-12', ['reason' => 'not_issued']);
            }

            $context->require($this->chain->locks($document, [$issued->id]));

            if ($versionId !== null && $issued->public_id !== $versionId) {
                throw Refused::conflict('ST-02', ['state' => 'SUPERSEDED']);
            }

            if ($this->chain->draftOf($document->id) !== null) {
                throw Refused::conflict('ST-02', ['state' => 'DRAFT']);
            }

            $now = $context->now();
            $draft = new DocumentVersion;
            $draft->forceFill([
                'document_id' => $document->id,
                'company_id' => $document->company_id,
                'document_type_code' => $document->document_type_code,
                'version_no' => DocumentChain::nextVersionNo($document->id),
                'state' => 'DRAFT',
                'business_date' => BusinessDate::of($now),
                'kuitansi_amount' => $issued->kuitansi_amount,
                'company_bank_account_id' => $issued->company_bank_account_id,
                'supersedes_version_id' => $issued->id,
                'recorded_at' => $now,
                'recorded_by' => $context->request->actorUserId,
            ])->save();

            $this->audit->record(
                action: 'DOC.DocumentRevisionDrafted',
                authorityClass: AuthorityClass::AdmPlus,
                actorUserId: $context->request->actorUserId,
                companyId: $document->company_id,
                entityType: 'document_version',
                entityId: $draft->id,
                entityPublicId: $draft->public_id,
                reason: trim($reason),
                commandId: $context->request->commandId,
                change: AuditChange::created(['version_no' => $draft->version_no, 'supersedes' => $issued->formatted_number]),
                linkage: AuditLinkage::make()->link(AuditRelation::Revises, 'document_version', $issued->public_id),
                metadata: ['document_type' => $document->document_type_code, 'version_no' => $draft->version_no],
            );

            return new CommandResult('document', $document->id);
        });
    }
}
