<?php

namespace App\Modules\INV\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Data\StockFigures;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-018 *Stok* and PG-019 *Detail Stok Produk*'s physical reads (ROUTE-018; ROUTE-019; PJ-01–PJ-04; PJ-07; CS-05; AC-13; DATABASE §5.1): the pool, read with `stock.view`. This class never selects a cost table: a lot's cost and basis come from CST's `CostFigures`, by family. A lot of a company outside scope ends at its code, quantity, rack and condition, with the relation marker.
 */
final class StockViews
{
    public const PAGE = 50;

    public const LOTS = 100;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /**
     * PG-018 per product: the four quantities, the pending line, the restock advice over usable availability (BR-INV-10); keyset by name.
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string}
     */
    public function products(?string $search, ?string $after): array
    {
        $cursor = is_string($after) && preg_match(self::UUID, $after) === 1 ? DB::table('products')->where('public_id', strtolower($after))->first(['id', 'name']) : null;
        $term = $search === null ? null : '%'.addcslashes(mb_strtolower(trim($search)), '%_\\').'%';
        $found = DB::table('stock_product_balances as b')->join('products as p', 'p.id', '=', 'b.product_id')->join('units as u', 'u.id', '=', 'p.base_unit_id')->where('p.kind', 'GOODS')
            ->when($term !== null, fn (Builder $where) => $where->where(fn (Builder $any) => $any->whereRaw('lower(p.name) like ?', [$term])->orWhereRaw('lower(p.sku) like ?', [$term])))
            ->when(is_object($cursor), fn (Builder $where) => $where->whereRaw('(lower(p.name), p.id) > (lower(?), ?)', is_object($cursor) ? [Row::text($cursor, 'name'), (int) Row::text($cursor, 'id')] : []))
            ->orderByRaw('lower(p.name)')->orderBy('p.id')->limit(self::PAGE + 1)
            ->get(['p.id', 'p.public_id', 'p.name', 'p.sku', 'p.min_stock_qty_base', 'u.code as unit', 'b.on_hand_qty', 'b.reserved_qty', 'b.unusable_qty', self::pendingColumn()])->all();
        $page = array_slice($found, 0, self::PAGE);
        $alternates = self::alternates(array_map(fn (object $row): int => (int) Row::text($row, 'id'), $page));
        $rows = array_map(fn (object $row): array => self::productRow($row, $alternates[(int) Row::text($row, 'id')] ?? null), $page);

        return ['rows' => $rows, 'more' => count($found) > self::PAGE && $page !== [] ? Row::text($page[count($page) - 1], 'public_id') : null];
    }

    /**
     * PG-018 per rack and condition (D-IA-17); keyset by rack, product and condition.
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string}
     */
    public function places(?string $after): array
    {
        $parts = is_string($after) ? explode('|', $after, 3) : [];
        $found = DB::table('stock_scope_balances as s')->join('products as p', 'p.id', '=', 's.product_id')->join('locations as l', 'l.id', '=', 's.location_id')->join('units as u', 'u.id', '=', 'p.base_unit_id')->where('s.qty', '<>', 0)
            ->when(count($parts) === 3, fn (Builder $where) => $where->whereRaw('(l.code, p.public_id::text, s.condition) > (?, ?, ?)', $parts))
            ->orderBy('l.code')->orderByRaw('p.public_id::text')->orderBy('s.condition')->limit(self::PAGE + 1)
            ->get(['l.code as rack', 'p.public_id', 'p.name', 'p.sku', 's.condition', 's.qty', 'u.code as unit'])->all();
        $rows = array_map(fn (object $row): array => ['id' => Row::text($row, 'public_id'), 'rack' => Row::text($row, 'rack'), 'name' => Row::text($row, 'name'), 'sku' => Row::nullable($row, 'sku'), 'condition' => Row::text($row, 'condition'), 'qty' => StockFigures::plain(Row::text($row, 'qty')), 'unit' => Row::text($row, 'unit'), 'href' => '/gudang/stok/'.Row::text($row, 'public_id')], array_slice($found, 0, self::PAGE));
        $last = $rows === [] ? null : $rows[count($rows) - 1];

        return ['rows' => $rows, 'more' => count($found) > self::PAGE && $last !== null ? "{$last['rack']}|{$last['id']}|{$last['condition']}" : null];
    }

