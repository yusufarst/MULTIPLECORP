<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;

/**
 * A revision's draft of an issued version (NX-12; CM-26), inside its caller's command and its caller's locks: a new version linked to the issued one it will supersede, which stays current and retrievable until the revision is issued (C-11; architecture challenge 7a). `ReviseDocument` (ROUTE-223) runs it in its own command; INV's dispatch corrections in theirs, so the record and its document commit in one transaction.
 */
final class DocumentRevising
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function draft(CommandContext $context, Document $document, DocumentVersion $issued, string $reason): DocumentVersion
    {
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

        return $draft;
    }
}
