<?php

namespace App\Modules\FUL\Queries;

use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Services\DocumentAccess;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentPaper;
use App\Modules\DOC\Services\IssuerIdentity;
use App\Modules\FUL\Data\HandoverProgress;
use App\Modules\FUL\Services\HandoverFacts;
use App\Modules\FUL\Services\ServiceHandoverSubjects;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * PG-033 *Serah Terima Jasa* (ROUTE-033; NAV-033; SCR-31; J-FUL-04; L-39): the project's confirmed service lines with
 * their handed-over and remaining quantities, the handovers with their reversal lines and each one's BAST state, the
 * evidence the form may cite (PJ-08) and the paper the browser composes the draft's live preview from (D-DS-23;
 * DESIGN_SYSTEM §14.3), only the fields the viewer may see (PJ-15; PJ-19). No stock figure, ever (FS-05). Each command's
 * control present with its capability (AZ-01). The handovers newest first by keyset (D-PF-02), older ones by `setelah`.
 * In a constant number of statements whatever the rows shown (QB-02).
 */
final class ServiceHandovers
{
    /** The handovers one page lists. */
    public const PAGE = 50;

    /** The evidence types a handover cites (J-FUL-04; PJ-08). */
    public const EVIDENCE = ['DELIVERY_PROOF', 'OTHER'];

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly IssuerIdentity $identity,
    ) {}

    /**
     * @param  ?string  $after  the handover the page's list continues after, within the project; an unknown one starts from the newest
     * @return array<string, mixed>
     */
    public function screen(int $viewerId, ProjectRef $project, ?string $after = null): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $company = $project->companyId;
        $header = DB::table('projects as p')->where('p.id', $project->id)
            ->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')->join('companies as c', 'c.id', '=', 'p.company_id')
            ->first(['p.project_number', 'p.title', 'p.state', 'o.name as client', 'c.code as company']);
        $open = in_array($header === null ? null : Row::text($header, 'state'), ['DRAFT', 'ACTIVE'], true);
        $lines = DB::table('project_lines as l')->where('l.project_id', $project->id)->where('l.fulfillment_mode', 'SERVICE')->whereNotNull('l.current_pin_id')
            ->join('confirmation_line_pins as p', 'p.id', '=', 'l.current_pin_id')->join('project_line_balances as b', 'b.project_line_id', '=', 'l.id')
            ->join('units as u', 'u.id', '=', 'p.entered_unit_id')
            ->join('product_units as pu', fn (JoinClause $join) => $join->on('pu.product_id', '=', 'l.product_id')->where('pu.is_base', true))->join('units as bu', 'bu.id', '=', 'pu.unit_id')
            ->orderBy('l.line_no')
            ->get(['l.id', 'l.line_no', 'l.quantity_scale', 'p.description', 'p.ratio_to_base', 'u.code as unit', 'bu.code as base_unit', 'b.demand_qty_base', 'b.handed_over_qty_base', 'b.cancelled_qty_base'])->all();
        $from = $after === null || preg_match('/^[0-9a-f-]{36}$/i', $after) !== 1 ? null : $this->handoverOf($project, $after);
        $handovers = DB::table('service_handovers as h')->where('h.project_id', $project->id)->when($from !== null, fn ($query) => $query->where('h.id', '<', $from))->leftJoin('users as u', 'u.id', '=', 'h.recorded_by')
            ->orderByDesc('h.id')->limit(self::PAGE + 1)->get(['h.id', 'h.public_id', 'h.business_date', 'h.recipient_name', 'h.recorded_at', 'u.name as recorded_by'])->all();
        $more = count($handovers) > self::PAGE;
        $handovers = array_slice($handovers, 0, self::PAGE);
        $ids = array_map(fn (object $row): int => (int) Row::text($row, 'id'), $handovers);
        $issue = DocumentAccess::mayIssue($viewer, ServiceHandoverSubjects::BAST, $company);
        $bast = $this->bast($ids, $issue);
        $evidence = $this->citedEvidence($viewer, $ids, $company);
        $handed = $this->handoverLines($ids);
        $reverse = $open && $viewer->allows('fulfillment.correct', $company);
        $remaining = array_map(function (object $line): array {
            $remainder = HandoverProgress::remainder(Row::text($line, 'demand_qty_base'), Row::text($line, 'handed_over_qty_base'), Row::text($line, 'cancelled_qty_base'));

            return [
                'handle' => RowHandle::of('project_lines', (int) Row::text($line, 'id')),
                'no' => (int) Row::text($line, 'line_no'),
                'description' => Row::text($line, 'description'),
                'unit' => Row::text($line, 'unit'),
                'base_unit' => Row::text($line, 'base_unit'),
                'ratio' => (int) Row::text($line, 'ratio_to_base'),
                'scale' => (int) Row::text($line, 'quantity_scale'),
                'demand' => Row::text($line, 'demand_qty_base'),
                'handed_over' => Row::text($line, 'handed_over_qty_base'),
                'cancelled' => Row::text($line, 'cancelled_qty_base'),
                'remainder' => $remainder,
                'remainder_entered' => HandoverProgress::inEnteredUnit($remainder, (int) Row::text($line, 'ratio_to_base'), (int) Row::text($line, 'quantity_scale')),
            ];
        }, $lines);
        $canRecord = $open && $viewer->allows('handover.record', $company);

        return [
            'project' => $header === null ? null : [
                'id' => $project->publicId,
                'number' => Row::text($header, 'project_number'),
                'title' => Row::text($header, 'title'),
                'company' => Row::text($header, 'company'),
                'client' => Row::text($header, 'client'),
                'state' => Row::text($header, 'state'),
            ],
            'lines' => $remaining,
            'handovers' => array_map(function (object $row) use ($handed, $evidence, $bast, $issue, $reverse): array {
                $id = (int) Row::text($row, 'id');
                $lines = $handed[$id] ?? [];
                $standing = array_values(array_filter($lines, fn (array $line): bool => $line['reversal'] === null));
                $document = $bast[$id] ?? null;

                return [
                    'id' => Row::text($row, 'public_id'),
                    'date' => Row::text($row, 'business_date'),
                    'recipient' => Row::text($row, 'recipient_name'),
                    'recorded_by' => Row::nullable($row, 'recorded_by'),
                    'recorded_at' => Row::text($row, 'recorded_at'),
                    'lines' => array_map(fn (array $line): array => [...$line, 'can_reverse' => $reverse && $line['reversal'] === null], $lines),
                    'evidence' => $evidence[$id] ?? [],
                    'bast' => $document,
                    // An issued BAST whose handover lost a line since says so, and PG-036 revises or voids it (WORKFLOWS §8; CM-27).
                    'bast_affected' => ($document['issued'] ?? null) !== null && $document['issued_lines'] !== count($standing),
                    'can_issue' => $issue && $standing !== [] && ($document === null || ($document['issued'] ?? null) === null),
                ];
            }, $handovers),
            // The last row of a full page continues the list; the page's own cursor says it is not the newest (D-PF-02).
            'older' => $more && $handovers !== [] ? Row::text($handovers[count($handovers) - 1], 'public_id') : null,
            'after' => $from === null ? null : $after,
            'paper' => $header === null || ! DocumentAccess::mayViewContent($viewer, ServiceHandoverSubjects::BAST, $company) ? null : $this->paper($company, $header),
            'evidence' => $canRecord && $viewer->allows('evidence.view', $company) ? $this->evidenceOptions($viewer, $company) : [],
            'today' => BusinessDate::today(),
            'can' => ['record' => $canRecord, 'upload' => $viewer->allows('evidence.upload', $company)],
        ];
    }

    /**
     * Each project line's handle, for the form's lines (AZ-11).
     *
     * @return array<string, int>
     */
    public function lineHandles(ProjectRef $project): array
    {
        $handles = [];

        foreach (DB::table('project_lines')->where('project_id', $project->id)->pluck('id') as $id) {
            if (is_numeric($id)) {
                $handles[RowHandle::of('project_lines', (int) $id)] = (int) $id;
            }
        }

        return $handles;
    }

    /** The handover by public identifier within its project, null otherwise (AZ-11; CS-04). */
    public function handoverOf(ProjectRef $project, string $publicId): ?int
    {
        $id = DB::table('service_handovers')->where('project_id', $project->id)->where('public_id', strtolower($publicId))->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * The handover's line by the handle PG-033 showed.
     *
     * @return array{line: int, project_line: int}|null
     */
    public function handoverLine(int $handoverId, string $handle): ?array
    {
        if (! RowHandle::valid($handle)) {
            return null;
        }

        foreach (DB::table('service_handover_lines')->where('service_handover_id', $handoverId)->get(['id', 'project_line_id']) as $row) {
            if (hash_equals(RowHandle::of('service_handover_lines', (int) Row::text($row, 'id')), $handle)) {
                return ['line' => (int) Row::text($row, 'id'), 'project_line' => (int) Row::text($row, 'project_line_id')];
            }
        }

        return null;
    }

    /**
     * The likely duplicates as MSG-15 lists them: each handover's date and recipient, within the project.
     *
     * @param  list<string>  $publicIds
     * @return list<array{id: string, label: string, href: null}>
     */
    public function matches(ProjectRef $project, array $publicIds): array
    {
        return array_values(array_map(fn (object $row): array => [
            'id' => Row::text($row, 'public_id'),
            'label' => Row::text($row, 'business_date').' · '.Row::text($row, 'recipient_name'),
            'href' => null,
        ], DB::table('service_handovers')->where('project_id', $project->id)->whereIn('public_id', $publicIds)->orderBy('id')->get(['public_id', 'business_date', 'recipient_name'])->all()));
    }

    /**
     * The lines of the handovers, a reversal line as its reversed line's `reversal`.
     *
     * @param  list<int>  $ids
     * @return array<int, list<array<string, mixed>>>
     */
    private function handoverLines(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('service_handover_lines as l')->whereIn('l.service_handover_id', $ids)
            ->join('project_lines as pl', 'pl.id', '=', 'l.project_line_id')->join('confirmation_line_pins as p', 'p.id', '=', 'pl.current_pin_id')->join('units as u', 'u.id', '=', 'l.entered_unit_id')
            ->leftJoin('users as r', 'r.id', '=', 'l.recorded_by')
            ->orderBy('l.id')
            ->get(['l.id', 'l.service_handover_id', 'l.reverses_line_id', 'l.entered_qty', 'l.qty_base', 'l.scope_note', 'l.recorded_at', 'pl.line_no', 'p.description', 'u.code as unit', 'r.name as recorded_by']);
        $reversals = [];

        foreach ($rows as $row) {
            if (Row::nullable($row, 'reverses_line_id') !== null) {
                $reversals[(int) Row::text($row, 'reverses_line_id')] = ['reason' => Row::nullable($row, 'scope_note'), 'by' => Row::nullable($row, 'recorded_by'), 'at' => Row::text($row, 'recorded_at')];
            }
        }

        $lines = [];

        foreach ($rows as $row) {
            if (Row::nullable($row, 'reverses_line_id') === null) {
                $id = (int) Row::text($row, 'id');
                $lines[(int) Row::text($row, 'service_handover_id')][] = [
                    'handle' => RowHandle::of('service_handover_lines', $id),
                    'no' => (int) Row::text($row, 'line_no'),
                    'description' => Row::text($row, 'description'),
                    'quantity' => Row::text($row, 'entered_qty'),
                    'unit' => Row::text($row, 'unit'),
                    'base' => Row::text($row, 'qty_base'),
                    'scope' => Row::nullable($row, 'scope_note'),
                    'reversal' => $reversals[$id] ?? null,
                ];
            }
        }

        return $lines;
    }

    /**
     * Each handover's BAST: its chain, the open draft with what its issue carries, the issued version with its number
     * and the count of lines it rendered.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function bast(array $ids, bool $issue): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('documents as d')->whereIn('d.service_handover_id', $ids)->where('d.document_type_code', ServiceHandoverSubjects::BAST)
            ->leftJoin('document_versions as v', fn (JoinClause $join) => $join->on('v.document_id', '=', 'd.id')->whereIn('v.state', ['DRAFT', 'ISSUED']))
            ->get(['d.service_handover_id', 'd.public_id', 'v.public_id as version', 'v.state', 'v.lock_version', 'v.formatted_number', DB::raw("coalesce(jsonb_array_length(v.payload #> '{paper,lines}'), 0) as lines")]);
        $bast = [];

        foreach ($rows as $row) {
            $id = (int) Row::text($row, 'service_handover_id');
            $bast[$id] ??= ['document' => Row::text($row, 'public_id'), 'draft' => null, 'issued' => null, 'issued_lines' => 0];
            $state = Row::nullable($row, 'state');

            if ($state === 'DRAFT') {
                $bast[$id]['draft'] = ['id' => Row::text($row, 'version'), 'lock_version' => (int) Row::text($row, 'lock_version'), 'facts' => null];
            } elseif ($state === 'ISSUED') {
                $bast[$id]['issued'] = Row::nullable($row, 'formatted_number');
                $bast[$id]['issued_lines'] = (int) Row::text($row, 'lines');
            }
        }

        // The draft's issue carries the digest of the facts its paper showed (architecture challenge 15a), read once for them all.
        $drafts = $issue ? array_keys(array_filter($bast, fn (array $document): bool => $document['draft'] !== null)) : [];

        foreach (HandoverFacts::of($drafts) as $id => $facts) {
            $draft = $bast[$id]['draft'];

            if (is_array($draft)) {
                $bast[$id]['draft'] = [...$draft, 'facts' => DocumentChain::factsDigest($facts)];
            }
        }

        return $bast;
    }

    /**
     * The evidence linked to the handovers, those the viewer may see (PJ-08).
     *
     * @param  list<int>  $ids
     * @return array<int, list<array{id: string, type: string}>>
     */
    private function citedEvidence(Authority $viewer, array $ids, int $company): array
    {
        if ($ids === []) {
            return [];
        }

        $linked = [];
        $rows = DB::table('evidence_links as e')->whereIn('e.service_handover_id', $ids)->whereNull('e.unlinked_at')
            ->join('evidence_documents as d', 'd.id', '=', 'e.evidence_document_id')->get(['e.service_handover_id', 'd.public_id', 'd.evidence_type']);

        foreach ($rows as $row) {
            if (EvidenceTypes::mayView($viewer, Row::text($row, 'evidence_type'), $company)) {
                $linked[(int) Row::text($row, 'service_handover_id')][] = ['id' => Row::text($row, 'public_id'), 'type' => Row::text($row, 'evidence_type')];
            }
        }

        return $linked;
    }

    /**
     * The evidence the form may cite, newest first.
     *
     * @return list<array{id: string, type: string, issuer: string, reference: ?string, date: string}>
     */
    private function evidenceOptions(Authority $viewer, int $company): array
    {
        $options = [];

        foreach (DB::table('evidence_documents')->where('company_id', $company)->whereIn('evidence_type', self::EVIDENCE)
            ->orderByDesc('id')->limit(100)->get(['public_id', 'evidence_type', 'declared_issuer', 'issuer_reference', 'document_date']) as $row) {
            if (EvidenceTypes::mayView($viewer, Row::text($row, 'evidence_type'), $company)) {
                $options[] = [
                    'id' => Row::text($row, 'public_id'),
                    'type' => Row::text($row, 'evidence_type'),
                    'issuer' => Row::text($row, 'declared_issuer'),
                    'reference' => Row::nullable($row, 'issuer_reference'),
                    'date' => Row::text($row, 'document_date'),
                ];
            }
        }

        return $options;
    }

    /**
     * The paper of the BAST's draft as the browser's composer takes it: the issuer's identity (PJ-19) and the project's
     * facts, the recipient, date and lines the page fills in from the form or the handover; marked as a draft, no number.
     *
     * @return array<string, mixed>
     */
    private function paper(int $company, object $header): array
    {
        $identity = $this->identity->of($company);
        $facts = new DocumentFacts(
            recipient: ['name' => '', 'lines' => [Row::text($header, 'client')]],
            references: [
                ['label' => 'No. Proyek', 'value' => Row::text($header, 'project_number')],
                ['label' => 'Klien', 'value' => Row::text($header, 'client')],
            ],
            subject: Row::text($header, 'title'),
        );

        return DocumentPaper::compose(ServiceHandoverSubjects::BAST, 'STANDARD', $facts, $identity, null, BusinessDate::today(), null, true,
            $identity['assets']['logo'] === null ? null : '/berkas/'.$identity['assets']['logo']['public_id'].'/unduh');
    }
}
