<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Contracts\DocumentSubjectProvider;
use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Jobs\RenderDocument;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\ORG\Data\DrawnNumber;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Services\CompanyGuard;
use App\Modules\ORG\Services\NumberAllocator;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\BusinessDate;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * AX-15's issue (SF-ISSUE; BD-06) inside its caller's command (TASK-029, shared with TASK-033): the draft re-read under
 * the subject's anchors and the chain at LK-30, the actor, the company where the issue starts new business (C-54), the
 * date (SF-DATE; ST-08), the record-bound precondition; the number drawn last (LK-31; NM-01) — a BASE_SUFFIX revision
 * keeping its predecessor's number with the next suffix (NM-07) —; the payload frozen with the company's identity, the
 * bank destination and the number (BR-DOC-01; BR-SN-01); the PENDING rendition and its audit; a revision superseding
 * its predecessor and handing the change to `DocumentChangeParticipant` (L-41). `IssueDocument` (ROUTE-222) runs it in
 * its own command; an issuing module whose record changes with the issue — PRJ's quotation revision becoming *Terkirim*
 * (ROUTE-155) — runs it in its own, so both commit in one transaction.
 */
final class DocumentIssuing
{
    /** How a BASE_SUFFIX revision writes its number: the base number and its suffix (GAP-019 validates the wording). */
    public const SUFFIX_FORMAT = '%s-R%d';

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly DocumentChain $chain,
        private readonly IssuerIdentity $identity,
        private readonly CompanyGuard $company,
        private readonly NumberAllocator $numbers,
    ) {}

    /**
     * AX-15's lock set: the subject's anchors, the company for a new chain that is no collection document (LK-06),
     * the chain at LK-30 with LK-01's projects of the draft and its predecessor, and what the participants lock for
     * the revision's change. The counter is the number's own last lock (LK-31).
     */
    public function locks(Document $document, DocumentSubject $subject, LockSet $anchors): LockSet
    {
        $draft = $this->chain->draftOf($document->id);
        $versions = $draft === null ? [] : array_values(array_filter([$draft->id, $draft->supersedes_version_id]));
        $set = $anchors->union($this->chain->locks($document, $versions));

        if ($draft !== null && DocumentChain::newBusiness($draft, $subject->typeCode)) {
            $set = $set->union(CompanyGuard::locks($document->company_id));
        }

        if ($draft !== null && $draft->supersedes_version_id !== null) {
            $set = $set->union($this->chain->participantLocks($this->chain->change(DocumentChange::DOCUMENT_REVISED, $document, $draft->supersedes_version_id, $draft->id)));
        }

        return $set;
    }

    /**
     * The subject's chain with a DRAFT to issue, for an issuing module that issues at its record (a quotation's revision;
     * WF-QUO-01): the chain created on first use (FU-12) with its first draft, a draft left open kept, or — once a version
     * is issued — a new draft revising it, so the issue supersedes the predecessor (C-11). The caller holds the subject's
     * anchors and has read no clock yet: the chain is locked here at LK-30, before it. Run in its own command before the
     * issue's, so the issue locks rows that exist (CONCURRENCY_IDEMPOTENCY §5).
     */
    public function draftFor(CommandContext $context, DocumentSubject $subject): Document
    {
        $document = $this->chainOf($subject);

        if ($document === null) {
            $document = new Document;
            $document->forceFill([
                'company_id' => $subject->companyId, 'document_type_code' => $subject->typeCode, 'mode' => $subject->mode, ...$subject->records,
                'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_by' => $context->request->actorUserId,
            ])->save();
        }

        $issued = $this->chain->issuedOf($document->id);
        $draft = $this->chain->draftOf($document->id);
        $context->lock($this->chain->locks($document, array_values(array_filter([$draft?->id, $issued?->id]))));

        if ($this->chain->draftOf($document->id) !== null) {
            return $document;
        }

        $now = $context->now();

        if ($issued !== null) {
            $this->revisionDraft($context, $document, $issued);

            return $document;
        }

        $draft = new DocumentVersion;
        $draft->forceFill([
            'document_id' => $document->id,
            'company_id' => $document->company_id,
            'document_type_code' => $document->document_type_code,
            'version_no' => DocumentChain::nextVersionNo($document->id),
            'state' => 'DRAFT',
            'business_date' => BusinessDate::of($now),
            'recorded_at' => $now,
            'recorded_by' => $context->request->actorUserId,
        ])->save();

        return $document;
    }

    /** The subject's chain, or null before its first draft. */
    public function chainOf(DocumentSubject $subject): ?Document
    {
        $query = Document::query()->where('company_id', $subject->companyId)->where('document_type_code', $subject->typeCode)->where('mode', $subject->mode);

        foreach (DocumentSubject::COLUMNS as $column) {
            isset($subject->records[$column]) ? $query->where($column, $subject->records[$column]) : $query->whereNull($column);
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * A new DRAFT version of the chain revising its issued one — the predecessor the issue will supersede — for an
     * issuing module that revises at its record (a quotation's next revision; L-25), under the chain's lock.
     */
    public function revisionDraft(CommandContext $context, Document $document, DocumentVersion $issued): DocumentVersion
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

        return $draft;
    }

    /**
     * Issues the chain's draft, the caller holding {@see self::locks()}; the draft's version, now ISSUED.
     *
     * @param  ?string  $factsDigest  the digest of the facts the confirmed paper composed, or null where no paper was shown
     * @param  ?string  $draftId  the draft version the page showed: another draft of the chain is stale (ST-01)
     * @param  bool  $authorized  whether the calling command already checked its own route's capability — ROUTE-155's `quotation.manage` (PJ-15) —, PG-036's issue checked here otherwise
     *
     * @throws Refused BD-06 for a draft already issued, ST-01 for a stale one, the precondition's and the numbering's refusals
     */
    public function issue(CommandContext $context, Document $document, DocumentSubject $subject, DocumentSubjectProvider $provider, ?int $lockVersion, string $businessDate,
        bool $prefilled, ?string $bankHandle, ?string $factsDigest = null, ?string $draftId = null, bool $authorized = false): DocumentVersion
    {
        if (! $authorized) {
            DocumentAccess::ensureIssue($context->authority(), $document->document_type_code, $document->company_id);
        }

        $draft = $this->chain->draftOf($document->id);

        if ($draft === null) {
            $issued = $this->chain->issuedOf($document->id);

            throw $issued === null ? Refused::conflict('ST-02', ['state' => 'VOIDED']) : Refused::conflict('BD-06', ['number' => $issued->formatted_number]);
        }

        // Data-dependent (§5 steps 3–4): the draft, its predecessor and their requirements read again under the locks.
        $context->require($this->locks($document, $subject, $provider->locks($subject)));
        $lockVersion ??= $draft->lock_version;

        if ($draft->lock_version !== $lockVersion || ($draftId !== null && $draft->public_id !== $draftId)) {
            throw Refused::conflict('ST-01');
        }

        if (DocumentChain::newBusiness($draft, $document->document_type_code)) {
            $this->company->ensureOpenForNewBusiness($context, $document->company_id);
        }

        BusinessDateRule::check($businessDate, $prefilled);
        $account = $this->identity->accountByHandle($document->company_id, $document->document_type_code, $bankHandle);
        $provider->precondition($context, $subject);
        $facts = $provider->facts($subject);

        // Architecture challenge 15a: the issued paper is the one previewed — a record changed since, a CONFLICT to review.
        if ($factsDigest !== null && ! hash_equals(DocumentChain::factsDigest($facts), $factsDigest)) {
            throw Refused::conflict('ST-01', ['reason' => 'facts']);
        }

        $predecessor = $draft->supersedes_version_id === null ? null : DocumentVersion::query()->find($draft->supersedes_version_id);
        $number = $this->number($context, $document, $predecessor, $businessDate);
        $now = $context->now();

        $issuer = $this->identity->of($document->company_id);
        $paper = DocumentPaper::compose($document->document_type_code, $document->mode, $facts, $issuer, $this->identity->bankAccount($document->company_id, $account), $businessDate, $number['formatted'], false);
        $payload = ['paper' => $paper, 'assets' => array_map(fn (?array $asset): ?array => $asset === null ? null : ['file' => $asset['file'], 'sha256' => $asset['sha256'], 'mime_type' => $asset['mime_type']], $issuer['assets'])];

        if ($predecessor !== null && $predecessor->state === 'ISSUED') {
            // C-11: one ISSUED version per chain, and a SUPERSEDED one only beside its issued successor — the second
            // a deferred check, held to the commit's `set constraints all immediate` whatever an earlier statement set.
            $context->connection->statement('set constraints document_versions_c11_superseded_by_successor deferred');
            DB::table('document_versions')->where('id', $predecessor->id)->where('state', 'ISSUED')
                ->update(['state' => 'SUPERSEDED', 'lock_version' => $predecessor->lock_version + 1, 'updated_at' => $now]);
        }

        $issued = DB::table('document_versions')->where('id', $draft->id)->where('state', 'DRAFT')->where('lock_version', $lockVersion)->update([
            'state' => 'ISSUED',
            'business_date' => $businessDate,
            'numbering_scheme_id' => $number['scheme'],
            'period_key' => $number['period'],
            'sequence_no' => $number['sequence'],
            'revision_suffix' => $number['suffix'],
            'formatted_number' => $number['formatted'],
            'quotation_revision_id' => $facts->renders['quotation_revision_id'] ?? null,
            'invoice_version_id' => $facts->renders['invoice_version_id'] ?? null,
            'kuitansi_amount' => $document->document_type_code === 'DOC-05' ? $facts->kuitansiAmount : null,
            'company_bank_account_id' => $account,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'payload_sha256' => DocumentChain::checksum($payload),
            'issued_by' => $context->request->actorUserId,
            'issue_command_id' => $context->request->commandId,
            'lock_version' => $lockVersion + 1,
            'updated_at' => $now,
        ]);

        if ($issued !== 1) {
            throw Refused::conflict('ST-01');
        }

        $rendition = new DocumentRendition;
        $rendition->forceFill(['document_version_id' => $draft->id, 'attempt_no' => 1, 'state' => 'PENDING', 'recorded_at' => $now])->save();
        $linkage = $predecessor === null ? null : AuditLinkage::make()->link(AuditRelation::Revises, 'document_version', $predecessor->public_id);
        $this->audit->record(
            action: 'DOC.DocumentIssued',
            authorityClass: AuthorityClass::Adm,
            actorUserId: $context->request->actorUserId,
            companyId: $document->company_id,
            entityType: 'document_version',
            entityId: $draft->id,
            entityPublicId: $draft->public_id,
            commandId: $context->request->commandId,
            change: AuditChange::updated(['state' => 'DRAFT'], ['state' => 'ISSUED', 'number' => $number['formatted']]),
            linkage: $linkage,
            businessDate: $businessDate,
            metadata: ['document_type' => $document->document_type_code, 'version_no' => $draft->version_no, 'number' => $number['formatted']],
        );

        if ($predecessor !== null) {
            $this->chain->changed($context, $this->chain->change(DocumentChange::DOCUMENT_REVISED, $document, $predecessor->id, $draft->id));
        }

        $renditionId = $rendition->id;
        $context->afterCommit(fn () => RenderDocument::dispatch($renditionId));

        return DocumentVersion::query()->findOrFail($draft->id);
    }

    /**
     * The version's number: a BASE_SUFFIX revision keeps its predecessor's base number with the next suffix under LK-30
     * and draws no counter; every other issue draws the next number of its business-date period, last (NM-01; NM-07).
     *
     * @return array{scheme: int, period: string, sequence: int, suffix: int, formatted: string}
     */
    private function number(CommandContext $context, Document $document, ?DocumentVersion $predecessor, string $businessDate): array
    {
        $format = $predecessor === null || $predecessor->numbering_scheme_id === null ? null
            : DB::table('numbering_schemes')->where('id', $predecessor->numbering_scheme_id)->value('revision_format');

        if ($predecessor !== null && $format === 'BASE_SUFFIX' && $predecessor->period_key !== null && $predecessor->sequence_no !== null) {
            $base = DocumentVersion::query()->where('document_id', $document->id)->where('period_key', $predecessor->period_key)
                ->where('sequence_no', $predecessor->sequence_no)->where('revision_suffix', 0)->value('formatted_number');
            $suffix = $predecessor->revision_suffix + 1;

            return [
                'scheme' => (int) $predecessor->numbering_scheme_id,
                'period' => $predecessor->period_key,
                'sequence' => $predecessor->sequence_no,
                'suffix' => $suffix,
                'formatted' => sprintf(self::SUFFIX_FORMAT, is_string($base) ? $base : (string) $predecessor->formatted_number, $suffix),
            ];
        }

        $drawn = $this->numbers->draw($context, $document->company_id, SequenceType::from($document->document_type_code), $businessDate);

        return self::drawn($drawn);
    }

    /** @return array{scheme: int, period: string, sequence: int, suffix: int, formatted: string} */
    private static function drawn(DrawnNumber $drawn): array
    {
        return ['scheme' => $drawn->schemeId, 'period' => $drawn->periodKey, 'sequence' => $drawn->sequenceNo, 'suffix' => 0, 'formatted' => $drawn->formatted];
    }
}
