<?php

namespace App\Modules\DOC\Actions;

use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Jobs\RenderDocument;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Services\DocumentAccess;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentIssuing;
use App\Modules\DOC\Services\DocumentSubjects;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;

/**
 * `DOC IssueDocument` (ROUTE-222; INT-222; AX-15; SF-ISSUE; BD-06): a draft issued after its confirmation (IP-08) —
 * one transaction that re-reads, under the subject's anchors, the actor, the company where an issue other than a revision's
 * starts new business with a document other than a collection document (C-54), and the chain at LK-30, that the version is still the DRAFT the page
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

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly DocumentSubjects $subjects,
        private readonly DocumentIssuing $issuing,
    ) {}

    /**
     * @param  ?string  $factsDigest  the digest of the facts the confirmed paper composed (DocumentDetail), or null where no paper was shown
     * @param  ?string  $draftId  the draft version the page showed: another draft of the chain — a revision's made meanwhile — is stale (ST-01)
     */
    public function run(string $commandId, int $actorUserId, Document $document, int $lockVersion, string $businessDate, bool $prefilled, ?string $bankHandle, ?string $factsDigest = null, ?string $draftId = null): Outcome
    {
        $subject = DocumentSubject::of($document);
        $provider = $this->subjects->of($subject);
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $document->company_id, DocumentAccess::ISSUE,
            Fingerprint::of(self::TYPE, DocumentChain::companyKey($document->company_id), ['document' => $document->public_id, 'lock_version' => $lockVersion, 'business_date' => $businessDate, 'prefilled' => $prefilled, 'bank' => $bankHandle, 'facts' => $factsDigest, 'draft' => $draftId]));

        return $this->envelope->run($request, function (CommandContext $context) use ($document, $subject, $provider, $lockVersion, $businessDate, $prefilled, $bankHandle, $factsDigest, $draftId): CommandResult {
            $context->lock($this->issuing->locks($document, $subject, $provider->locks($subject)));

            if (! DocumentAccess::issuedByDocumentRoute($document->document_type_code, $document->mode)) {
                throw Refused::rejected('WF-QUO-01', ['reason' => 'issued_at_record']);
            }

            self::render($context, $this->issuing->issue($context, $document, $subject, $provider, $lockVersion, $businessDate, $prefilled, $bankHandle, $factsDigest, $draftId));

            return new CommandResult('document', $document->id);
        });
    }

    /** SF-RENDER: the issued version's PENDING rendition handed to the render job once the issue commits (IP-12). */
    public static function render(CommandContext $context, DocumentVersion $version): void
    {
        $rendition = DocumentRendition::query()->where('document_version_id', $version->id)->orderByDesc('attempt_no')->value('id');

        if (is_int($rendition)) {
            $context->afterCommit(fn () => RenderDocument::dispatch($rendition));
        }
    }
}
