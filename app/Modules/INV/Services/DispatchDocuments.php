<?php

namespace App\Modules\INV\Services;

use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Services\DocumentAccess;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentRevising;
use App\Modules\DOC\Services\DocumentVoiding;
use App\Modules\INV\Data\DocumentDisposition;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;

/**
 * "Every row revises, voids or keeps (with reason) the record-bound documents it affects" (WORKFLOWS §8) as a dispatch correction meets it: the dispatch's issued Surat Jalan disposed in the correction's own transaction, an issued version's content staying immutable (C-11). Keeping needs only the correction's authority; a revision's draft or a void is `documents.correct`'s (CM-26). A draft has no snapshot to dispose: its facts are read live.
 */
final class DispatchDocuments
{
    private const NOTE = DispatchSubjects::NOTE;

    public function __construct(
        private readonly DocumentChain $chain,
        private readonly DocumentVoiding $voiding,
        private readonly DocumentRevising $revising,
    ) {}

    /** The chain and what a void's participants lock, when the dispatch has a Surat Jalan. */
    public function locks(int $movementId): LockSet
    {
        $document = $this->chain->ofDispatch($movementId, self::NOTE);

        if ($document === null) {
            return new LockSet;
        }

        $issued = $this->chain->issuedOf($document->id);
        $draft = $this->chain->draftOf($document->id);
        $set = $this->chain->locks($document, array_values(array_filter([$issued?->id, $draft?->id])));

        return $issued === null ? $set : $set->union($this->chain->participantLocks($this->chain->change(DocumentChange::DOCUMENT_VOIDED, $document, $issued->id, null)));
    }

    /**
     * The disposition carried out; what the correction's audit event records, null when nothing is issued.
     *
     * @return array{number: string, disposition: string, reason: string}|null
     */
    public function dispose(CommandContext $context, int $movementId, ?DocumentDisposition $disposition): ?array
    {
        $document = $this->chain->ofDispatch($movementId, self::NOTE);
        $issued = $document === null ? null : $this->chain->issuedOf($document->id);

        if ($document === null || $issued === null) {
            return null;
        }

        if ($disposition === null || ! in_array($disposition->choice, DocumentDisposition::CHOICES, true) || trim($disposition->reason) === '') {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'disposition']);
        }

        if ($disposition->choice !== 'KEEP') {
            $authority = $context->authority();

            if ($authority === null || ! DocumentAccess::mayCorrect($authority, self::NOTE, $document->company_id)) {
                throw Refused::rejected('AZ-01', ['reason' => 'document']);
            }
        }

        if ($disposition->choice === 'REVISE') {
            if ($this->chain->draftOf($document->id) !== null) {
                throw Refused::conflict('ST-02', ['state' => 'DRAFT']);
            }

            $this->revising->draft($context, $document, $issued, $disposition->reason);
        }

        if ($disposition->choice === 'VOID') {
            $this->voiding->void($context, $document, $issued, $disposition->reason);
        }

        return ['number' => (string) $issued->formatted_number, 'disposition' => $disposition->choice, 'reason' => trim($disposition->reason)];
    }
}
