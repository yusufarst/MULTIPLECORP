<?php

namespace App\Modules\DOC\Data;

/**
 * A change a `DocumentChangeParticipant` re-evaluates (L-41; ARCHITECTURE §3): an evidence document whose current
 * version was replaced, or whose link to a record was removed, or a generated document's issued version voided or
 * superseded by its revision (NX-12; AX-15), with the records its links name — each arc column and the record's id —
 * so a participant finds what depended on it without reading DOC's tables.
 */
final class DocumentChange
{
    public const EVIDENCE_REPLACED = 'EVIDENCE_REPLACED';

    public const EVIDENCE_UNLINKED = 'EVIDENCE_UNLINKED';

    /** A generated document's issued version voided (NX-12; TASK-029). */
    public const DOCUMENT_VOIDED = 'DOCUMENT_VOIDED';

    /** A generated document's issued version superseded by its revision's issue (AX-15; TASK-029). */
    public const DOCUMENT_REVISED = 'DOCUMENT_REVISED';

    /**
     * @param  list<array{link: int, target: string, id: int, role: string}>  $links  the active links the change concerns
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $evidenceId,
        public readonly ?int $companyId,
        public readonly string $evidenceType,
        public readonly array $links,
        public readonly ?int $previousVersionId = null,
        public readonly ?int $currentVersionId = null,
        public readonly ?int $documentId = null,
    ) {}

    /**
     * A generated document's change: no evidence — `evidenceId` 0, `evidenceType` its document type —, its
     * version's requirement links, the version changed and, for a revision, the version that superseded it.
     *
     * @param  list<array{link: int, target: string, id: int, role: string}>  $links
     */
    public static function ofDocument(string $kind, int $documentId, int $companyId, string $typeCode, array $links, int $previousVersionId, ?int $currentVersionId): self
    {
        return new self($kind, 0, $companyId, $typeCode, $links, $previousVersionId, $currentVersionId, $documentId);
    }

    public function isDocument(): bool
    {
        return $this->documentId !== null;
    }
}
