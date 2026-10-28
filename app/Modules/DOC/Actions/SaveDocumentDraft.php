<?php

namespace App\Modules\DOC\Actions;

use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Services\DocumentAccess;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentPaper;
use App\Modules\DOC\Services\DocumentSubjects;
use App\Modules\DOC\Services\IssuerIdentity;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\BusinessDate;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * `DOC SaveDocumentDraft` (ROUTE-221; INT-221; NX-12; WF-DOC-01): a document's draft — editable and unnumbered, one per
 * chain (DATABASE §4.10) —, either prepared from its record's facts by the issuing page's "Terbitkan …" control, the
 * chain and its draft created on first use (FU-12) or the draft an earlier press left kept as it is, or saved on
 * PG-036 with its entered fields — the date and, where the layout states one, the bank destination — at the
 * `lock_version` the page read (ST-01; M-38). A draft writes no number, counter, payload or rendition (D-DS-20;
 * GAP-053). `documents.issue` with the subject family's view (PJ-15); the subject's anchors, the actor, then the chain
 * at LK-30. Each write an ADM audit event.
 */
final class SaveDocumentDraft
{
    public const PREPARE = 'DOC.PrepareDocumentDraft';

    public const SAVE = 'DOC.SaveDocumentDraft';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly AuditRecorder $audit,
        private readonly DocumentSubjects $subjects,
        private readonly DocumentChain $chain,
        private readonly IssuerIdentity $identity,
    ) {}

    /**
     * The draft of the subject's chain, prepared from its record: the chain and its draft created, the draft an earlier
     * press left kept, or — once issued — the document itself, which PG-036 opens (WF-DOC-01; `DIR-081` OB-02).
     */
    public function prepare(string $commandId, int $actorUserId, DocumentSubject $subject): Outcome
    {
        $provider = $this->subjects->of($subject);
        $request = new CommandRequest($commandId, self::PREPARE, $actorUserId, $subject->companyId, DocumentAccess::ISSUE,
            Fingerprint::of(self::PREPARE, DocumentChain::companyKey($subject->companyId), ['type' => $subject->typeCode, 'mode' => $subject->mode, 'records' => $subject->records]));

        return $this->envelope->run($request, function (CommandContext $context) use ($subject, $provider): CommandResult {
            $context->lock($provider->locks($subject));
            DocumentAccess::ensureIssue($context->authority(), $subject->typeCode, $subject->companyId);
            $document = $this->find($subject);

            if ($document !== null) {
                $draft = $this->chain->draftOf($document->id);
                $context->lock($this->chain->locks($document, $draft === null ? [] : [$draft->id]));

                if ($this->chain->draftOf($document->id) !== null || $this->chain->issuedOf($document->id) !== null) {
                    return new CommandResult('document', $document->id);
                }
            }

            $now = $context->now();

            if ($document === null) {
                $document = new Document;
                $document->forceFill([
                    'company_id' => $subject->companyId, 'document_type_code' => $subject->typeCode, 'mode' => $subject->mode, ...$subject->records,
                    'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId,
                ])->save();
            }

            $accounts = $this->identity->bankAccounts($subject->companyId);
            $draft = new DocumentVersion;
            $draft->forceFill([
                'document_id' => $document->id,
                'company_id' => $document->company_id,
                'document_type_code' => $document->document_type_code,
                'version_no' => DocumentChain::nextVersionNo($document->id),
                'state' => 'DRAFT',
                'business_date' => BusinessDate::of($now),
                'kuitansi_amount' => $subject->typeCode === 'DOC-05' ? $provider->facts($subject)->kuitansiAmount : null,
                'company_bank_account_id' => in_array($subject->typeCode, DocumentPaper::BANK_LAYOUTS, true) && $accounts !== [] ? $accounts[0]['id'] : null,
                'recorded_at' => $now,
                'recorded_by' => $context->request->actorUserId,
            ])->save();
            $this->record($context, 'DOC.DocumentDraftCreated', $document, $draft, AuditChange::created(['version_no' => $draft->version_no, 'business_date' => BusinessDate::of($now)]));

            return new CommandResult('document', $document->id);
        });
    }

    /**
     * The draft's entered fields saved at the version the page read (ST-01): its date — a chosen day taken as it is,
     * a prefilled one no longer today a CONFLICT carrying today (SF-DATE; ST-08) — and its bank destination by handle.
     */
    public function save(string $commandId, int $actorUserId, Document $document, int $lockVersion, string $businessDate, bool $prefilled, ?string $bankHandle): Outcome
    {
        $subject = DocumentSubject::of($document);
        $provider = $this->subjects->of($subject);
        $request = new CommandRequest($commandId, self::SAVE, $actorUserId, $document->company_id, DocumentAccess::ISSUE,
            Fingerprint::of(self::SAVE, DocumentChain::companyKey($document->company_id), ['document' => $document->public_id, 'lock_version' => $lockVersion, 'business_date' => $businessDate, 'prefilled' => $prefilled, 'bank' => $bankHandle]));

        return $this->envelope->run($request, function (CommandContext $context) use ($document, $subject, $provider, $lockVersion, $businessDate, $prefilled, $bankHandle): CommandResult {
            $draft = $this->chain->draftOf($document->id);
            $context->lock($provider->locks($subject)->union($this->chain->locks($document, $draft === null ? [] : [$draft->id])));
            DocumentAccess::ensureIssue($context->authority(), $document->document_type_code, $document->company_id);
            $draft = $this->chain->draftOf($document->id);

            if ($draft === null) {
                throw Refused::conflict('ST-02', ['state' => $this->chain->issuedOf($document->id) === null ? 'VOIDED' : 'ISSUED']);
            }

            $context->require($this->chain->locks($document, [$draft->id]));

            if ($draft->lock_version !== $lockVersion) {
                throw Refused::conflict('ST-01');
            }

            BusinessDateRule::check($businessDate, $prefilled);
            $account = $this->identity->accountByHandle($document->company_id, $document->document_type_code, $bankHandle);
            $before = ['business_date' => $draft->business_date?->format('Y-m-d'), 'bank_account' => $draft->company_bank_account_id];
            $after = ['business_date' => $businessDate, 'bank_account' => $account];

            if ($before !== $after) {
                $saved = DB::table('document_versions')->where('id', $draft->id)->where('state', 'DRAFT')->where('lock_version', $lockVersion)
                    ->update(['business_date' => $businessDate, 'company_bank_account_id' => $account, 'lock_version' => $lockVersion + 1, 'updated_at' => $context->now()]);

                if ($saved !== 1) {
                    throw Refused::conflict('ST-01');
                }

                $this->record($context, 'DOC.DocumentDraftSaved', $document, $draft, AuditChange::updated($before, $after));
            }

            return new CommandResult('document', $document->id);
        });
    }

    private function find(DocumentSubject $subject): ?Document
    {
        $query = Document::query()->where('company_id', $subject->companyId)->where('document_type_code', $subject->typeCode)->where('mode', $subject->mode);

        foreach (DocumentSubject::COLUMNS as $column) {
            isset($subject->records[$column]) ? $query->where($column, $subject->records[$column]) : $query->whereNull($column);
        }

        return $query->first();
    }

    private function record(CommandContext $context, string $action, Document $document, DocumentVersion $draft, AuditChange $change): void
    {
        $this->audit->record(
            action: $action,
            authorityClass: AuthorityClass::Adm,
            actorUserId: $context->request->actorUserId,
            companyId: $document->company_id,
            entityType: 'document_version',
            entityId: $draft->id,
            entityPublicId: $draft->public_id,
            commandId: $context->request->commandId,
            change: $change,
            metadata: ['document_type' => $document->document_type_code, 'version_no' => $draft->version_no],
        );
    }
}
