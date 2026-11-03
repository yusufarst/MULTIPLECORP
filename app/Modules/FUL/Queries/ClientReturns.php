<?php

namespace App\Modules\FUL\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Data\ClientReturns as Returned;
use App\Modules\INV\Queries\StockViews;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * PG-034 *Retur dari Klien* (ROUTE-034; NAV-034; SCR-32; J-FUL-05): a project's shipment lines the client received, each with
 * net delivered and what it returned (PX-08), the consumptions and serials still with the client the return form may name, the
 * racks and proofs it may cite; the project's SALES_RETURN cases with their open steps — the demand, the invoice, the refund and
 * the damaged units' outcome —, each step's state read from the records that close it, the restored units with "Balikkan
 * (Tercatat Keliru)" by capability; the contra amounts only with `cost.view` (SM-25). In a constant number of statements
 * whatever the rows (QB-02).
 */
final class ClientReturns
{
    /** The rows one list shows. */
    public const PAGE = 50;

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly StockViews $views,
    ) {}

    /** The project, when the viewer holds the capability in its company (CS-03); otherwise not found. */
    public function projectOf(int $viewerId, string $publicId, string $capability): ?ProjectRef
    {
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('projects')->where('public_id', strtolower($publicId))->first(['id', 'company_id', 'public_id']) : null;

        if ($row === null || ! $this->capabilities->for($viewerId)->allows($capability, (int) Row::text($row, 'company_id'))) {
            return null;
        }

        return new ProjectRef((int) Row::text($row, 'id'), (int) Row::text($row, 'company_id'), Row::text($row, 'public_id'));
    }

    /**
     * ROUTE-034's content, null when the project is not the viewer's to open with `sales_return.record` (ROUTE-034).
     *
     * @return array<string, mixed>|null
     */
    public function page(int $viewerId, string $publicId): ?array
    {
        $project = $this->projectOf($viewerId, $publicId, 'sales_return.record');

        if ($project === null) {
            return null;
        }

        $viewer = $this->capabilities->for($viewerId);
        $company = $project->companyId;
        $cost = $viewer->allows('cost.view', $company);
        $header = DB::table('projects as p')->leftJoin('client_organizations as o', 'o.id', '=', 'p.client_organization_id')->where('p.id', $project->id)->first(['p.public_id', 'p.project_number', 'p.title', 'p.state', 'o.name as client']);

        if ($header === null) {
            return null;
        }

        return [
            'project' => ['id' => Row::text($header, 'public_id'), 'number' => Row::text($header, 'project_number'), 'title' => Row::text($header, 'title'), 'client' => Row::nullable($header, 'client'), 'state' => Row::text($header, 'state')],
            'lines' => self::lines($project->id),
            'cases' => self::cases($project->id, $cost),
            'racks' => array_values(array_map(fn (object $row): array => ['id' => RowHandle::of('locations', (int) Row::text($row, 'id')), 'code' => Row::text($row, 'code'), 'name' => Row::text($row, 'name')],
                DB::table('locations')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])->all())),
            'proofs' => self::proofs($viewer, $company),
            'can' => [
                'record' => $viewer->allows('sales_return.record', $company), 'reverse' => $viewer->allows('stock.reverse', $company), 'reserve' => $viewer->allows('stock.reserve', $company),
                'close' => $viewer->allows('cases.close', $company), 'cost' => $cost, 'upload' => $viewer->allows('evidence.upload', $company),
            ],
        ];
    }

    /** The project's shipment line by its handle. */
    public function dispatchLineOf(ProjectRef $project, string $handle): ?int
    {
        return self::byHandle('dispatch_lines', DB::table('dispatch_lines')->where('project_id', $project->id)->pluck('id')->all(), $handle);
    }

    /** The shipment line's consumption by its handle. */
    public function consumptionOf(int $dispatchLineId, string $handle): ?int
    {
        return self::byHandle('stock_consumptions', DB::table('stock_consumptions')->where('dispatch_line_id', $dispatchLineId)->pluck('id')->all(), $handle);
    }

    /** The serial still out on the consumption, by its handle. */
    public function serialOf(int $consumptionId, string $handle): ?int
    {
        return self::byHandle('serial_units', DB::table('serial_units as s')->join('stock_movement_entries as e', 'e.id', '=', 's.last_entry_id')->where('e.stock_consumption_id', $consumptionId)->where('s.state', 'DISPATCHED')->pluck('s.id')->all(), $handle);
    }

    /** An active rack by its handle. */
    public function rackOf(string $handle): ?int
    {
        return self::byHandle('locations', DB::table('locations')->where('is_active', true)->pluck('id')->all(), $handle);
    }

    /**
     * The product's policy the return was entered under (ST-09).
     *
     * @return array{serialized: bool, scale: int}
     */
    public function policyOf(int $dispatchLineId): array
    {
        $row = DB::table('dispatch_lines as d')->join('products as p', 'p.id', '=', 'd.product_id')->where('d.id', $dispatchLineId)->first(['p.is_serialized', 'p.quantity_scale']);

        return ['serialized' => $row !== null && (bool) Row::text($row, 'is_serialized'), 'scale' => $row === null ? 0 : (int) Row::text($row, 'quantity_scale')];
    }

    /** The proof a return cites: the company's own evidence the viewer sees (PJ-08). */
    public function evidenceOf(int $viewerId, int $companyId, string $publicId): ?int
    {
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('evidence_documents')->where('public_id', strtolower($publicId))->where('company_id', $companyId)->where('evidence_scope', 'COMPANY')->first(['id', 'evidence_type']) : null;

        return $row !== null && EvidenceTypes::mayView($this->capabilities->for($viewerId), Row::text($row, 'evidence_type'), $companyId) ? (int) Row::text($row, 'id') : null;
    }

    /**
     * ROUTE-211's original by its public id: a return's or a restoration's movement the viewer reverses in its company, or a
     * drop-ship confirmation whose movement-less return is undone.
     *
     * @return array{movement: ?int, confirmation: ?int, company: int, type: string}|null
     */
    public function originalOf(int $viewerId, string $publicId): ?array
    {
        if (preg_match('/^[0-9a-f-]{36}$/i', $publicId) !== 1) {
            return null;
        }

        $viewer = $this->capabilities->for($viewerId);
        $movement = DB::table('stock_movements')->where('public_id', strtolower($publicId))->whereIn('movement_type', ['RESTORATION', 'PURCHASE_RETURN', 'DROPSHIP_RETURN_RECEIPT'])->first(['id', 'company_id', 'movement_type']);

        if ($movement !== null) {
            $company = (int) Row::text($movement, 'company_id');

            return $viewer->allows('stock.reverse', $company) ? ['movement' => (int) Row::text($movement, 'id'), 'confirmation' => null, 'company' => $company, 'type' => Row::text($movement, 'movement_type')] : null;
        }

        $confirmation = DB::table('dropship_confirmations')->where('public_id', strtolower($publicId))->first(['id', 'company_id']);

        if ($confirmation === null || ! $viewer->allows('stock.reverse', (int) Row::text($confirmation, 'company_id'))) {
            return null;
        }

        return ['movement' => null, 'confirmation' => (int) Row::text($confirmation, 'id'), 'company' => (int) Row::text($confirmation, 'company_id'), 'type' => 'DROPSHIP'];
    }

    /** A restoration of the original by its handle: on the movement, or a movement-less one on the confirmation's lines. */
    public function restorationOf(?int $movementId, ?int $confirmationId, string $handle): ?int
    {
        $ids = $movementId !== null ? DB::table('stock_restorations')->where('stock_movement_id', $movementId)->pluck('id')->all()
            : DB::table('stock_restorations as r')->join('dropship_confirmation_lines as l', 'l.id', '=', 'r.dropship_confirmation_line_id')->where('l.dropship_confirmation_id', $confirmationId ?? 0)->whereNull('r.stock_movement_id')->pluck('r.id')->all();

        return self::byHandle('stock_restorations', $ids, $handle);
    }

    /**
     * IP-20's choices after a reversal is refused for the shortfall (C-06): the active reservations of the original's product,
     * only to a viewer who may cut them (`stock.reserve`).
     *
     * @return list<array{id: string, reserved: string, other: bool, project: array{label: string, href: string}|null}>
     */
    public function cutChoices(int $viewerId, int $movementId, int $companyId): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $product = DB::table('stock_movement_entries as e')->join('inventory_lots as i', 'i.id', '=', 'e.lot_id')->where('e.stock_movement_id', $movementId)->orderBy('e.id')->value('i.product_id');

        return is_numeric($product) && $viewer->allows('stock.reserve', $companyId) ? $this->views->reservations($viewer, (int) $product) : [];
    }

    /**
     * The projects one command returned from completed to *Aktif* (SF-REVAL; CM-32), by number.
     *
     * @return list<string>
     */
    public function reactivatedBy(string $commandId): array
    {
        return preg_match('/^[0-9a-f-]{36}$/i', $commandId) !== 1 ? [] : array_values(array_map(fn (mixed $number): string => is_scalar($number) ? (string) $number : '',
            DB::table('project_state_transitions as t')->join('projects as p', 'p.id', '=', 't.project_id')->where('t.command_id', strtolower($commandId))->where('t.transition_kind', 'REVALIDATE')->orderBy('p.project_number')->pluck('p.project_number')->all()));
    }

    /** An active reservation a reversal's cut names, by its handle (IP-20). */
    public function reservationOf(string $handle): ?int
    {
        return self::byHandle('reservations', DB::table('reservations')->where('state', 'ACTIVE')->pluck('id')->all(), $handle);
    }

    /** A purchase return's consumption by its handle. */
    public function returnedConsumptionOf(int $movementId, string $handle): ?int
    {
        return self::byHandle('stock_consumptions', DB::table('stock_consumptions')->where('stock_movement_id', $movementId)->where('kind', 'PURCHASE_RETURN')->pluck('id')->all(), $handle);
    }

    /**
     * The likely duplicates as MSG-15 lists them: each standing return's movement and date.
     *
     * @param  list<string>  $publicIds
     * @return list<array{id: string, label: string, href: null}>
     */
    public function matches(array $publicIds): array
    {
        return array_values(array_map(fn (object $row): array => ['id' => Row::text($row, 'public_id'), 'label' => Row::text($row, 'business_date'), 'href' => null],
            DB::table('stock_movements')->whereIn('public_id', $publicIds === [] ? ['00000000-0000-0000-0000-000000000000'] : $publicIds)->orderBy('id')->get(['public_id', 'business_date'])->all()));
    }

    /**
     * The project's shipment lines with something delivered or returned: net delivered, returned, the consumptions and serials still with the client.
     *
     * @return list<array<string, mixed>>
     */
    private static function lines(int $projectId): array
    {
        $rows = DB::table('dispatch_lines as d')->join('stock_movements as m', 'm.id', '=', 'd.stock_movement_id')->join('project_lines as pl', 'pl.id', '=', 'd.project_line_id')->join('products as p', 'p.id', '=', 'd.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'd.entered_unit_id')->leftJoin('units as bu', 'bu.id', '=', 'p.base_unit_id')->where('d.project_id', $projectId)->orderByDesc('d.id')->limit(self::PAGE)
            ->get(['d.id', 'd.ratio_to_base', 'm.public_id as movement', 'm.business_date', 'pl.line_no', 'pl.description', 'p.is_serialized', 'p.quantity_scale', 'u.code as unit', 'bu.code as base_unit',
                DB::raw(Returned::NET_DELIVERED.' as delivered'), DB::raw('('.Returned::RETURNED.') as returned')])->all();
        $ids = array_map(fn (object $row): int => (int) Row::text($row, 'id'), $rows);
        $consumptions = $ids === [] ? [] : DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->join('inventory_lots as i', 'i.id', '=', 'c.lot_id')
            ->whereIn('c.dispatch_line_id', $ids)->orderBy('c.id')->get(['c.id', 'c.dispatch_line_id', 'i.lot_code', DB::raw('b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base as open')])->all();
        $serials = $ids === [] ? [] : DB::table('serial_units as s')->join('stock_movement_entries as e', 'e.id', '=', 's.last_entry_id')->join('stock_consumptions as c', 'c.id', '=', 'e.stock_consumption_id')
            ->whereIn('c.dispatch_line_id', $ids)->where('s.state', 'DISPATCHED')->orderBy('s.serial_number')->get(['s.id', 's.serial_number', 'c.id as consumption'])->all();
        $byLine = [];

        foreach ($consumptions as $consumption) {
            $byLine[(int) Row::text($consumption, 'dispatch_line_id')][] = ['id' => RowHandle::of('stock_consumptions', (int) Row::text($consumption, 'id')), 'lot' => Row::text($consumption, 'lot_code'), 'open' => self::plain(Row::text($consumption, 'open')),
                'serials' => array_values(array_map(fn (object $serial): array => ['id' => RowHandle::of('serial_units', (int) Row::text($serial, 'id')), 'serial' => Row::text($serial, 'serial_number')],
                    array_filter($serials, fn (object $serial): bool => (int) Row::text($serial, 'consumption') === (int) Row::text($consumption, 'id'))))];
        }

        return array_values(array_map(fn (object $row): array => [
            'id' => RowHandle::of('dispatch_lines', (int) Row::text($row, 'id')), 'shipment' => Row::text($row, 'movement'), 'date' => Row::text($row, 'business_date'), 'no' => (int) Row::text($row, 'line_no'),
            'description' => Row::text($row, 'description'), 'unit' => Row::nullable($row, 'unit'), 'base_unit' => Row::nullable($row, 'base_unit'), 'ratio' => (int) Row::text($row, 'ratio_to_base'),
            'serialized' => (bool) Row::text($row, 'is_serialized'), 'scale' => (int) Row::text($row, 'quantity_scale'),
            'delivered' => self::plain((string) BigDecimal::max(BigDecimal::zero(), BigDecimal::of(Row::text($row, 'delivered')))), 'returned' => self::plain(Row::text($row, 'returned')),
            // What is still with the client: no more of a consumption than the line's net delivered (PX-08).
            'consumptions' => array_values(array_map(fn (array $consumption): array => [...$consumption, 'open' => self::plain((string) BigDecimal::min(BigDecimal::of($consumption['open']), BigDecimal::max(BigDecimal::zero(), BigDecimal::of(Row::text($row, 'delivered')))))],
                $byLine[(int) Row::text($row, 'id')] ?? [])),
        ], array_filter($rows, fn (object $row): bool => BigDecimal::of(Row::text($row, 'delivered'))->isPositive() || BigDecimal::of(Row::text($row, 'returned'))->isPositive())));
    }

    /**
     * The project's client-return cases, newest first: their steps with each one's state read from the records that close it,
     * their restorations with what of each still stands, the contra only with `cost.view`.
     *
     * @return list<array<string, mixed>>
     */
    private static function cases(int $projectId, bool $cost): array
    {
        $cases = DB::table('correction_cases as k')->leftJoin('inventory_lots as i', 'i.id', '=', 'k.source_lot_id')->where('k.project_id', $projectId)->where('k.case_kind', 'SALES_RETURN')->orderByDesc('k.id')->limit(self::PAGE)
            ->get(['k.id', 'k.public_id', 'k.state', 'k.reason', 'k.lock_version', 'k.recorded_at', 'k.source_lot_id', 'i.lot_code',
                DB::raw("(select a.metadata->'steps' from audit_events a where a.entity_type = 'correction_case' and a.entity_id = k.id and a.action = 'OPS.CorrectionCaseOpened' order by a.id limit 1) as steps"),
                DB::raw("exists (select 1 from invoices v where v.void_correction_case_id = k.id) or exists (select 1 from invoice_versions w where w.basis_correction_case_id = k.id) as invoiced_done"),
                DB::raw("(select coalesce(sum(b.qty), 0) from stock_lot_balances b where b.lot_id = k.source_lot_id and b.condition = 'UNUSABLE') as unusable_left")])->all();
        $ids = array_map(fn (object $row): int => (int) Row::text($row, 'id'), $cases);

        if ($ids === []) {
            return [];
        }

        $restorations = DB::table('stock_restorations as r')->join('stock_movements as m', 'm.id', '=', 'r.stock_movement_id')->join('stock_consumptions as c', 'c.id', '=', 'r.stock_consumption_id')->join('dispatch_lines as d', 'd.id', '=', 'c.dispatch_line_id')
            ->join('project_line_balances as pb', 'pb.project_line_id', '=', 'd.project_line_id')->whereIn('r.correction_case_id', $ids)->orderBy('r.id')
            ->get(['r.id', 'r.correction_case_id', 'r.qty_base', 'r.returned_condition', 'm.public_id as movement', 'm.business_date', 'd.project_line_id',
                DB::raw("(select coalesce(sum(x.qty_delta_base), 0) from stock_movement_entries x where x.stock_restoration_id = r.id and x.movement_type = 'MOVEMENT_REVERSAL') as reversed"),
                DB::raw("(select coalesce(-sum(a.amount), 0) from cost_attributions a where a.stock_restoration_id = r.id) as contra"),
                DB::raw('pb.demand_qty_base - pb.dispatched_net_qty_base - pb.dropship_confirmed_net_qty_base - pb.handed_over_qty_base - pb.cancelled_qty_base as remainder')])->all();
        $byCase = [];

        foreach ($restorations as $restoration) {
            $byCase[(int) Row::text($restoration, 'correction_case_id')][] = $restoration;
        }

        return array_values(array_map(function (object $case) use ($byCase, $cost): array {
            $rows = $byCase[(int) Row::text($case, 'id')] ?? [];
            $steps = json_decode(Row::nullable($case, 'steps') ?? '[]', true);
            $demandDone = $rows !== [] && array_reduce($rows, fn (bool $all, object $row): bool => $all && ! BigDecimal::of(Row::text($row, 'remainder'))->isPositive(), true);
            $done = ['demand' => $demandDone, 'invoice_correction' => (bool) Row::text($case, 'invoiced_done'), 'refund' => false, 'damaged_outcome' => ! BigDecimal::of(Row::text($case, 'unusable_left'))->isPositive()];

            return [
                'id' => Row::text($case, 'public_id'), 'state' => Row::text($case, 'state'), 'reason' => Row::nullable($case, 'reason'), 'lock_version' => (int) Row::text($case, 'lock_version'), 'date' => substr(Row::text($case, 'recorded_at'), 0, 10),
                'lot' => Row::nullable($case, 'lot_code'),
                'steps' => array_values(array_map(fn (string $step): array => ['key' => $step, 'done' => $done[$step] ?? false], array_values(array_filter(is_array($steps) ? $steps : [], is_string(...))))),
                'restorations' => array_values(array_map(fn (object $row): array => [
                    'id' => RowHandle::of('stock_restorations', (int) Row::text($row, 'id')), 'movement' => Row::text($row, 'movement'), 'date' => Row::text($row, 'business_date'),
                    'quantity' => self::plain(Row::text($row, 'qty_base')), 'standing' => self::plain((string) BigDecimal::of(Row::text($row, 'qty_base'))->plus(Row::text($row, 'reversed'))), 'condition' => Row::nullable($row, 'returned_condition'),
                    ...($cost ? ['contra' => Money::of(Row::text($row, 'contra'))->toString()] : []),
                ], $rows)),
            ];
        }, $cases));
    }

    /**
     * The proofs the return form may cite: the company's evidence the viewer sees (PJ-08), newest first.
     *
     * @return list<array{id: string, type: string, label: string}>
     */
    private static function proofs(Authority $viewer, int $company): array
    {
        $options = [];

        foreach (DB::table('evidence_documents')->where('company_id', $company)->where('evidence_scope', 'COMPANY')->whereIn('evidence_type', Deliveries::EVIDENCE)->orderByDesc('id')->limit(self::PAGE)->get(['public_id', 'evidence_type', 'declared_issuer', 'issuer_reference', 'document_date'])->all() as $row) {
            if (EvidenceTypes::mayView($viewer, Row::text($row, 'evidence_type'), $company)) {
                $options[] = ['id' => Row::text($row, 'public_id'), 'type' => Row::text($row, 'evidence_type'), 'label' => Row::text($row, 'declared_issuer').' · '.(Row::nullable($row, 'issuer_reference') ?? Row::text($row, 'document_date'))];
            }
        }

        return $options;
    }

    /** @param  array<mixed>  $ids */
    private static function byHandle(string $table, array $ids, string $handle): ?int
    {
        if (! RowHandle::valid($handle)) {
            return null;
        }

        foreach ($ids as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of($table, (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    /** A decimal without its trailing zeros. */
    private static function plain(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
