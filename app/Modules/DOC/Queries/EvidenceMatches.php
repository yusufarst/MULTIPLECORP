<?php

namespace App\Modules\DOC\Queries;

use App\Modules\DOC\Data\EvidenceIdentity;
use Illuminate\Support\Facades\DB;

/**
 * The FL-10 lookups of one registration (BD-14; BD-15; H6-09), run as plain reads after the registration keys of FU-10
 * are held and before the registration's transaction opens, in system context — what the actor is shown is decided
 * under DP-13 by the command, never here: (1) the same content under another identity and (3) a rendition equal to
 * the upload, on IX-02 (`ContentMatches`); (2) the same issuer, reference and date under another identity — another
 * type, or another company —, on the type-free identity index of DATABASE §4.10, probed once per company and once for
 * pool evidence (AS-08); and for a document without a reference, BD-14's likely duplicate in its company on issuer,
 * type, date and amount, on the same index. Each match is named by its basis.
 */
final class EvidenceMatches
{
    public const CONTENT = 'CONTENT';

    public const CROSS_TYPE = 'CROSS_TYPE';

    public const REFERENCELESS = 'REFERENCELESS';

    public const RENDITION = 'RENDITION';

    public function __construct(private readonly ContentMatches $content) {}

    /**
     * @return list<array{basis: string, kind: 'evidence'|'rendition', record_id: int, public_id: string, company_id: int|null, type: string}>
     */
    public function of(string $sha256, int $fileId, string $issuer, string $type, ?string $reference, string $date, ?string $amount, ?int $companyId, ?int $excludingEvidenceId = null): array
    {
        $matches = [];

        foreach ($this->content->of($sha256, $fileId) as $match) {
            if ($match['kind'] === 'evidence' && $match['record_id'] === $excludingEvidenceId) {
                continue;
            }

            $matches[] = [
                'basis' => $match['kind'] === 'rendition' ? self::RENDITION : self::CONTENT,
                'kind' => $match['kind'],
                'record_id' => $match['record_id'],
                'public_id' => $match['public_id'],
                'company_id' => $match['company_id'],
                'type' => $match['type'],
            ];
        }

        if ($excludingEvidenceId === null) {
            $matches = [...$matches, ...$this->identity($issuer, $type, $reference, $date, $amount, $companyId)];
        }

        $unique = [];

        foreach ($matches as $match) {
            $unique[$match['kind'].':'.$match['record_id']] ??= $match;
        }

        return array_values($unique);
    }

    /**
     * @return list<array{basis: string, kind: 'evidence', record_id: int, public_id: string, company_id: int|null, type: string}>
     */
    private function identity(string $issuer, string $type, ?string $reference, string $date, ?string $amount, ?int $companyId): array
    {
        $issuer = EvidenceIdentity::issuer($issuer);
        $reference = EvidenceIdentity::reference($reference);
        $query = DB::table('evidence_documents')
            ->whereRaw("split_part(identity_key, '|', 1) = ?", [$issuer])
            ->whereRaw("split_part(identity_key, '|', 3) = ?", [$reference])
            ->where('document_date', $date);

        if ($reference !== '') {
            // FL-10 (2): across the workspace — each company, then the pool —, every identity but this one's own.
            $companies = '{'.DB::table('companies')->orderBy('id')->pluck('id')->filter(fn (mixed $id): bool => is_numeric($id))->implode(',').'}';
            $query->whereRaw('(company_id = any(?::bigint[]) or company_id is null)', [$companies])
                ->whereRaw('(evidence_type <> ? or company_id is distinct from ?::bigint)', [$type, $companyId]);
            $basis = self::CROSS_TYPE;
        } else {
            // BD-14: the reference-less likely duplicate in its own company on issuer, type, date and amount.
            ($companyId === null ? $query->whereNull('company_id') : $query->where('company_id', $companyId))
                ->where('evidence_type', $type)
                ->whereRaw('proven_amount is not distinct from ?::numeric', [$amount]);
            $basis = self::REFERENCELESS;
        }

        $matches = [];

        foreach ($query->orderBy('id')->get(['id', 'public_id', 'company_id', 'evidence_type']) as $row) {
            $matches[] = [
                'basis' => $basis,
                'kind' => 'evidence',
                'record_id' => is_numeric($row->id) ? (int) $row->id : 0,
                'public_id' => is_string($row->public_id) ? $row->public_id : '',
                'company_id' => is_numeric($row->company_id) ? (int) $row->company_id : null,
                'type' => is_string($row->evidence_type) ? $row->evidence_type : '',
            ];
        }

        return $matches;
    }
}
