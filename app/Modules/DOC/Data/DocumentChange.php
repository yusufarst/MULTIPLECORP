<?php

namespace App\Modules\DOC\Data;

/**
 * A change a `DocumentChangeParticipant` re-evaluates (L-41; ARCHITECTURE §3): an evidence document whose current
 * version was replaced, or whose link to a record was removed, with the records its links name — each arc column and
 * the record's id — so a participant finds what depended on it without reading DOC's tables.
 */
final class DocumentChange
{
    public const EVIDENCE_REPLACED = 'EVIDENCE_REPLACED';

    public const EVIDENCE_UNLINKED = 'EVIDENCE_UNLINKED';

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
    ) {}
}
