<?php

namespace App\Modules\INV\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Data\StockFigures;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-029 *Selisih & Temuan*'s reads (ROUTE-029; NAV-029; RD-09; PJ-07; PJ-22; UXS-39): the open findings and the pending cases physically to every viewer; a case's candidates and exposures by PJ-07 alone — the Owner all, an evidence holder the lots and quantities without costs for companies in scope, a `cost.view` holder that company's own rows —; the number and identity of other candidate companies never to a non-Owner.
 */
final class DiscrepancyQueries
{
    public const PAGE = 50;

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
    ) {}

    /** @return array<string, bool> */
    public function can(int $viewerId): array
    {
        $viewer = $this->capabilities->for($viewerId);

        return ['surplus' => $viewer->has('stock.attribute_surplus'), 'resolve' => $viewer->has('stock.resolve_unattributed'), 'evidence' => $viewer->has('stock.resolve_unattributed_evidence'), 'upload' => $viewer->has('evidence.upload')];
    }

    /**
     * The open findings, newest first: quantity and state to every viewer; an attribution's company and basis are not here (row 66).
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string, cursor_failed: bool}
     */
    public function findings(int $viewerId, ?string $after = null): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $key = $after === null ? null : StockCursor::read($viewer, $after, ['list' => 'findings'], 1);

        if ($after !== null && $key === null) {
            return ['rows' => [], 'more' => null, 'cursor_failed' => true];
        }

        $found = DB::table('stock_count_findings as f')->join('products as p', 'p.id', '=', 'f.product_id')->join('units as u', 'u.id', '=', 'p.base_unit_id')->join('locations as l', 'l.id', '=', 'f.location_id')->where('f.state', 'OPEN')->when($key !== null, fn (Builder $where) => $where->where('f.id', '<', (int) ($key[0] ?? 0)))->orderByDesc('f.id')->limit(self::PAGE + 1)
            ->get(['f.id', 'f.surplus_qty_base', 'f.condition', 'f.recorded_at', 'f.product_id', 'f.location_id', 'p.name', 'p.is_serialized', 'p.quantity_scale', 'u.code as unit', 'l.code as rack'])->all();
        $page = array_slice($found, 0, self::PAGE);
        $last = $page === [] ? null : $page[count($page) - 1];

        return ['rows' => array_map(fn (object $row): array => ['id' => RowHandle::of('stock_count_findings', (int) Row::text($row, 'id')), 'product' => Row::text($row, 'name'), 'unit' => Row::text($row, 'unit'), 'rack' => Row::text($row, 'rack'), 'condition' => Row::text($row, 'condition'), 'qty' => StockFigures::plain(Row::text($row, 'surplus_qty_base')), 'date' => substr(Row::text($row, 'recorded_at'), 0, 10), 'serialized' => (bool) Row::text($row, 'is_serialized'), 'scale' => (int) Row::text($row, 'quantity_scale'), 'losses' => $viewer->has('stock.attribute_surplus') ? self::identifiedLosses((int) Row::text($row, 'product_id'), (int) Row::text($row, 'location_id'), Row::text($row, 'condition')) : []], $page),
            'more' => count($found) > self::PAGE && $last !== null ? StockCursor::of($viewer, [(int) Row::text($last, 'id')], ['list' => 'findings']) : null,
            'cursor_failed' => false,
        ];
    }

    /**
     * The fungible losses of a finding's scope the Owner may reverse first (AX-07).
     *
     * @return list<array{id: string, date: string, code: string, open: string}>
     */
    private static function identifiedLosses(int $product, int $location, string $condition): array
    {
        return array_values(array_map(fn (object $row): array => ['id' => RowHandle::of('stock_consumptions', (int) Row::text($row, 'id')), 'date' => Row::text($row, 'business_date'), 'code' => Row::text($row, 'lot_code'), 'open' => StockFigures::plain(Row::text($row, 'open'))],
            DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->join('stock_movement_entries as e', 'e.stock_consumption_id', '=', 'c.id')->join('inventory_lots as i', 'i.id', '=', 'c.lot_id')->join('stock_movements as m', 'm.id', '=', 'c.stock_movement_id')->where('c.product_id', $product)->where('e.location_id', $location)->where('e.condition', $condition)->whereNull('e.serial_unit_id')->whereIn('c.kind', ['LOSS', 'UNATTRIBUTED_LOSS'])->whereRaw('b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base > 0')->orderByDesc('c.id')->limit(20)
                ->get(['c.id', 'm.business_date', 'i.lot_code', DB::raw('b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base as open')])->all()));
    }

    /**
     * The cases still pending, oldest first: the physical result to every viewer, the snapshot by PJ-07.
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string, cursor_failed: bool}
     */
    public function cases(int $viewerId, ?string $after = null): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $key = $after === null ? null : StockCursor::read($viewer, $after, ['list' => 'cases'], 1);

        if ($after !== null && $key === null) {
            return ['rows' => [], 'more' => null, 'cursor_failed' => true];
        }

        $cases = [];
        $found = DB::table('unattributed_loss_cases as k')->join('products as p', 'p.id', '=', 'k.product_id')->join('units as u', 'u.id', '=', 'p.base_unit_id')->join('locations as l', 'l.id', '=', 'k.location_id')->join('users as a', 'a.id', '=', 'k.recognized_by')->where('k.pending_qty_base', '>', 0)->when($key !== null, fn (Builder $where) => $where->where('k.id', '>', (int) ($key[0] ?? 0)))->orderBy('k.id')->limit(self::PAGE + 1)
            ->get(['k.id', 'k.public_id', 'k.case_kind', 'k.condition', 'k.recognized_qty_base', 'k.found_qty_base', 'k.resolved_qty_base', 'k.pending_qty_base', 'k.business_date', 'k.reason', 'k.exposure_group_case_id', 'p.name', 'u.code as unit', 'l.code as rack', 'a.name as actor'])->all();
        $rows = array_slice($found, 0, self::PAGE);
        $last = $rows === [] ? null : $rows[count($rows) - 1];
        $ids = array_map(fn (object $row): int => (int) Row::text($row, 'id'), $rows);
        $snapshot = $this->snapshot($viewerId, $ids);

        foreach ($rows as $row) {
            $id = (int) Row::text($row, 'id');
            $cases[] = [
                'id' => Row::text($row, 'public_id'), 'kind' => Row::text($row, 'case_kind'), 'product' => Row::text($row, 'name'), 'unit' => Row::text($row, 'unit'), 'rack' => Row::text($row, 'rack'), 'condition' => Row::text($row, 'condition'), 'date' => Row::text($row, 'business_date'), 'reason' => Row::text($row, 'reason'), 'actor' => Row::text($row, 'actor'),
                'recognized' => StockFigures::plain(Row::text($row, 'recognized_qty_base')), 'found' => StockFigures::plain(Row::text($row, 'found_qty_base')), 'resolved' => StockFigures::plain(Row::text($row, 'resolved_qty_base')), 'pending' => StockFigures::plain(Row::text($row, 'pending_qty_base')),
                'candidates' => $snapshot[$id]['candidates'] ?? [],
                'exposures' => $snapshot[$id]['exposures'] ?? [],
                'lots' => $viewer->has('stock.resolve_unattributed') || $viewer->has('stock.resolve_unattributed_evidence') ? $this->liveLots($viewerId, $id) : [],
            ];
        }

        return ['rows' => $cases, 'more' => count($found) > self::PAGE && $last !== null ? StockCursor::of($viewer, [(int) Row::text($last, 'id')], ['list' => 'cases']) : null, 'cursor_failed' => false];
    }

    /**
     * Resolutions not yet reversed: the case's physical result to every viewer, the decider, reason and lines to the Owner and a `cost.view` holder of an assigned company (PJ-07; PJ-11).
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string, cursor_failed: bool}
     */
    public function resolutions(int $viewerId, ?string $after = null): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $key = $after === null ? null : StockCursor::read($viewer, $after, ['list' => 'resolutions'], 1);

        if ($after !== null && $key === null) {
            return ['rows' => [], 'more' => null, 'cursor_failed' => true];
        }

        $list = [];
        $found = DB::table('unattributed_loss_resolutions as r')->join('unattributed_loss_cases as k', 'k.id', '=', 'r.unattributed_loss_case_id')->join('products as p', 'p.id', '=', 'k.product_id')->join('locations as l', 'l.id', '=', 'k.location_id')->join('users as a', 'a.id', '=', 'r.decided_by')
            ->whereNotExists(fn (Builder $back) => $back->from('stock_movements as x')->whereColumn('x.reverses_movement_id', 'r.stock_movement_id')->where('x.movement_type', 'UNATTRIBUTED_RESOLUTION_REVERSAL'))->when($key !== null, fn (Builder $where) => $where->where('r.id', '<', (int) ($key[0] ?? 0)))->orderByDesc('r.id')->limit(self::PAGE + 1)
            ->get(['r.id', 'r.resolution_kind', 'r.business_date', 'r.reason', 'a.name as actor', 'p.name', 'l.code as rack'])->all();
        $rows = array_slice($found, 0, self::PAGE);
        $last = $rows === [] ? null : $rows[count($rows) - 1];
        $ids = array_map(fn (object $row): int => (int) Row::text($row, 'id'), $rows);
        $inScope = [];
        $lines = [];

        foreach ($ids === [] ? [] : DB::table('unattributed_loss_resolution_lines as x')->join('companies as c', 'c.id', '=', 'x.assigned_company_id')->whereIn('x.unattributed_loss_resolution_id', $ids)->orderBy('c.code')->get(['x.unattributed_loss_resolution_id', 'x.assigned_company_id', 'x.assigned_qty_base', 'c.code']) as $line) {
            // A reversal needs the company it charged in scope (AZ-08).
            $resolution = (int) Row::text($line, 'unattributed_loss_resolution_id');
            $inScope[$resolution] = ($inScope[$resolution] ?? true) && $viewer->scope->includes((int) Row::text($line, 'assigned_company_id'));

            if ($viewer->ownerRole || $viewer->allows('cost.view', (int) Row::text($line, 'assigned_company_id'))) {
                $lines[(int) Row::text($line, 'unattributed_loss_resolution_id')][] = ['company' => Row::text($line, 'code'), 'qty' => StockFigures::plain(Row::text($line, 'assigned_qty_base'))];
            }
        }

        foreach ($rows as $row) {
            $id = (int) Row::text($row, 'id');
            $evidence = Row::text($row, 'resolution_kind') === 'EVIDENCE_IDENTIFIED';
            $shown = isset($lines[$id]);
            $list[] = ['id' => RowHandle::of('unattributed_loss_resolutions', $id), 'kind' => Row::text($row, 'resolution_kind'), 'product' => Row::text($row, 'name'), 'rack' => Row::text($row, 'rack'), 'date' => Row::text($row, 'business_date'), 'reason' => $shown ? Row::text($row, 'reason') : null, 'actor' => $shown ? Row::text($row, 'actor') : null, 'lines' => $lines[$id] ?? [],
                'reversible' => $viewer->has('stock.resolve_unattributed') || ($evidence && $viewer->has('stock.resolve_unattributed_evidence') && ($inScope[$id] ?? false))];
        }

        return ['rows' => $list, 'more' => count($found) > self::PAGE && $last !== null ? StockCursor::of($viewer, [(int) Row::text($last, 'id')], ['list' => 'resolutions']) : null, 'cursor_failed' => false];
    }

    /**
     * The companies the Owner assigns a finding to.
     *
     * @return list<array{id: string, code: string, name: string}>
     */
    public function companies(int $viewerId): array
    {
        if (! $this->capabilities->for($viewerId)->has('stock.attribute_surplus')) {
            return [];
        }

        return array_values(array_map(fn (object $row): array => ['id' => Row::text($row, 'public_id'), 'code' => Row::text($row, 'code'), 'name' => Row::text($row, 'legal_name')], DB::table('companies')->orderBy('code')->get(['public_id', 'code', 'legal_name'])->all()));
    }

    /** @return array{id: int, product: int, kind: string}|null */
    public function case(string $publicId): ?array
    {
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('unattributed_loss_cases')->where('public_id', strtolower($publicId))->first(['id', 'product_id', 'case_kind']) : null;

        return $row === null ? null : ['id' => (int) Row::text($row, 'id'), 'product' => (int) Row::text($row, 'product_id'), 'kind' => Row::text($row, 'case_kind')];
    }

    public function finding(string $handle): ?int
    {
        return self::found('stock_count_findings', $handle);
    }

    public function resolution(string $handle): ?int
    {
        return self::found('unattributed_loss_resolutions', $handle);
    }

    public function consumption(string $handle): ?int
    {
        return self::found('stock_consumptions', $handle);
    }

    public function lot(string $handle): ?int
    {
        return self::found('inventory_lots', $handle);
    }

    public function company(string $publicId): ?int
    {
        $id = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('companies')->where('public_id', strtolower($publicId))->value('id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function evidence(int $viewerId, string $publicId): ?int
    {
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('evidence_documents')->where('public_id', strtolower($publicId))->where('evidence_scope', 'POOL')->first(['id', 'evidence_type']) : null;

        return $row !== null && EvidenceTypes::mayView($this->capabilities->for($viewerId), Row::text($row, 'evidence_type'), null) ? (int) Row::text($row, 'id') : null;
    }

    /** A resolution's reversal capability: an evidence-identified one's own, or the Owner's (OD-10; OD-15). */
    public function reversalCapability(int $viewerId, int $resolution): string
    {
        $viewer = $this->capabilities->for($viewerId);
        $kind = DB::table('unattributed_loss_resolutions')->where('id', $resolution)->value('resolution_kind');

        return $kind === 'EVIDENCE_IDENTIFIED' && ! $viewer->has('stock.resolve_unattributed') ? 'stock.resolve_unattributed_evidence' : 'stock.resolve_unattributed';
    }

    /** A row by its keyed handle, among the table's rows (RowHandle). */
    private static function found(string $table, string $handle): ?int
    {
        if (! RowHandle::valid($handle)) {
            return null;
        }

        foreach (DB::table($table)->orderByDesc('id')->limit(5000)->pluck('id') as $id) {
            if (is_numeric($id) && RowHandle::of($table, (int) $id) === $handle) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * A case's snapshot by PJ-07, in one read of each table.
     *
     * @param  list<int>  $ids
     * @return array<int, array{candidates?: list<array<string, mixed>>, exposures?: list<array<string, mixed>>}>
     */
    private function snapshot(int $viewerId, array $ids): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $snapshot = [];

        if ($ids === []) {
            return $snapshot;
        }

        foreach (DB::table('unattributed_loss_candidates as c')->join('inventory_lots as i', 'i.id', '=', 'c.lot_id')->join('companies as o', 'o.id', '=', 'c.candidate_company_id')->whereIn('c.unattributed_loss_case_id', $ids)->orderBy('c.id')->get(['c.unattributed_loss_case_id', 'c.candidate_company_id', 'c.lot_id', 'c.qty_in_scope_at_recognition', 'c.net_unit_cost_at_recognition', 'i.lot_code', 'o.code']) as $row) {
            $company = (int) Row::text($row, 'candidate_company_id');
            $cost = $viewer->ownerRole || $viewer->allows('cost.view', $company);
            $lots = $viewer->has('stock.resolve_unattributed_evidence') && $viewer->scope->includes($company);

            if ($viewer->ownerRole || $cost || $lots) {
                $snapshot[(int) Row::text($row, 'unattributed_loss_case_id')]['candidates'][] = ['lot' => RowHandle::of('inventory_lots', (int) Row::text($row, 'lot_id')), 'code' => Row::text($row, 'lot_code'), 'company' => Row::text($row, 'code'), 'qty' => StockFigures::plain(Row::text($row, 'qty_in_scope_at_recognition')), 'cost' => $cost ? Row::text($row, 'net_unit_cost_at_recognition') : null];
            }
        }

        foreach (DB::table('unattributed_loss_exposures as x')->join('companies as o', 'o.id', '=', 'x.company_id')->whereIn('x.unattributed_loss_case_id', $ids)->orderBy('o.code')->get(['x.unattributed_loss_case_id', 'x.company_id', 'x.exposure_qty_base', 'x.assigned_net_qty_base', 'o.code', 'o.public_id']) as $row) {
            $company = (int) Row::text($row, 'company_id');

            if ($viewer->ownerRole || $viewer->allows('cost.view', $company)) {
                $snapshot[(int) Row::text($row, 'unattributed_loss_case_id')]['exposures'][] = ['id' => Row::text($row, 'public_id'), 'company' => Row::text($row, 'code'), 'exposure' => StockFigures::plain(Row::text($row, 'exposure_qty_base')), 'assigned' => StockFigures::plain(Row::text($row, 'assigned_net_qty_base'))];
            }
        }

        return $snapshot;
    }

    /**
     * The lots still holding claims in the case's scope now: what a resolution closes; another company's identity to the Owner alone (PJ-07).
     *
     * @return list<array<string, mixed>>
     */
    private function liveLots(int $viewerId, int $case): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $row = DB::table('unattributed_loss_cases')->where('id', $case)->first(['product_id', 'location_id', 'condition']);

        if ($row === null) {
            return [];
        }

        $lots = [];

        foreach (DB::table('stock_lot_balances as b')->join('inventory_lots as i', 'i.id', '=', 'b.lot_id')->join('companies as o', 'o.id', '=', 'i.source_company_id')->where('b.product_id', (int) Row::text($row, 'product_id'))->where('b.location_id', (int) Row::text($row, 'location_id'))->where('b.condition', Row::text($row, 'condition'))->where('b.qty', '>', 0)->orderBy('i.business_date')->orderBy('i.id')->limit(self::PAGE)->get(['b.lot_id', 'b.qty', 'i.lot_code', 'i.source_company_id', 'o.code', 'o.public_id']) as $lot) {
            $company = (int) Row::text($lot, 'source_company_id');

            if ($viewer->ownerRole || $viewer->scope->includes($company)) {
                $lots[] = ['lot' => RowHandle::of('inventory_lots', (int) Row::text($lot, 'lot_id')), 'code' => Row::text($lot, 'lot_code'), 'qty' => StockFigures::plain(Row::text($lot, 'qty')), 'company' => Row::text($lot, 'code'), 'company_id' => Row::text($lot, 'public_id')];
            }
        }

        return $lots;
    }
}
