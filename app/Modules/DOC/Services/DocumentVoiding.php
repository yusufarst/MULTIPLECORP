<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * The void of an issued version (C-11; C-12; AC-12), inside its caller's command and its caller's locks: its row, rendition,
 * reason, actor and number kept, the number retired forever; never cascading to the business record (BR-CR-06; L-41) — the
 * requirements and completion that depended on the version are re-evaluated through `DocumentChangeParticipant`. A
 * revision's draft of the voided version has nothing left to revise and is removed with it. `VoidDocument` (ROUTE-224) runs it
 * in its own command; FIN's invoice void (AX-22) in its own, so the record and its documents commit in one transaction.
 */
final class DocumentVoiding
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly DocumentChain $chain,
    ) {}

    public function void(CommandContext $context, Document $document, DocumentVersion $issued, string $reason): void
    {
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
    }
}