    /**
     * PG-019's head: the product's figures, its racks, its pending line and reservations; an unknown id null (AZ-11; IP-21).
     *
     * @return array<string, mixed>|null
     */
    public function product(int $viewerId, string $publicId): ?array
    {
        $product = preg_match(self::UUID, $publicId) === 1 ? DB::table('products as p')->leftJoin('stock_product_balances as b', 'b.product_id', '=', 'p.id')->join('units as u', 'u.id', '=', 'p.base_unit_id')->where('p.public_id', strtolower($publicId))->where('p.kind', 'GOODS')
            ->first(['p.id', 'p.public_id', 'p.name', 'p.sku', 'p.min_stock_qty_base', 'p.is_serialized', 'u.code as unit', 'b.on_hand_qty', 'b.reserved_qty', 'b.unusable_qty', self::pendingColumn()]) : null;

        if ($product === null) {
            return null;
        }

        $viewer = $this->capabilities->for($viewerId);
        $id = (int) Row::text($product, 'id');
        $alternate = self::alternates([$id])[$id] ?? null;

        return [
            'product' => [...self::productRow($product, $alternate), 'serialized' => (bool) $product->is_serialized, 'internal' => $id],
            'places' => array_map(fn (object $row): array => ['rack' => Row::text($row, 'code'), 'condition' => Row::text($row, 'condition'), 'qty' => StockFigures::plain(Row::text($row, 'qty'))],
                DB::table('stock_scope_balances as s')->join('locations as l', 'l.id', '=', 's.location_id')->where('s.product_id', $id)->where('s.qty', '<>', 0)->orderBy('l.code')->orderBy('s.condition')->get(['l.code', 's.condition', 's.qty'])->all()),
            'reservations' => $viewer->has('stock.view') ? $this->reservations($viewer, $id) : [],
        ];
    }

    /**
     * PG-019's deferred group (QB-05): lots with their oldest-first picking aid (BR-INV-04) and serials with their presence, at most three queries.
     *
     * @return array{lots: list<array<string, mixed>>, serials: array<int, array<string, string>>, internal: list<int>}
     */
    public function group(int $viewerId, int $productId): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $lots = [];
        $internal = [];
        $first = true;

        foreach (DB::table('stock_lot_balances as b')->join('inventory_lots as i', 'i.id', '=', 'b.lot_id')->join('locations as l', 'l.id', '=', 'b.location_id')->join('companies as c', 'c.id', '=', 'i.source_company_id')->where('b.product_id', $productId)->where('b.qty', '>', 0)
            ->orderBy('i.business_date')->orderBy('i.id')->orderBy('l.code')->limit(self::LOTS)
            ->get(['i.id', 'i.lot_code', 'i.source_company_id', 'i.acquisition_kind', 'i.business_date', 'i.acquired_qty_base', 'b.qty', 'b.condition', 'l.code as rack', 'c.code as company_code', 'c.legal_name']) as $row) {
            $id = (int) Row::text($row, 'id');
            $physical = ['id' => RowHandle::of('inventory_lots', $id), 'code' => Row::text($row, 'lot_code'), 'qty' => StockFigures::plain(Row::text($row, 'qty')), 'rack' => Row::text($row, 'rack'), 'condition' => Row::text($row, 'condition'), 'oldest' => $first];
            $first = false;
            $internal[] = $id;
            $lots[] = $viewer->scope->includes((int) Row::text($row, 'source_company_id'))
                ? [...$physical, 'other' => false, 'company' => Row::text($row, 'company_code').' · '.Row::text($row, 'legal_name'), 'kind' => Row::text($row, 'acquisition_kind'), 'date' => Row::text($row, 'business_date'), 'acquired' => StockFigures::plain(Row::text($row, 'acquired_qty_base'))]
                : [...$physical, 'other' => true];
        }

