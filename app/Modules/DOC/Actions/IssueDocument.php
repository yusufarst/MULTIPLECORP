<?php

namespace App\Modules\DOC\Actions;

use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Jobs\RenderDocument;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Services\DocumentAccess;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentPaper;
use App\Modules\DOC\Services\DocumentSubjects;
use App\Modules\DOC\Services\IssuerIdentity;
use App\Modules\ORG\Data\DrawnNumber;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Services\CompanyGuard;
use App\Modules\ORG\Services\NumberAllocator;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
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
 * `DOC IssueDocument` (ROUTE-222; INT-222; AX-15; SF-ISSUE; BD-06): a draft issued after its confirmation (IP-08) —
 * one transaction that re-reads, under the subject's anchors, the actor, the company where a new chain of a document
 * other than a collection document starts (C-54), and the chain at LK-30, that the version is still the DRAFT the page
 * confirmed (ST-01, ST-02), the record-bound precondition, the date (SF-DATE; ST-08) —; then draws the next number of
 * the company, type and business-date period last (LK-31; NM-01) — a BASE_SUFFIX revision keeps its predecessor's
 * number with the next suffix and draws none (NM-07) —, freezes the payload with the company's identity, the bank
 * destination by its typed account and the number (BR-DOC-01; BR-SN-01), writes the PENDING rendition that is the
 * durable record of the work owed (GAP-007) and its audit event; a revision supersedes its predecessor and hands the
 * change to `DocumentChangeParticipant` in the same transaction (L-41). The render is dispatched after the commit. A
 * second issue of one draft finds it ISSUED: CONFLICT with the issued number, consuming none (M-19).
 */
final class IssueDocument
{
    public const TYPE = 'DOC.IssueDocument';

    /** How a BASE_SUFFIX revision writes its number: the base number and its suffix (GAP-019 validates the wording). */
    public const SUFFIX_FORMAT = '%s-R%d';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly AuditRecorder $audit,
        private readonly DocumentSubjects $subjects,
        private readonly DocumentChain $chain,
        private readonly IssuerIdentity $identity,
        private readonly CompanyGuard $company,
        private readonly NumberAllocator $numbers,
    ) {}

    public function run(string $commandId, int $actorUserId, Document $document, int $lockVersion, string $businessDate, bool $prefilled, ?string $bankHandle): Outcome
    {
        $subject = DocumentSubject::of($document);
        $provider = $this->subjects->of($subject);
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $document->company_id, DocumentAccess::ISSUE,
            Fingerprint::of(self::TYPE, DocumentChain::companyKey($document->company_id), ['document' => $document->public_id, 'lock_version' => $lockVersion, 'business_date' => $businessDate, 'prefilled' => $prefilled, 'bank' => $bankHandle]));

        return $this->envelope->run($request, function (CommandContext $context) use ($document, $subject, $provider, $lockVersion, $businessDate, $prefilled, $bankHandle): CommandResult {
            $context->lock($this->locks($document, $subject, $provider->locks($subject)));
            DocumentAccess::ensureIssue($context->authority(), $document->document_type_code, $document->company_id);
            $draft = $this->chain->draftOf($document->id);

            if ($draft === null) {
                $issued = $this->chain->issuedOf($document->id);

                throw $issued === null ? Refused::conflict('ST-02', ['state' => 'VOIDED']) : Refused::conflict('BD-06', ['number' => $issued->formatted_number]);
            }

            // Data-dependent (§5 steps 3–4): the draft, its predecessor and their requirements read again under the locks.
            $context->require($this->locks($document, $subject, $provider->locks($subject)));

            if ($draft->lock_version !== $lockVersion) {
                throw Refused::conflict('ST-01');
            }

            if (! $this->chain->started($document->id) && ! in_array($document->document_type_code, DocumentPaper::COLLECTION_LAYOUTS, true)) {
                $this->company->ensureOpenForNewBusiness($context, $document->company_id);
            }

            BusinessDateRule::check($businessDate, $prefilled);
            $account = $this->identity->accountByHandle($document->company_id, $document->document_type_code, $bankHandle);
            $provider->precondition($context, $subject);
            $facts = $provider->facts($subject);
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

            return new CommandResult('document', $document->id);
        });
    }

    /**
     * AX-15's lock set: the subject's anchors, the company for a new chain that is no collection document (LK-06),
     * the chain at LK-30 with LK-01's projects of the draft and its predecessor, and what the participants lock for
     * the revision's change. The counter is the number's own last lock (LK-31).
     */
    private function locks(Document $document, DocumentSubject $subject, LockSet $anchors): LockSet
    {
        $draft = $this->chain->draftOf($document->id);
        $versions = $draft === null ? [] : array_values(array_filter([$draft->id, $draft->supersedes_version_id]));
        $set = $anchors->union($this->chain->locks($document, $versions));

        if (! $this->chain->started($document->id) && ! in_array($subject->typeCode, DocumentPaper::COLLECTION_LAYOUTS, true)) {
            $set = $set->union(CompanyGuard::locks($document->company_id));
        }

        if ($draft !== null && $draft->supersedes_version_id !== null) {
            $set = $set->union($this->chain->participantLocks($this->chain->change(DocumentChange::DOCUMENT_REVISED, $document, $draft->supersedes_version_id, $draft->id)));
        }

        return $set;
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
