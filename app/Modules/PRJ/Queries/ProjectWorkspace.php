<?php

namespace App\Modules\PRJ\Queries;

use App\Modules\DOC\Queries\ProjectDocuments;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\OPS\Queries\CorrectionTrail;
use App\Modules\PRJ\Data\ProjectInput;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\Journey;
use App\Support\Search\Row;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * PG-011 *Perjalanan Proyek*'s reads (ROUTE-011; SCR-10; SCR-14; NAV-011; UXN-02; D-IA-09; QB-02; UXS-44): the project
 * as a guided journey. At once — its header, its stage track, *Sekarang · Berikutnya* and the one primary action derived
 * from PRJ's own facts, "Rincian proyek" with its lines, and "Posisi keuangan" to `finance.view` —; then at most three
 * deferred groups, each ≤ 10 statements: the completion predicates with the blockers and "Yang tersisa sebelum selesai",
 * "Kesiapan dokumen", and *Riwayat*. A part of a family the viewer lacks is absent, never masked or shown as zero; the
 * parts of the higher tiers — *Biaya dan laba*, *Pengadaan*, availability — arrive with their Tasks (§5.5; §13.7).
 * A linked project outside the viewer's scope is named only as "a project of another company" (XL-4).
 */
final class ProjectWorkspace
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly ProjectJourneys $journeys,
        private readonly ProjectDocuments $documents,
        private readonly CorrectionTrail $trail,
    ) {}

    public function viewer(int $viewerId): Authority
    {
        return $this->capabilities->for($viewerId);
    }

    /** A project by public identifier, or unknown to a viewer without `projects.view` of its company (AZ-11; CS-04). */
    public function find(int $viewerId, string $publicId): Project
    {
        $viewer = $this->viewer($viewerId);
        $project = preg_match(self::UUID, $publicId) === 1 ? Project::query()->where('public_id', strtolower($publicId))->first() : null;

        if ($project === null || ! $viewer->allows('projects.view', $project->company_id)) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$publicId]);
        }

        return $project;
    }

    /** @return array<string, mixed> */
    public function screen(int $viewerId, Project $project): array
    {
        $viewer = $this->viewer($viewerId);
        $money = $viewer->allows('finance.view', $project->company_id);
        $manage = $viewer->allows('projects.manage', $project->company_id);
        $header = DB::table('projects as p')->where('p.id', $project->id)
            ->join('companies as c', 'c.id', '=', 'p.company_id')
            ->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->leftJoin('client_units as u', 'u.id', '=', 'p.client_unit_id')
            ->leftJoin('client_addresses as a', 'a.id', '=', 'p.client_address_id')
            ->leftJoin('client_contacts as k', 'k.id', '=', 'p.client_contact_id')
            ->leftJoin('projects as l', 'l.id', '=', 'p.linked_project_id')
            ->leftJoin('project_siplah_details as s', 's.project_id', '=', 'p.id')
            ->first([
                'p.public_id', 'p.project_number', 'p.title', 'p.state', 'p.channel', 'p.deadline', 'p.link_reason', 'p.lock_version', 'p.recorded_at',
                'c.code as company_code', 'c.legal_name as company_name', 'o.name as client', 'u.name as unit', 'a.label as address_label', 'a.address_lines', 'a.city',
                'k.name as contact', 'k.phone as contact_phone', 'l.public_id as linked_id', 'l.project_number as linked_number', 'l.title as linked_title', 'l.company_id as linked_company',
                's.external_reference', 's.program_code', 's.account_code', 's.notes', 's.siplah_value', 's.real_purchase_value', 's.fee_amount', 's.va_fee_amount', 's.cashback_amount',
                's.refund_amount', 's.locked_at',
            ]);

        if ($header === null) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$project->public_id]);
        }

        $lines = DB::table('project_lines as l')->join('products as p', 'p.id', '=', 'l.product_id')->join('units as u', 'u.id', '=', 'l.entered_unit_id')
            ->where('l.project_id', $project->id)->orderBy('l.line_no')
            ->get(['l.line_no', 'l.description', 'l.entered_qty', 'l.unit_price', 'l.fulfillment_mode', 'p.name as product', 'u.code as unit'])->all();
        $edit = $manage && in_array($project->state, ['DRAFT', 'ACTIVE'], true) ? "/proyek/{$project->public_id}/ubah" : null;
        $facts = $this->journeys->facts([$project->id], $money)[$project->id] ?? null;
        $linkedCompany = Row::nullable($header, 'linked_company');

        return [
            'project' => [
                'id' => $project->public_id,
                'number' => Row::text($header, 'project_number'),
                'title' => Row::text($header, 'title'),
                'state' => Row::text($header, 'state'),
                'channel' => Row::text($header, 'channel'),
                'deadline' => Row::nullable($header, 'deadline'),
                'company' => ['code' => Row::text($header, 'company_code'), 'name' => Row::text($header, 'company_name')],
                'client' => Row::text($header, 'client'),
                'unit' => Row::nullable($header, 'unit'),
                'address' => Row::nullable($header, 'address_label') === null && Row::nullable($header, 'address_lines') === null ? null
                    : ['label' => Row::nullable($header, 'address_label'), 'lines' => Row::nullable($header, 'address_lines'), 'city' => Row::nullable($header, 'city')],
                'contact' => Row::nullable($header, 'contact') === null ? null : ['name' => Row::text($header, 'contact'), 'phone' => Row::nullable($header, 'contact_phone')],
                'link' => $linkedCompany === null ? null : ($viewer->allows('projects.view', (int) $linkedCompany)
                    ? ['id' => Row::text($header, 'linked_id'), 'number' => Row::text($header, 'linked_number'), 'title' => Row::text($header, 'linked_title'), 'visible' => true, 'reason' => Row::nullable($header, 'link_reason')]
                    : ['id' => null, 'number' => null, 'title' => null, 'visible' => false, 'reason' => Row::nullable($header, 'link_reason')]),
                'siplah' => Row::text($header, 'channel') !== 'SIPLAH' ? null : self::siplah($header, $money),
                'lines' => array_map(fn (object $line): array => [
                    'no' => (int) Row::text($line, 'line_no'),
                    'product' => Row::text($line, 'product'),
                    'description' => Row::text($line, 'description'),
                    'quantity' => Row::text($line, 'entered_qty'),
                    'unit' => Row::text($line, 'unit'),
                    'mode' => Row::text($line, 'fulfillment_mode'),
                    ...($money ? ['price' => Row::nullable($line, 'unit_price')] : []),
                ], $lines),
                'recorded_at' => Row::text($header, 'recorded_at'),
                'lock_version' => (int) Row::text($header, 'lock_version'),
            ],
            'journey' => $facts === null ? null : Journey::derive($facts, [], $money, $edit),
            'financial' => $money ? $this->financial($project->id) : null,
            'can' => ['edit' => $edit !== null, 'discard' => $manage && $project->state === 'DRAFT'],
        ];
    }

    /**
     * The predicates group: the journey derived with every completion predicate — its blockers on their stages and
     * "Yang tersisa sebelum selesai" (QB-02; SCR-14).
     *
     * @return array<string, mixed>|null
     */
    public function predicates(int $viewerId, Project $project): ?array
    {
        $viewer = $this->viewer($viewerId);
        $edit = $viewer->allows('projects.manage', $project->company_id) && in_array($project->state, ['DRAFT', 'ACTIVE'], true) ? "/proyek/{$project->public_id}/ubah" : null;

        return $this->journeys->of([$project->id], [$project->id => $viewer->allows('finance.view', $project->company_id)], [$project->id => $edit])[$project->id] ?? null;
    }

    /**
     * The documents group, "Kesiapan dokumen" (QB-02).
     *
     * @return array{rows: list<array<string, mixed>>, more: bool}
     */
    public function documents(int $viewerId, Project $project): array
    {
        return $this->documents->of($this->viewer($viewerId), $project->company_id, $project->id);
    }

    /**
     * The *Riwayat* group (QB-02): the project's creation and state transitions, newest first, each with its account —
     * the business audit itself stays the Owner's (LG-05) —, and the corrections linked to it (TASK-031's trail).
     *
     * @return array{events: list<array{kind: string, from: ?string, to: ?string, reason: ?string, by: ?string, at: string}>, corrects: list<array<string, mixed>>, corrected_by: list<array<string, mixed>>}
     */
    public function history(int $viewerId, Project $project): array
    {
        $viewer = $this->viewer($viewerId);
        $transitions = DB::table('project_state_transitions as t')->leftJoin('users as u', 'u.id', '=', 't.decided_by')
            ->where('t.project_id', $project->id)->orderByDesc('t.id')->limit(20)
            ->get(['t.transition_kind', 't.from_state', 't.to_state', 't.reason', 't.recorded_at', 'u.name'])->all();
        $creator = DB::table('users')->where('id', $project->recorded_by)->value('name');
        $events = array_values(array_map(fn (object $row): array => [
            'kind' => Row::text($row, 'transition_kind'),
            'from' => Row::nullable($row, 'from_state'),
            'to' => Row::nullable($row, 'to_state'),
            'reason' => Row::nullable($row, 'reason'),
            'by' => Row::nullable($row, 'name'),
            'at' => Row::text($row, 'recorded_at'),
        ], $transitions));
        $events[] = ['kind' => 'CREATE', 'from' => null, 'to' => null, 'reason' => null, 'by' => is_string($creator) ? $creator : null, 'at' => $project->recorded_at->toIso8601String()];

        return ['events' => $events, ...$this->trail->of($viewer, 'project', $project->public_id)];
    }

    /**
     * "Posisi keuangan": the confirmed and invoiced sales value (`project_commercial_balances`; L-16), zero before any.
     *
     * @return array{confirmed: string, invoiced: string}
     */
    private function financial(int $projectId): array
    {
        $row = DB::table('project_commercial_balances')->where('project_id', $projectId)->first(['confirmed_sales_value', 'invoiced_sales_value']);

        return ['confirmed' => $row === null ? '0.00' : Row::text($row, 'confirmed_sales_value'), 'invoiced' => $row === null ? '0.00' : Row::text($row, 'invoiced_sales_value')];
    }

    /** @return array<string, mixed> */
    private static function siplah(object $header, bool $money): array
    {
        $values = ['locked' => Row::nullable($header, 'locked_at') !== null];

        foreach ([...ProjectInput::SIPLAH_TEXTS, ...($money ? ProjectInput::SIPLAH_AMOUNTS : [])] as $field) {
            $values[$field] = Row::nullable($header, $field);
        }

        return $values;
    }
}