        $serials = array_map(fn (object $row): array => ['serial' => Row::text($row, 'serial_number'), 'lot' => Row::text($row, 'lot_code'), 'rack' => Row::text($row, 'rack'), 'condition' => Row::text($row, 'current_condition')],
            DB::table('serial_units as s')->join('inventory_lots as i', 'i.id', '=', 's.current_lot_id')->join('locations as l', 'l.id', '=', 's.current_location_id')->where('s.product_id', $productId)->where('s.state', 'IN_STOCK')->orderBy('s.serial_number')->limit(self::LOTS)
                ->get(['s.serial_number', 'i.lot_code', 'l.code as rack', 's.current_condition'])->all());

        return ['lots' => $lots, 'serials' => $serials, 'internal' => $internal];
    }

    /**
     * The product's active reservations: a project named with `projects.view` where its company is in scope, otherwise the quantity and the relation marker only (row 61; PJ-04; IP-20).
     *
     * @return list<array{id: string, reserved: string, project: array{label: string, href: string}|null}>
     */
    public function reservations(Authority $viewer, int $productId): array
    {
        $rows = [];

        foreach (DB::table('reservations as r')->join('project_lines as l', 'l.id', '=', 'r.project_line_id')->join('projects as p', 'p.id', '=', 'l.project_id')->where('r.product_id', $productId)->where('r.state', 'ACTIVE')->orderBy('r.id')->limit(self::PAGE)
            ->get(['r.id', 'r.company_id', 'r.remaining_qty_base', 'p.project_number', 'p.public_id']) as $row) {
            $company = (int) Row::text($row, 'company_id');
            $rows[] = ['id' => RowHandle::of('reservations', (int) Row::text($row, 'id')), 'reserved' => StockFigures::plain(Row::text($row, 'remaining_qty_base')),
                'project' => $viewer->scope->includes($company) && $viewer->allows('projects.view', $company) ? ['label' => Row::text($row, 'project_number'), 'href' => '/proyek/'.Row::text($row, 'public_id')] : null];
        }

        return $rows;
    }

    /** The pending quantity of the product's LOSS cases, outside every company's figures (DIR-027; UXS-39; PJ-07). */
    private static function pendingColumn(): Expression
    {
        return DB::raw("(select coalesce(sum(c.pending_qty_base), 0) from unattributed_loss_cases c where c.product_id = p.id and c.case_kind = 'LOSS') as pending_qty");
    }

    /**
     * Each product's largest alternate unit, for the hint.
     *
     * @param  list<int>  $products
     * @return array<int, array{unit: string, ratio: int}>
     */
    private static function alternates(array $products): array
    {
        $found = [];

        foreach ($products === [] ? [] : DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')->whereIn('pu.product_id', $products)->where('pu.is_base', false)->where('pu.is_active', true)->orderBy('pu.ratio_to_base')->get(['pu.product_id', 'u.code', 'pu.ratio_to_base']) as $row) {
            $found[(int) Row::text($row, 'product_id')] = ['unit' => Row::text($row, 'code'), 'ratio' => (int) Row::text($row, 'ratio_to_base')];
        }

        return $found;
    }

    /**
     * @param  array{unit: string, ratio: int}|null  $alternate
     * @return array<string, mixed>
     */
    private static function productRow(object $row, ?array $alternate): array
    {
        $figures = StockFigures::of(Row::nullable($row, 'on_hand_qty') ?? '0', Row::nullable($row, 'reserved_qty') ?? '0', Row::nullable($row, 'unusable_qty') ?? '0', Row::text($row, 'unit'), $alternate['unit'] ?? null, $alternate['ratio'] ?? null);
        $minimum = Row::nullable($row, 'min_stock_qty_base');
        $pending = StockFigures::plain(Row::text($row, 'pending_qty'));

        return ['id' => Row::text($row, 'public_id'), 'name' => Row::text($row, 'name'), 'sku' => Row::nullable($row, 'sku'), 'figures' => $figures, 'pending' => $pending === '0' ? null : $pending,
            'below' => $minimum !== null && BigDecimal::of($figures['available'])->isLessThan($minimum) ? StockFigures::plain($minimum) : null, 'href' => '/gudang/stok/'.Row::text($row, 'public_id')];
    }
}
