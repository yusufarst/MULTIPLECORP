<?php

namespace App\Modules\DOC\Queries;

use Illuminate\Support\Facades\DB;

/**
 * The content lookups of SECURITY FL-10 (1) and (3) and BD-15, on IX-02's `sha256` index: the evidence holding the
 * same bytes under another evidence identity, and a system rendition equal to an upload — a reproduction that can
 * never satisfy a client original (FL-05; FS-12). It runs across the workspace in system context, as a plain read
 * outside the business transaction; what the actor is shown of a match is decided by the registration command under
 * DP-13 (TASK-030), never here. Each match names its record — the evidence document, or the rendered document
 * version — by internal and public identifier.
 */
final class ContentMatches
{
    /**
     * @return list<array{kind: 'evidence'|'rendition', file_id: int, company_id: int|null, type: string, record_id: int, public_id: string}>
     */
    public function of(string $sha256, ?int $excludingFileId = null): array
    {
        $evidence = DB::table('file_objects as f')
            ->join('evidence_document_versions as ev', 'ev.file_object_id', '=', 'f.id')
            ->join('evidence_documents as e', 'e.id', '=', 'ev.evidence_document_id')
            ->where('f.sha256', $sha256)
            ->when($excludingFileId !== null, fn ($query) => $query->where('f.id', '<>', $excludingFileId))
            ->orderBy('f.id')
            ->get(['f.id as file_id', 'e.company_id', 'e.evidence_type as type', 'e.id as record_id', 'e.public_id']);
        $renditions = DB::table('file_objects as f')
            ->join('document_renditions as r', 'r.file_object_id', '=', 'f.id')
            ->join('document_versions as v', 'v.id', '=', 'r.document_version_id')
            ->where('f.sha256', $sha256)
            ->where('f.purpose', 'RENDITION')
            ->orderBy('f.id')
            ->get(['f.id as file_id', 'v.company_id', 'v.document_type_code as type', 'v.id as record_id', 'v.public_id']);
        $matches = [];

        foreach ([['evidence', $evidence], ['rendition', $renditions]] as [$kind, $rows]) {
            foreach ($rows as $row) {
                $matches[] = [
                    'kind' => $kind,
                    'file_id' => is_numeric($row->file_id) ? (int) $row->file_id : 0,
                    'company_id' => is_numeric($row->company_id) ? (int) $row->company_id : null,
                    'type' => is_string($row->type) ? $row->type : '',
                    'record_id' => is_numeric($row->record_id) ? (int) $row->record_id : 0,
                    'public_id' => is_string($row->public_id) ? $row->public_id : '',
                ];
            }
        }

        return $matches;
    }
}
