<?php

namespace App\Modules\INV\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Services\DocumentAccess;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Data\StockFigures;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-023 *Barang Keluar* and PG-024 *Keluarkan Barang*'s reads (ROUTE-023; ROUTE-024; NAV-023; NAV-024; CALC-02; PJ-01–PJ-06): the remaining reservations of ACTIVE projects' warehouse lines, the project's lines, the lots from the pool with the relation marker for another company's, and the project's dispatches. Physical only: this class never selects a cost table, and another company's lot ends at its code, rack, condition and quantity.
 */
final class DispatchQueries
{
    public const PAGE = 50;

    public const CHOICES = 200;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /**
     * The companies whose dispatches the viewer may record, each a group of its own (NAV-023).
     *
     * @return list<array{id: string, code: string, name: string, internal: int}>
     */
    public function groups(int $viewerId): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $groups = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']) as $company) {
            if ($viewer->allows('stock.dispatch', (int) Row::text($company, 'id'))) {
                $groups[] = ['id' => Row::text($company, 'public_id'), 'code' => Row::text($company, 'code'), 'name' => Row::text($company, 'legal_name'), 'internal' => (int) Row::text($company, 'id')];
            }
        }

        return $groups;
    }

    /**
     * One company's dispatch-ready lines (CALC-02): the remaining reservations of its ACTIVE projects' warehouse lines; keyset by reservation (PF-13; PF-14).
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string, cursor_failed: bool}
     */
    public function worklist(int $viewerId, int $companyId, ?string $after): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $binding = ['list' => 'dispatch', 'company' => $companyId];
        $key = $after === null ? null : StockCursor::read($viewer, $after, $binding, 1);

        if ($after !== null && $key === null) {
            return ['rows' => [], 'more' => null, 'cursor_failed' => true];
        }

        $found = $viewer->scope->apply(DB::table('reservations as r'), 'r.company_id')->where('r.company_id', $companyId)->where('r.state', 'ACTIVE')
            ->join('project_lines as l', 'l.id', '=', 'r.project_line_id')->join('projects as p', 'p.id', '=', 'l.project_id')->join('products as x', 'x.id', '=', 'r.product_id')->join('units as u', 'u.id', '=', 'x.base_unit_id')
            ->where('p.state', 'ACTIVE')->where('l.fulfillment_mode', 'WAREHOUSE')
            ->when($key !== null, fn (Builder $where) => $where->where('r.id', '>', $key[0] ?? 0))
            ->orderBy('r.id')->limit(self::PAGE + 1)
            ->get(['r.id', 'r.remaining_qty_base', 'l.line_no', 'x.name', 'u.code as unit', 'p.public_id as project', 'p.project_number', 'p.title'])->all();
        $page = array_slice($found, 0, self::PAGE);
        $last = $page === [] ? null : $page[count($page) - 1];

        return [
            'rows' => array_map(fn (object $row): array => ['id' => RowHandle::of('reservations', (int) Row::text($row, 'id')), 'project' => Row::text($row, 'project_number'), 'title' => Row::text($row, 'title'), 'line' => (int) Row::text($row, 'line_no'), 'name' => Row::text($row, 'name'), 'remaining' => StockFigures::plain(Row::text($row, 'remaining_qty_base')), 'unit' => Row::text($row, 'unit'), 'href' => '/gudang/barang-keluar/'.Row::text($row, 'project')], $page),
            'more' => count($found) > self::PAGE && $last !== null ? StockCursor::of($viewer, [(int) Row::text($last, 'id')], $binding) : null,
            'cursor_failed' => false,
        ];
    }

    /**
     * The project by public id, to the capability on its company; otherwise unknown (AZ-11; CS-04).
     *
     * @return array{id: int, company: int, public_id: string}|null
     */
    public function project(int $viewerId, string $publicId, string $capability = 'stock.dispatch'): ?array
    {
        $row = preg_match(self::UUID, $publicId) === 1 ? DB::table('projects')->where('public_id', strtolower($publicId))->first(['id', 'company_id', 'public_id']) : null;

        if ($row === null || ! $this->capabilities->for($viewerId)->allows($capability, (int) Row::text($row, 'company_id'))) {
            return null;
        }

        return ['id' => (int) Row::text($row, 'id'), 'company' => (int) Row::text($row, 'company_id'), 'public_id' => Row::text($row, 'public_id')];
    }

    /**
     * A dispatch by its movement's public id, to the capability on its company; otherwise unknown (AZ-11).
     *
     * @return array{movement: int, line: int, company: int, project: string, product: int, project_id: int}|null
     */
    public function dispatch(int $viewerId, string $publicId, string $capability = 'stock.reverse'): ?array
    {
        $row = preg_match(self::UUID, $publicId) === 1 ? DB::table('stock_movements as m')->join('dispatch_lines as d', 'd.stock_movement_id', '=', 'm.id')->join('projects as p', 'p.id', '=', 'd.project_id')->where('m.public_id', strtolower($publicId))->where('m.movement_type', 'DISPATCH')->first(['m.id', 'd.id as line', 'd.company_id', 'd.product_id', 'd.project_id', 'p.public_id as project']) : null;

        if ($row === null || ! $this->capabilities->for($viewerId)->allows($capability, (int) Row::text($row, 'company_id'))) {
            return null;
        }

        return ['movement' => (int) Row::text($row, 'id'), 'line' => (int) Row::text($row, 'line'), 'company' => (int) Row::text($row, 'company_id'), 'project' => Row::text($row, 'project'), 'product' => (int) Row::text($row, 'product_id'), 'project_id' => (int) Row::text($row, 'project_id')];
    }

    /** @return array<string, mixed> PG-024 in QB-05's four statements: the project, its lines, their units, its dispatches. */
    public function screen(int $viewerId, int $projectId, int $companyId): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $project = DB::table('projects as p')->join('companies as c', 'c.id', '=', 'p.company_id')->where('p.id', $projectId)->first(['p.public_id', 'p.project_number', 'p.title', 'p.state', 'c.code', 'c.public_id as company']);

        return [
            'project' => $project === null ? null : ['id' => Row::text($project, 'public_id'), 'number' => Row::text($project, 'project_number'), 'title' => Row::text($project, 'title'), 'state' => Row::text($project, 'state'), 'company' => Row::text($project, 'code'), 'company_id' => Row::text($project, 'company')],
            'lines' => self::lines($projectId),
            'dispatches' => $this->dispatches($viewer, $projectId, $companyId),
            'locations' => array_values(array_map(fn (object $row): array => ['id' => RowHandle::of('locations', (int) Row::text($row, 'id')), 'code' => Row::text($row, 'code'), 'name' => Row::text($row, 'name')], DB::table('locations')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])->all())),
            'today' => BusinessDate::today(),
            'can' => ['allocate' => $viewer->allows('stock.allocate_intercompany', $companyId), 'reverse' => $viewer->allows('stock.reverse', $companyId), 'reserve' => $viewer->allows('stock.reserve', $companyId), 'note' => DocumentAccess::mayIssue($viewer, 'DOC-03', $companyId), 'evidence' => $viewer->allows('evidence.upload', $companyId)],
        ];
    }

    /** @return list<array<string, mixed>> the project's confirmed warehouse lines */
    private static function lines(int $projectId): array
    {
        $rows = DB::table('project_lines as l')->join('project_line_balances as b', 'b.project_line_id', '=', 'l.id')->join('products as r', 'r.id', '=', 'l.product_id')
            ->where('l.project_id', $projectId)->where('l.fulfillment_mode', 'WAREHOUSE')->whereNotNull('l.current_pin_id')->orderBy('l.line_no')
            ->get(['l.id', 'l.line_no', 'l.description', 'l.product_id', 'r.public_id as product', 'r.name', 'r.sku', 'r.is_serialized', 'r.quantity_scale', 'b.demand_qty_base', 'b.reserved_qty_base', 'b.dispatched_net_qty_base', 'b.cancelled_qty_base'])->all();
        $units = $rows === [] ? [] : DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')->whereIn('pu.product_id', array_map(fn (object $row): int => (int) Row::text($row, 'product_id'), $rows))->where('pu.is_active', true)->orderByDesc('pu.is_base')->orderBy('u.code')->get(['pu.product_id', 'u.code', 'u.name', 'pu.ratio_to_base'])->all();

        return array_values(array_map(function (object $row) use ($units): array {
            $product = (int) Row::text($row, 'product_id');

            return [
                'handle' => RowHandle::of('project_lines', (int) Row::text($row, 'id')), 'no' => (int) Row::text($row, 'line_no'), 'description' => Row::text($row, 'description'), 'product' => Row::text($row, 'product'), 'name' => Row::text($row, 'name'), 'sku' => Row::nullable($row, 'sku'), 'serialized' => (bool) $row->is_serialized, 'scale' => (int) Row::text($row, 'quantity_scale'),
                'demand' => StockFigures::plain(Row::text($row, 'demand_qty_base')), 'reserved' => StockFigures::plain(Row::text($row, 'reserved_qty_base')), 'dispatched' => StockFigures::plain(Row::text($row, 'dispatched_net_qty_base')),
                'remaining' => StockFigures::remainder(Row::text($row, 'demand_qty_base'), Row::text($row, 'dispatched_net_qty_base'), Row::text($row, 'cancelled_qty_base')),
                'units' => array_values(array_map(fn (object $unit): array => ['code' => Row::text($unit, 'code'), 'name' => Row::text($unit, 'name'), 'ratio' => (int) Row::text($unit, 'ratio_to_base')], array_filter($units, fn (object $unit): bool => (int) Row::text($unit, 'product_id') === $product))),
            ];
        }, $rows));
    }

    /**
     * The project's dispatches, each with what may still come back: the consumptions open, their lots by code and marker, their serials out; never a cost.
     *
     * @return list<array<string, mixed>>
     */
    private function dispatches(Authority $viewer, int $projectId, int $companyId): array
    {
        $rows = DB::table('dispatch_lines as d')->join('stock_movements as m', 'm.id', '=', 'd.stock_movement_id')->join('project_lines as l', 'l.id', '=', 'd.project_line_id')->join('products as x', 'x.id', '=', 'd.product_id')->join('project_line_balances as g', 'g.project_line_id', '=', 'd.project_line_id')->leftJoin('units as u', 'u.id', '=', 'd.entered_unit_id')
            ->where('d.project_id', $projectId)->orderByDesc('d.id')->limit(self::PAGE)
            ->get(['d.id', 'd.project_line_id', 'd.qty_base', 'd.entered_qty', 'u.code as unit', 'm.public_id', 'm.business_date', 'l.line_no', 'x.name', 'x.is_serialized', 'g.dispatched_net_qty_base', 'g.delivered_net_qty_base'])->all();
        $ids = array_map(fn (object $row): int => (int) Row::text($row, 'id'), $rows);
        $consumptions = [];

        foreach ($ids === [] ? [] : DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->join('inventory_lots as i', 'i.id', '=', 'c.lot_id')->whereIn('c.dispatch_line_id', $ids)->orderBy('c.id')
            ->get(['c.id', 'c.dispatch_line_id', 'c.source_company_id', 'i.lot_code', 'b.qty_base', 'b.restored_qty_base', 'b.reclassified_qty_base', 'b.substituted_qty_base',
                DB::raw("(select coalesce(json_agg(json_build_object('handle', s.id, 'serial', s.serial_number)), '[]') from stock_movement_entries e join serial_units s on s.id = e.serial_unit_id where e.stock_consumption_id = c.id and s.state = 'DISPATCHED') as serials"),
                DB::raw('(select e.location_id from stock_movement_entries e where e.stock_consumption_id = c.id order by e.id limit 1) as location')]) as $row) {
            $serials = json_decode(Row::text($row, 'serials'), true);
            $open = StockFigures::remainder(Row::text($row, 'qty_base'), Row::text($row, 'restored_qty_base'), Row::text($row, 'reclassified_qty_base'), Row::text($row, 'substituted_qty_base'));
            $location = Row::nullable($row, 'location');
            $consumptions[(int) Row::text($row, 'dispatch_line_id')][] = [
                'handle' => RowHandle::of('stock_consumptions', (int) Row::text($row, 'id')), 'lot' => Row::text($row, 'lot_code'), 'other' => (int) Row::text($row, 'source_company_id') !== $companyId, 'open' => $open, 'location' => $location === null ? null : RowHandle::of('locations', (int) $location),
                'serials' => is_array($serials) ? array_values(array_map(fn (array $serial): array => ['handle' => RowHandle::of('serial_units', is_numeric($serial['handle'] ?? null) ? (int) $serial['handle'] : 0), 'serial' => is_string($serial['serial'] ?? null) ? $serial['serial'] : ''], array_filter($serials, is_array(...)))) : [],
            ];
        }

        $mayReverse = $viewer->allows('stock.reverse', $companyId);

        return array_values(array_map(fn (object $row): array => [
            'id' => Row::text($row, 'public_id'), 'date' => Row::text($row, 'business_date'), 'line' => (int) Row::text($row, 'line_no'), 'handle' => RowHandle::of('project_lines', (int) Row::text($row, 'project_line_id')), 'name' => Row::text($row, 'name'), 'qty' => StockFigures::plain(Row::text($row, 'entered_qty')), 'unit' => Row::nullable($row, 'unit'), 'base' => StockFigures::plain(Row::text($row, 'qty_base')), 'serialized' => (bool) $row->is_serialized,
            'undelivered' => StockFigures::remainder(Row::text($row, 'dispatched_net_qty_base'), Row::text($row, 'delivered_net_qty_base')),
            'consumptions' => $consumptions[(int) Row::text($row, 'id')] ?? [],
            'reversible' => $mayReverse && array_filter($consumptions[(int) Row::text($row, 'id')] ?? [], fn (array $one): bool => $one['open'] !== '0') !== [],
        ], $rows));
    }

    /**
     * The usable lots and serials of a line's product at their racks (PJ-01; BR-INV-04): "Ambil Lebih Dulu" on the oldest, a picking aid only; another company's by its code, rack, condition and quantity with the marker; never a cost.
     *
     * @return array<string, mixed>|null
     */
    public function choices(int $projectId, int $companyId, string $handle): ?array
    {
        $line = $this->line($projectId, $handle);

        if ($line === null) {
            return null;
        }

        $product = (int) DB::table('project_lines')->where('id', $line)->value('product_id');
        $lots = [];
        $first = true;

        foreach (DB::table('stock_lot_balances as b')->join('inventory_lots as i', 'i.id', '=', 'b.lot_id')->join('locations as l', 'l.id', '=', 'b.location_id')->where('b.product_id', $product)->where('b.condition', 'USABLE')->where('b.qty', '>', 0)
            ->orderBy('i.business_date')->orderBy('i.id')->orderBy('l.code')->limit(self::CHOICES)->get(['i.id', 'i.lot_code', 'i.source_company_id', 'b.qty', 'b.location_id', 'l.code as rack']) as $row) {
            $lots[] = ['lot' => RowHandle::of('inventory_lots', (int) Row::text($row, 'id')), 'code' => Row::text($row, 'lot_code'), 'location' => RowHandle::of('locations', (int) Row::text($row, 'location_id')), 'rack' => Row::text($row, 'rack'), 'qty' => StockFigures::plain(Row::text($row, 'qty')), 'other' => (int) Row::text($row, 'source_company_id') !== $companyId, 'oldest' => $first];
            $first = false;
        }

        $serials = array_values(array_map(fn (object $row): array => ['handle' => RowHandle::of('serial_units', (int) Row::text($row, 'id')), 'serial' => Row::text($row, 'serial_number'), 'lot' => RowHandle::of('inventory_lots', (int) Row::text($row, 'current_lot_id')), 'location' => RowHandle::of('locations', (int) Row::text($row, 'current_location_id'))],
            DB::table('serial_units')->where('product_id', $product)->where('state', 'IN_STOCK')->where('current_condition', 'USABLE')->orderBy('serial_number')->limit(self::CHOICES)->get(['id', 'serial_number', 'current_lot_id', 'current_location_id'])->all()));

        return ['line' => $handle, 'lots' => $lots, 'serials' => $serials];
    }

    /**
     * The likely duplicates' labels (IP-05).
     *
     * @param  list<string>  $publicIds
     * @return list<array{id: string, label: string, href: null}>
     */
    public function matches(array $publicIds): array
    {
        return array_values(array_map(fn (object $row): array => ['id' => Row::text($row, 'public_id'), 'label' => Row::text($row, 'business_date').' · '.Row::text($row, 'movement_type'), 'href' => null],
            DB::table('stock_movements')->whereIn('public_id', $publicIds)->orderBy('id')->get(['public_id', 'business_date', 'movement_type'])->all()));
    }

    /** A line of the project by its handle. */
    public function line(int $projectId, string $handle): ?int
    {
        foreach (DB::table('project_lines')->where('project_id', $projectId)->pluck('id') as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('project_lines', (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    public function productOf(int $lineId): int
    {
        $id = DB::table('project_lines')->where('id', $lineId)->value('product_id');

        return is_numeric($id) ? (int) $id : 0;
    }

    public function unit(int $lineId, string $code): ?int
    {
        $id = DB::table('project_lines as l')->join('product_units as pu', 'pu.product_id', '=', 'l.product_id')->join('units as u', 'u.id', '=', 'pu.unit_id')->where('l.id', $lineId)->where('u.code', $code)->value('u.id');

        return is_numeric($id) ? (int) $id : null;
    }

    /** A lot of the line's product by its handle (CS-05: the pool), in whatever state: the command re-reads it under its lock, and a replay still finds its answer (CI-05). */
    public function lot(int $productId, string $handle): ?int
    {
        return self::found(DB::table('inventory_lots')->where('product_id', $productId)->pluck('id')->all(), 'inventory_lots', $handle);
    }

    /** A rack by its handle (CS-05). */
    public function location(string $handle): ?int
    {
        return self::found(DB::table('locations')->pluck('id')->all(), 'locations', $handle);
    }

    /** A serial of the product by its handle, in whatever state. */
    public function serial(int $productId, string $handle): ?int
    {
        return self::found(DB::table('serial_units')->where('product_id', $productId)->pluck('id')->all(), 'serial_units', $handle);
    }

    /** A consumption of the dispatch line by its handle. */
    public function consumption(int $dispatchLineId, string $handle): ?int
    {
        return self::found(DB::table('stock_consumptions')->where('dispatch_line_id', $dispatchLineId)->pluck('id')->all(), 'stock_consumptions', $handle);
    }

    /** An evidence of the company the viewer may see, by public id (PJ-08; CS-04). */
    public function evidence(int $viewerId, int $companyId, string $publicId): ?int
    {
        $row = preg_match(self::UUID, $publicId) === 1 ? DB::table('evidence_documents')->where('company_id', $companyId)->where('public_id', strtolower($publicId))->first(['id', 'evidence_type']) : null;

        return $row !== null && EvidenceTypes::mayView($this->capabilities->for($viewerId), Row::text($row, 'evidence_type'), $companyId) ? (int) Row::text($row, 'id') : null;
    }

    /** @param  array<int|string, mixed>  $ids */
    private static function found(array $ids, string $table, string $handle): ?int
    {
        foreach ($ids as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of($table, (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }
}
