<?php

namespace App\Modules\ADM\Queries;

use App\Modules\ADM\Services\Readiness;
use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Queries\EvidenceList;
use App\Modules\DOC\Services\DocumentAccess;
use App\Modules\DOC\Services\DocumentProjects;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\PRJ\Contracts\ReadinessProvider;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * The checklist inside PG-011's "Kesiapan dokumen" (D-IA-10; J-ADM-01; WF-ADM-01; PT-29), ADM's part of PRJ's
 * `ReadinessProvider`: each requirement a derived readiness row — the item, its status, what satisfies it and its
 * waivers —, missing items first, with no stored progress, fraction or percentage. A linked document shows its number
 * only with its family's view capability and an evidence only with its type's (PJ-15; PJ-08); why a client-original
 * item stays open — its upload reproduces a system rendition — only to a viewer who may see that rendition (IP-16;
 * GAP-034; SM-23); the default invoice item only with `finance.view` (PJ-13); a document only of the project itself,
 * through its subject (BR-ADM-01). Waiver eligibility is the Owner's to read (row 96; OD-05): an Admin receives only
 * whether "Tandai Tidak Berlaku" is offered. The controls follow the capabilities, the server deciding either way
 * (AZ-01). A bounded number of statements, whatever the number of items (QB-02).
 *
 * It also resolves what the commands name — a requirement or a decision by the handle the page showed, a document or
 * an evidence by public identifier — among the project's own rows, an unknown one alike to a hidden one (AZ-11).
 */
