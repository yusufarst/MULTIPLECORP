<?php

namespace App\Modules\DOC\Queries;

use App\Modules\IAM\Data\Authority;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * A project's documents for its journey (INFORMATION_ARCHITECTURE §5.5; QB-01; QB-02), DOC's query class PRJ reads
 * directly (ARCHITECTURE §3): the documents whose subject is the project itself — the quotation, the delivery and
 * handover plans (DOC-01, DOC-09, DOC-11) —, each by its current version; the documents of a purchase, a shipment or
 * an invoice join as their Tasks bind those subjects to the project. A failed rendition of an issued version is the
 * *Dokumen* stage's blocker (the P7 gate record's journey model). Each read is one statement over the projects asked,
 * never one per project (PF-01).
 */
final class ProjectDocuments
{
    public const ROWS = 20;

    /**
     * Per project, how many documents it has and how many issued versions' latest rendition failed.
     *
     * @param  list<int>  $projectIds
     * @return array<int, array{documents: int, failed: int}>
     */
    public function readiness(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('documents as d')
            ->whereIn('d.project_id', $projectIds)
            ->groupBy('d.project_id')
            ->select('d.project_id')
            ->selectRaw('count(*) as documents')
            ->selectRaw("count(*) filter (where exists (select 1 from document_versions v join lateral (select r.state from document_renditions r where r.document_version_id = v.id order by r.attempt_no desc limit 1) last on true where v.document_id = d.id and v.state = 'ISSUED' and last.state = 'FAILED')) as failed")
            ->get();
        $readiness = [];

        foreach ($rows as $row) {
            $readiness[(int) Row::text($row, 'project_id')] = ['documents' => (int) Row::text($row, 'documents'), 'failed' => (int) Row::text($row, 'failed')];
        }

        return $readiness;
    }

    /**
     * PG-011's "Kesiapan dokumen": the project's documents, newest first, each with its current version's state and
     * number and its latest rendition's state, to a viewer who may see the company's documents (PJ-08).
     *
     * @return array{rows: list<array{id: string, type: string, type_name: string, state: string, number: ?string, rendition: ?string}>, more: bool}
     */
    public function of(Authority $viewer, int $companyId, int $projectId): array
    {
        if (! $viewer->allows('projects.view', $companyId)) {
            return ['rows' => [], 'more' => false];
        }

        $found = DB::table('documents as d')
            ->where('d.company_id', $companyId)
            ->where('d.project_id', $projectId)
            ->join('document_types as t', 't.code', '=', 'd.document_type_code')
            ->joinLateral(DB::table('document_versions as v')->whereColumn('v.document_id', 'd.id')
                ->orderByRaw("(v.state = 'ISSUED') desc")->orderByDesc('v.version_no')->limit(1)
                ->select(['v.id', 'v.state', 'v.formatted_number']), 'c')
            ->leftJoinLateral(DB::table('document_renditions as r')->whereColumn('r.document_version_id', 'c.id')
                ->orderByDesc('r.attempt_no')->limit(1)->select(['r.state']), 'last')
            ->orderByDesc('d.id')
            ->limit(self::ROWS + 1)
            ->get(['d.public_id', 'd.document_type_code', 't.name as type_name', 'c.state', 'c.formatted_number', 'last.state as rendition'])
            ->all();

        return [
            'rows' => array_map(fn (object $row): array => [
                'id' => Row::text($row, 'public_id'),
                'type' => Row::text($row, 'document_type_code'),
                'type_name' => Row::text($row, 'type_name'),
                'state' => Row::text($row, 'state'),
                'number' => Row::nullable($row, 'formatted_number'),
                'rendition' => Row::nullable($row, 'rendition'),
            ], array_slice($found, 0, self::ROWS)),
            'more' => count($found) > self::ROWS,
        ];
    }
}
