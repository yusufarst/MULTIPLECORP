<?php

namespace App\Modules\INV\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Services\DocumentAccess;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * PG-021 *Barang Masuk* and PG-022 *Terima Barang*'s reads (ROUTE-021; ROUTE-022; NAV-021; NAV-022; QS-05; PJ-18): the receiving projection — purchase number, supplier name, project link, product and quantities, never a price, tax, charge or cost, the scope inside the query (CS-07; PF-13).
 */
final class ReceivingQueries
{
    public const PAGE = 50;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /**
     * The companies whose receipts the viewer may record, each a group of its own (NAV-021).
     *
     * @return list<array{id: string, code: string, name: string, internal: int}>
     */
    public function groups(int $viewerId): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $groups = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']) as $company) {
            if ($viewer->allows('stock.receive', (int) Row::text($company, 'id'))) {
                $groups[] = ['id' => Row::text($company, 'public_id'), 'code' => Row::text($company, 'code'), 'name' => Row::text($company, 'legal_name'), 'internal' => (int) Row::text($company, 'id')];
            }
        }

        return $groups;
    }

    /**
     * One company's OPEN purchases with an open warehouse remainder (QS-05), one query, by the oldest first; one more read to know whether more follow.
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string}
     */
    public function worklist(int $viewerId, int $companyId, ?string $after): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $cursor = is_string($after) && preg_match(self::UUID, $after) === 1 ? DB::table('purchases')->where('public_id', strtolower($after))->where('company_id', $companyId)->value('id') : null;
        $found = $viewer->scope->apply(DB::table('purchases as x'), 'x.company_id')->where('x.company_id', $companyId)->where('x.state', 'OPEN')
            ->join('purchase_lines as l', 'l.purchase_id', '=', 'x.id')->join('purchase_line_balances as b', 'b.purchase_line_id', '=', 'l.id')->leftJoin('projects as p', 'p.id', '=', 'x.project_id')
            ->where('l.kind', 'GOODS')->where('l.delivery_mode', 'WAREHOUSE')->whereRaw('(b.ordered_qty_base > b.normal_received_net_qty_base + b.closed_qty_base or b.replacement_expected_qty_base > b.replacement_received_net_qty_base)')
            ->when(is_numeric($cursor), fn ($where) => $where->where('x.id', '>', is_numeric($cursor) ? (int) $cursor : 0))
            ->groupBy('x.id', 'x.public_id', 'x.purchase_number', 'x.supplier_snapshot', 'x.business_date', 'p.project_number')->orderBy('x.id')->limit(self::PAGE + 1)
            ->selectRaw('x.public_id, x.purchase_number, x.supplier_snapshot, x.business_date, p.project_number, count(*) as lines, sum(b.ordered_qty_base - b.normal_received_net_qty_base - b.closed_qty_base + b.replacement_expected_qty_base - b.replacement_received_net_qty_base) as remaining')->get()->all();
        $rows = [];

        foreach (array_slice($found, 0, self::PAGE) as $row) {
            $supplier = json_decode(Row::text($row, 'supplier_snapshot'), true);
            $rows[] = ['id' => Row::text($row, 'public_id'), 'number' => Row::text($row, 'purchase_number'), 'supplier' => is_array($supplier) && is_string($supplier['name'] ?? null) ? $supplier['name'] : '', 'project' => Row::nullable($row, 'project_number'), 'date' => Row::text($row, 'business_date'), 'lines' => (int) Row::text($row, 'lines'), 'remaining' => Row::text($row, 'remaining'), 'href' => '/gudang/barang-masuk/'.Row::text($row, 'public_id')];
        }

        return ['rows' => $rows, 'more' => count($found) > self::PAGE && $rows !== [] ? $rows[count($rows) - 1]['id'] : null];
    }

    /**
     * The purchase by public id, to `stock.receive` of its company; otherwise unknown (AZ-11; CS-04).
     *
     * @return array{id: int, company: int, project: ?int, public_id: string}|null
     */
    public function purchase(int $viewerId, string $publicId, string $capability = 'stock.receive'): ?array
    {
        $row = preg_match(self::UUID, $publicId) === 1 ? DB::table('purchases')->where('public_id', strtolower($publicId))->first(['id', 'company_id', 'project_id', 'public_id']) : null;

        if ($row === null || ! $this->capabilities->for($viewerId)->allows($capability, (int) Row::text($row, 'company_id'))) {
            return null;
        }

        $project = Row::nullable($row, 'project_id');

        return ['id' => (int) Row::text($row, 'id'), 'company' => (int) Row::text($row, 'company_id'), 'project' => $project === null ? null : (int) $project, 'public_id' => Row::text($row, 'public_id')];
    }

    /** @return array<string, mixed> */
    public function screen(int $viewerId, int $purchaseId, int $companyId): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $purchase = DB::table('purchases as x')->leftJoin('projects as p', 'p.id', '=', 'x.project_id')->join('companies as c', 'c.id', '=', 'x.company_id')->where('x.id', $purchaseId)
            ->first(['x.public_id', 'x.purchase_number', 'x.supplier_snapshot', 'x.state', 'c.code', 'c.public_id as company', 'p.public_id as project', 'p.project_number', 'p.state as project_state']);
        $supplier = $purchase === null ? null : json_decode(Row::text($purchase, 'supplier_snapshot'), true);
        $lines = $this->lines($viewer, $purchaseId, $companyId, $purchase === null ? null : Row::nullable($purchase, 'project_state'));

        return [
            'purchase' => $purchase === null ? null : ['id' => Row::text($purchase, 'public_id'), 'number' => Row::text($purchase, 'purchase_number'), 'state' => Row::text($purchase, 'state'), 'supplier' => is_array($supplier) && is_string($supplier['name'] ?? null) ? $supplier['name'] : '', 'company' => Row::text($purchase, 'code'), 'company_id' => Row::text($purchase, 'company'), 'project' => Row::nullable($purchase, 'project'), 'project_number' => Row::nullable($purchase, 'project_number')],
            'lines' => $lines,
            'cases' => array_filter($lines, fn (array $line): bool => BigDecimal::of($line['replacement'])->isPositive()) !== [] ? $this->cases($purchaseId, $companyId) : [],
            'receipts' => $this->receipts($purchaseId),
            'locations' => array_values(array_map(fn (object $row): array => ['id' => (int) Row::text($row, 'id') > 0 ? RowHandle::of('locations', (int) Row::text($row, 'id')) : '', 'code' => Row::text($row, 'code'), 'name' => Row::text($row, 'name')], DB::table('locations')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])->all())),
            'today' => BusinessDate::today(),
            'can' => ['reserve' => $viewer->allows('stock.reserve', $companyId), 'note' => DocumentAccess::mayIssue($viewer, 'DOC-12', $companyId), 'evidence' => $viewer->allows('evidence.upload', $companyId), 'barcode' => $viewer->allows('products.edit')],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lines(Authority $viewer, int $purchaseId, int $companyId, ?string $projectState): array
    {
        $rows = DB::table('purchase_lines as l')->join('purchase_line_balances as b', 'b.purchase_line_id', '=', 'l.id')->join('products as r', 'r.id', '=', 'l.product_id')->leftJoin('project_line_balances as d', 'd.project_line_id', '=', 'l.project_line_id')
            ->where('l.purchase_id', $purchaseId)->where('l.kind', 'GOODS')->where('l.delivery_mode', 'WAREHOUSE')->orderBy('l.line_no')
            ->get(['l.id', 'l.line_no', 'l.description', 'l.product_id', 'l.project_line_id', 'r.public_id as product', 'r.name', 'r.sku', 'r.is_serialized', 'r.quantity_scale', 'b.ordered_qty_base', 'b.normal_received_net_qty_base', 'b.closed_qty_base',
                DB::raw('b.ordered_qty_base - b.normal_received_net_qty_base - b.closed_qty_base as remaining'),
                DB::raw('b.replacement_expected_qty_base - b.replacement_received_net_qty_base as replacement'),
                DB::raw('greatest(d.demand_qty_base - d.dispatched_net_qty_base - d.cancelled_qty_base - d.reserved_qty_base, 0) as reservable')])->all();
        $units = DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')->whereIn('pu.product_id', array_map(fn (object $row): int => (int) Row::text($row, 'product_id'), $rows))->where('pu.is_active', true)->orderByDesc('pu.is_base')->orderBy('u.code')->get(['pu.product_id', 'u.code', 'u.name', 'pu.ratio_to_base'])->all();
        $offer = $projectState === 'ACTIVE' && $viewer->allows('stock.reserve', $companyId);
        $lines = [];

        foreach ($rows as $row) {
            $product = (int) Row::text($row, 'product_id');
            $lines[] = [
                'handle' => RowHandle::of('purchase_lines', (int) Row::text($row, 'id')), 'no' => (int) Row::text($row, 'line_no'), 'description' => Row::text($row, 'description'), 'product' => Row::text($row, 'product'), 'name' => Row::text($row, 'name'), 'sku' => Row::nullable($row, 'sku'), 'serialized' => (bool) $row->is_serialized, 'scale' => (int) Row::text($row, 'quantity_scale'),
                'ordered' => Row::text($row, 'ordered_qty_base'), 'received' => Row::text($row, 'normal_received_net_qty_base'), 'closed' => Row::text($row, 'closed_qty_base'), 'remaining' => Row::text($row, 'remaining'), 'replacement' => Row::text($row, 'replacement'),
                'reservable' => $offer && Row::nullable($row, 'project_line_id') !== null ? Row::nullable($row, 'reservable') : null,
                'units' => array_values(array_map(fn (object $unit): array => ['code' => Row::text($unit, 'code'), 'name' => Row::text($unit, 'name'), 'ratio' => (int) Row::text($unit, 'ratio_to_base')], array_filter($units, fn (object $unit): bool => (int) Row::text($unit, 'product_id') === $product))),
            ];
        }

        return $lines;
    }

    /**
     * The OPEN return cases a replacement receipt of this purchase may cite (C-59).
     *
     * @return list<array{id: string, label: string}>
     */
    private function cases(int $purchaseId, int $companyId): array
    {
        $cases = [];

        foreach (DB::table('correction_cases as c')->leftJoin('inventory_lots as i', 'i.id', '=', 'c.source_lot_id')->leftJoin('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->leftJoin('purchase_lines as l', 'l.id', '=', 'r.purchase_line_id')
            ->where('c.company_id', $companyId)->where('c.case_kind', 'PURCHASE_RETURN')->where('c.state', 'OPEN')->where(fn ($where) => $where->whereNull('c.source_lot_id')->orWhere('l.purchase_id', $purchaseId))->orderBy('c.id')->limit(50)->get(['c.public_id', 'c.reason']) as $row) {
            $cases[] = ['id' => Row::text($row, 'public_id'), 'label' => Row::nullable($row, 'reason') ?? Row::text($row, 'public_id')];
        }

        return $cases;
    }

    /** @return list<array<string, mixed>> */
    private function receipts(int $purchaseId): array
    {
        $receipts = [];

        foreach (DB::table('receiving_lines as r')->join('purchase_lines as l', 'l.id', '=', 'r.purchase_line_id')->join('stock_movements as m', 'm.id', '=', 'r.stock_movement_id')->leftJoin('inventory_lots as i', 'i.receiving_line_id', '=', 'r.id')->where('l.purchase_id', $purchaseId)->orderBy('r.id')->limit(200)
            ->get(['r.id', 'l.line_no', 'r.receipt_kind', 'r.usable_qty_base', 'r.damaged_qty_base', 'r.rejected_qty_base', 'r.supplier_delivery_reference', 'm.public_id', 'm.business_date', 'i.lot_code', 'i.acquired_qty_base',
                DB::raw("(select coalesce(sum(-e.qty_delta_base), 0) from stock_movement_entries e where e.lot_id = i.id and e.movement_type = 'RECEIPT_REVERSAL') as reversed_qty")]) as $row) {
            $acquired = Row::nullable($row, 'acquired_qty_base');
            $receipts[] = ['handle' => RowHandle::of('receiving_lines', (int) Row::text($row, 'id')), 'movement' => Row::text($row, 'public_id'), 'line' => (int) Row::text($row, 'line_no'), 'kind' => Row::text($row, 'receipt_kind'), 'usable' => Row::text($row, 'usable_qty_base'), 'damaged' => Row::text($row, 'damaged_qty_base'), 'rejected' => Row::text($row, 'rejected_qty_base'), 'reference' => Row::nullable($row, 'supplier_delivery_reference'), 'date' => Row::text($row, 'business_date'), 'lot' => Row::nullable($row, 'lot_code'), 'reversed' => $acquired !== null && BigDecimal::of(Row::text($row, 'reversed_qty'))->isGreaterThanOrEqualTo($acquired)];
        }

        return $receipts;
    }

    /** A line of the purchase by its handle. */
    public function line(int $purchaseId, string $handle): ?int
    {
        foreach (DB::table('purchase_lines')->where('purchase_id', $purchaseId)->pluck('id') as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('purchase_lines', (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    /** A rack by its handle (CS-05: the pool), active or not: the command re-reads that, and a replay still finds its answer (CI-05). */
    public function location(string $handle): ?int
    {
        foreach (DB::table('locations')->pluck('id') as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('locations', (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    public function unit(int $lineId, string $code): ?int
    {
        $id = DB::table('purchase_lines as l')->join('product_units as pu', 'pu.product_id', '=', 'l.product_id')->join('units as u', 'u.id', '=', 'pu.unit_id')->where('l.id', $lineId)->where('u.code', $code)->value('u.id');

        return is_numeric($id) ? (int) $id : null;
    }

    /** A receipt of the line by its handle (C-59). */
    public function receipt(int $lineId, string $handle): ?int
    {
        foreach (DB::table('receiving_lines')->where('purchase_line_id', $lineId)->pluck('id') as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('receiving_lines', (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    /** A case of the company, for a replacement receipt: the command re-reads its kind and state (C-59), and a replay still finds its answer (CI-05). */
    public function openCase(int $companyId, string $publicId): ?int
    {
        $id = preg_match(self::UUID, $publicId) === 1 ? DB::table('correction_cases')->where('company_id', $companyId)->where('public_id', strtolower($publicId))->value('id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    /** An evidence of the company the viewer may see, by public id (PJ-08; CS-04). */
    public function evidence(int $viewerId, int $companyId, string $publicId): ?int
    {
        $row = preg_match(self::UUID, $publicId) === 1 ? DB::table('evidence_documents')->where('company_id', $companyId)->where('public_id', strtolower($publicId))->first(['id', 'evidence_type']) : null;

        return $row !== null && EvidenceTypes::mayView($this->capabilities->for($viewerId), Row::text($row, 'evidence_type'), $companyId) ? (int) Row::text($row, 'id') : null;
    }

    /**
     * A lot from its receipt, to `stock.reverse` of its company: its unconsumed places and serials offered, whole or in part (CM-02; CM-03).
     *
     * @return array{id: int, company: int, purchase: string, product: int}|null
     */
    public function lot(int $viewerId, string $handle): ?array
    {
        foreach (DB::table('receiving_lines as r')->join('inventory_lots as i', 'i.receiving_line_id', '=', 'r.id')->join('purchase_lines as l', 'l.id', '=', 'r.purchase_line_id')->join('purchases as p', 'p.id', '=', 'l.purchase_id')
            ->whereIn('r.company_id', $this->capabilities->for($viewerId)->scope->all ? DB::table('companies')->select('id') : $this->capabilities->for($viewerId)->scope->companyIds)->get(['r.id', 'i.id as lot', 'r.company_id', 'r.product_id', 'p.public_id']) as $row) {
            if (hash_equals(RowHandle::of('receiving_lines', (int) Row::text($row, 'id')), $handle) && $this->capabilities->for($viewerId)->allows('stock.reverse', (int) Row::text($row, 'company_id'))) {
                return ['id' => (int) Row::text($row, 'lot'), 'company' => (int) Row::text($row, 'company_id'), 'purchase' => Row::text($row, 'public_id'), 'product' => (int) Row::text($row, 'product_id')];
            }
        }

        return null;
    }

    /** A serial this lot has held, by its handle, in whatever state: the command re-reads it under its lock (C-02), and a replay still finds its answer (CI-05). */
    public function serialOfLot(int $lotId, string $handle): ?int
    {
        foreach (DB::table('stock_movement_entries')->where('lot_id', $lotId)->whereNotNull('serial_unit_id')->distinct()->pluck('serial_unit_id') as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('serial_units', (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    /** A reservation of the product by its handle, for a cut (IP-20), in whatever state: `cut` re-reads it under its lock (C-05). */
    public function reservation(int $productId, string $handle): ?int
    {
        foreach (DB::table('reservations')->where('product_id', $productId)->pluck('id') as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('reservations', (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }
}