final class ProjectChecklist implements ReadinessProvider
{
    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly EvidenceList $evidence,
    ) {}

    public function checklist(int $viewerId, ProjectRef $project): ?array
    {
        $viewer = $this->capabilities->for($viewerId);
        $company = $project->companyId;

        if (! $viewer->allows('projects.view', $company)) {
            return null;
        }

        $can = [
            'manage' => $viewer->allows('requirements.manage', $company),
            'waive' => $viewer->allows('requirements.waive', $company),
            'owner' => $viewer->allows('requirements.set_waiver_eligibility', $company),
            'relax' => $viewer->allows('requirements.relax', $company),
            'supersede' => $viewer->allows('decisions.supersede', $company),
        ];
        // The default invoice item's status tells the invoiced value against the confirmed: a money fact (PJ-13).
        $money = $viewer->allows('finance.view', $company);
        $items = array_values(array_filter(Readiness::of([$project->id])[$project->id] ?? [], fn (array $item): bool => $money || ! $item['default_invoice']));
        $ids = array_column($items, 'id');
        $links = $this->links($viewer, $ids, $company);
        $waivers = $this->waivers($ids);
        $rows = [];

        foreach ($items as $item) {
            $history = $waivers[$item['id']] ?? [];
            $rows[] = [
                'handle' => RowHandle::of('admin_requirements', $item['id']),
                'kind' => $item['kind'],
                'document_type' => $item['document_type'],
                'evidence_type' => $item['evidence_type'],
                'description' => $item['description'],
                'required' => $item['required'],
                'mode' => $item['mode'],
                'client_original' => $item['client_original'],
                'default_invoice' => $item['default_invoice'],
                'lock_version' => $item['lock_version'],
                'status' => $item['status'],
                'links' => $links[$item['id']]['rows'] ?? [],
                'reproduction' => $links[$item['id']]['reproduction'] ?? false,
                'may_waive' => $can['waive'] && $item['waiver_eligible'] && ! $item['default_invoice'] && $item['status'] !== Readiness::WAIVED,
                ...($can['owner'] ? ['waiver_eligible' => $item['waiver_eligible']] : []),
                'waivers' => array_map(fn (array $waiver): array => [...$waiver, 'handle' => $can['supersede'] && $waiver['current'] && ! $waiver['reopened'] ? $waiver['handle'] : null], $history),
            ];
        }

        return [
            'items' => $rows,
            'can' => $can,
            'options' => $can['manage'] ? $this->options($viewer, $project) : null,
        ];
    }

    /**
     * The external document types a requirement may name — a company's evidence types, without the pool's warehouse
     * record and the migration's sign-off, which no client asks for.
     *
     * @return list<string>
     */
    public function evidenceTypes(): array
    {
        return array_values(array_filter(EvidenceTypes::TYPES, fn (string $type): bool => ! in_array($type, ['OPENING_SIGNOFF', 'WAREHOUSE_RECORD'], true)));
    }

    /** A requirement of the project by the handle its page showed, removed or not; null otherwise (AZ-11). */
    public function requirementOf(ProjectRef $project, string $handle): ?int
    {
        if (! RowHandle::valid($handle)) {
            return null;
        }

        foreach (DB::table('admin_requirements')->where('project_id', $project->id)->pluck('id') as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('admin_requirements', (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * A decision of the project by its handle — a waiver of one of its requirements, or its commercial confirmation —
     * as `[kind, id]`; null when the handle names none of them (AZ-11).
     *
     * @return ?array{0: string, 1: int}
     */
    public function decisionOf(ProjectRef $project, string $handle): ?array
    {
        if (! RowHandle::valid($handle)) {
            return null;
        }

        $waivers = DB::table('admin_requirement_waivers as w')->join('admin_requirements as r', 'r.id', '=', 'w.admin_requirement_id')
            ->where('r.project_id', $project->id)->pluck('w.id');

        foreach ($waivers as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('admin_requirement_waivers', (int) $id), $handle)) {
                return ['waiver', (int) $id];
            }
        }

        foreach (DB::table('commercial_confirmations')->where('project_id', $project->id)->pluck('id') as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('commercial_confirmations', (int) $id), $handle)) {
                return ['approval', (int) $id];
            }
        }

        return null;
    }

    /** The issued version of a document of the project the viewer may open, by the document's public identifier (PJ-15; CS-04). */
    public function versionOf(int $viewerId, ProjectRef $project, string $documentPublicId): ?int
    {
        $viewer = $this->capabilities->for($viewerId);
        $row = DB::table('documents as d')->join('document_versions as v', 'v.document_id', '=', 'd.id')
            ->where('d.public_id', strtolower($documentPublicId))->where('d.company_id', $project->companyId)->where('v.state', 'ISSUED')
            // A document of another project never satisfies this one's checklist (BR-ADM-01).
            ->whereRaw(DocumentProjects::SQL.' = ?', [$project->id])
            ->first(['v.id', 'd.document_type_code']);

        return is_object($row) && DocumentAccess::mayViewContent($viewer, Row::text($row, 'document_type_code'), $project->companyId) ? (int) Row::text($row, 'id') : null;
    }

    /** A company evidence the viewer may see, by public identifier (PJ-08; CS-04). */
    public function evidenceOf(int $viewerId, ProjectRef $project, string $publicId): ?int
    {
        return $this->evidence->idOf($viewerId, $publicId, $project->companyId, EvidenceTypes::TYPES);
    }

    /**
     * Each item's active links as the viewer may see them, and whether an evidence it links reproduces a rendition the
     * viewer may see (FS-12; IP-16).
     *
     * @param  list<int>  $ids
     * @return array<int, array{rows: list<array<string, mixed>>, reproduction: bool}>
     */
    private function links(Authority $viewer, array $ids, int $company): array
    {
        if ($ids === []) {
            return [];
        }

        $found = DB::table('admin_requirement_links as l')
            ->leftJoin('document_versions as v', 'v.id', '=', 'l.document_version_id')
            ->leftJoin('documents as d', 'd.id', '=', 'v.document_id')
            ->leftJoin('evidence_documents as e', 'e.id', '=', 'l.evidence_document_id')
            ->leftJoin('evidence_document_versions as ev', 'ev.id', '=', 'e.current_version_id')
            ->leftJoin('file_objects as f', 'f.id', '=', 'ev.file_object_id')
            ->whereIn('l.admin_requirement_id', $ids)->whereNull('l.unlinked_at')->orderBy('l.id')
            ->selectRaw(<<<'SQL'
                l.admin_requirement_id, d.public_id as document, d.document_type_code, v.formatted_number, v.state as version_state,
                e.public_id as evidence, e.evidence_type, e.declared_issuer,
                (select rd.document_type_code from file_objects g join document_renditions dr on dr.file_object_id = g.id
                    join document_versions rv on rv.id = dr.document_version_id join documents rd on rd.id = rv.document_id
                    where g.purpose = 'RENDITION' and g.sha256 = f.sha256 limit 1) as reproduces
                SQL)
            ->get();
        $links = [];

        foreach ($found as $row) {
            $requirement = (int) Row::text($row, 'admin_requirement_id');
            $links[$requirement] ??= ['rows' => [], 'reproduction' => false];

            if (Row::nullable($row, 'document') !== null) {
                $type = Row::text($row, 'document_type_code');
                $visible = DocumentAccess::mayViewContent($viewer, $type, $company);
                $links[$requirement]['rows'][] = ['kind' => 'document', 'id' => $visible ? Row::text($row, 'document') : null, 'type' => $type,
                    'number' => $visible ? Row::nullable($row, 'formatted_number') : null, 'state' => Row::text($row, 'version_state')];

                continue;
            }

            $type = Row::text($row, 'evidence_type');
            $visible = EvidenceTypes::mayView($viewer, $type, $company);
            $links[$requirement]['rows'][] = ['kind' => 'evidence', 'id' => $visible ? Row::text($row, 'evidence') : null, 'type' => $type,
                'issuer' => $visible ? Row::nullable($row, 'declared_issuer') : null];
            $reproduces = Row::nullable($row, 'reproduces');

            if ($reproduces !== null && DocumentAccess::mayViewContent($viewer, $reproduces, $company)) {
                $links[$requirement]['reproduction'] = true;
            }
        }

        return $links;
    }

    /**
     * Each item's waivers, newest first, with the account that decided each and whether it is the chain's head.
     *
     * @param  list<int>  $ids
     * @return array<int, list<array{handle: string, reason: string, by: ?string, at: string, reopened: bool, current: bool}>>
     */
    private function waivers(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $found = DB::table('admin_requirement_waivers as w')->leftJoin('users as u', 'u.id', '=', 'w.decided_by')
            ->whereIn('w.admin_requirement_id', $ids)->orderByDesc('w.id')
            ->selectRaw('w.id, w.admin_requirement_id, w.reason, w.reopened_by_correction, w.recorded_at, u.name,
                not exists (select 1 from admin_requirement_waivers n where n.supersedes_waiver_id = w.id) as current')
            ->get();
        $waivers = [];

        foreach ($found as $row) {
            $waivers[(int) Row::text($row, 'admin_requirement_id')][] = [
                'handle' => RowHandle::of('admin_requirement_waivers', (int) Row::text($row, 'id')),
                'reason' => Row::text($row, 'reason'),
                'by' => Row::nullable($row, 'name'),
                'at' => Row::text($row, 'recorded_at'),
                'reopened' => ($row->reopened_by_correction ?? false) === true,
                'current' => ($row->current ?? false) === true,
            ];
        }

        return $waivers;
    }

    /**
     * What a new requirement and a link choose from: the catalogue's document types and the evidence types; the
     * project's documents with an issued version the viewer may open, and the company's evidence the viewer may see.
     *
     * @return array<string, mixed>
     */
    private function options(Authority $viewer, ProjectRef $project): array
    {
        $company = $project->companyId;
        $documents = DB::table('documents as d')->join('document_versions as v', 'v.document_id', '=', 'd.id')
            ->where('d.company_id', $company)->whereRaw(DocumentProjects::SQL.' = ?', [$project->id])->where('v.state', 'ISSUED')->orderByDesc('d.id')->limit(100)
            ->get(['d.public_id', 'd.document_type_code', 'v.formatted_number'])
            ->filter(fn (object $row): bool => DocumentAccess::mayViewContent($viewer, Row::text($row, 'document_type_code'), $company))
            ->map(fn (object $row): array => ['id' => Row::text($row, 'public_id'), 'type' => Row::text($row, 'document_type_code'), 'number' => Row::nullable($row, 'formatted_number')])
            ->values()->all();
        $evidence = DB::table('evidence_documents')->where('company_id', $company)->orderByDesc('id')->limit(100)
            ->get(['public_id', 'evidence_type', 'declared_issuer', 'issuer_reference', 'document_date'])
            ->filter(fn (object $row): bool => EvidenceTypes::mayView($viewer, Row::text($row, 'evidence_type'), $company))
            ->map(fn (object $row): array => ['id' => Row::text($row, 'public_id'), 'type' => Row::text($row, 'evidence_type'), 'issuer' => Row::text($row, 'declared_issuer'),
                'reference' => Row::nullable($row, 'issuer_reference'), 'date' => Row::text($row, 'document_date')])
            ->values()->all();

        return [
            'document_types' => array_values(array_map(fn (mixed $code): string => is_string($code) ? $code : '', DB::table('document_types')->orderBy('code')->pluck('code')->all())),
            'evidence_types' => $this->evidenceTypes(),
            'documents' => $documents,
            'evidence' => $evidence,
        ];
    }
}
