<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use Illuminate\Support\Facades\DB;

/**
 * A document's chain (DATABASE §4.10, §10; FU-12): one per subject and type, with at most one DRAFT and one ISSUED
 * version, the issued chain linear through `supersedes_version_id` (C-11). Its rows are locked at LK-30 — the
 * document, then its versions, in canonical order — under LK-01 for the subject's project and the projects of the
 * requirements linked to a version (CONCURRENCY_IDEMPOTENCY §7.1 AX-15, NX-12), which is also what a change hands
 * to `DocumentChangeParticipant` (L-41; SF-REVAL).
 */
final class DocumentChain
{
    public function __construct(private readonly Renditions $renditions, private readonly EvidenceUse $use) {}

    public function draftOf(int $documentId): ?DocumentVersion
    {
        return DocumentVersion::query()->where('document_id', $documentId)->where('state', 'DRAFT')->first();
    }

    public function issuedOf(int $documentId): ?DocumentVersion
    {
        return DocumentVersion::query()->where('document_id', $documentId)->where('state', 'ISSUED')->first();
    }

    /** Whether the document has issued a version yet: its first issue starts the chain (C-54; WF-DOC-01). */
    public function started(int $documentId): bool
    {
        return DocumentVersion::query()->where('document_id', $documentId)->where('state', '<>', 'DRAFT')->exists();
    }

    /**
     * LK-30's rows and LK-01's projects of the versions named: the subject's project and the projects of the
     * requirements linked to each version.
     *
     * @param  list<int>  $versionIds
     */
    public function locks(Document $document, array $versionIds): LockSet
    {
        $set = (new LockSet)->add('documents', ['id' => $document->id]);

        foreach (array_unique($versionIds) as $versionId) {
            $set = $set->add('document_versions', ['id' => $versionId]);

            foreach ($this->renditions->projectsOf($versionId) as $project) {
                $set = $set->add('projects', ['id' => $project]);
            }
        }

        return $set;
    }

    /** A version's void or supersession as `DocumentChangeParticipant` reads it, with its active requirement links. */
    public function change(string $kind, Document $document, int $previousVersionId, ?int $currentVersionId): DocumentChange
    {
        $links = DB::table('admin_requirement_links')->where('document_version_id', $previousVersionId)->whereNull('unlinked_at')->orderBy('id')
            ->get(['id', 'admin_requirement_id'])
            ->map(fn (object $link): array => ['link' => self::int($link->id ?? null), 'target' => 'admin_requirement_id', 'id' => self::int($link->admin_requirement_id ?? null), 'role' => 'REQUIREMENT'])
            ->values()->all();
        $links = array_values($links);

        return DocumentChange::ofDocument($kind, $document->id, $document->company_id, $document->document_type_code, $links, $previousVersionId, $currentVersionId);
    }

    /** The participants' own locks for the change, before DOC takes its locks. */
    public function participantLocks(DocumentChange $change): LockSet
    {
        return $this->use->participantLocks($change);
    }

    /** L-41: what depended on the version re-evaluated in DOC's transaction, under the locks the change took. */
    public function changed(CommandContext $context, DocumentChange $change): void
    {
        $this->use->changed($context, $change);
    }

    /** A company's public identifier, which a command's fingerprint names it by (CI-04). */
    public static function companyKey(int $companyId): string
    {
        $id = DB::table('companies')->where('id', $companyId)->value('public_id');

        return is_string($id) ? $id : (string) $companyId;
    }

    /** The next version number of a chain, read under its LK-30 lock. */
    public static function nextVersionNo(int $documentId): int
    {
        return self::int(DocumentVersion::query()->where('document_id', $documentId)->max('version_no')) + 1;
    }

    /**
     * The payload's checksum over its canonical JSON — keys sorted at every level —, so the jsonb column's own key
     * order never changes it (BR-DOC-01).
     *
     * @param  array<mixed>  $payload
     */
    public static function checksum(array $payload): string
    {
        return hash('sha256', (string) json_encode(self::canonical($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::canonical(...), $value);
    }
}
