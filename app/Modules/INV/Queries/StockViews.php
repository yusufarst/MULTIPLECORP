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

    /** A cut chooses among all of a product's reservations (IP-20): the largest first, within a bound no product's reservations reach. */
    public const CUT_CHOICES = 200;

    /** QS-17's filter: the products under their minimum over usable availability (BR-INV-10). */
    public const BELOW_MINIMUM = 'di_bawah_minimum';

    public const CONDITIONS = ['USABLE', 'UNUSABLE'];

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /** A product's largest alternate unit, in its own head's one statement. */
    private const ALTERNATE_UNIT = '(select au.code from product_units pu join units au on au.id = pu.unit_id where pu.product_id = p.id and pu.is_base = false and pu.is_active = true order by pu.ratio_to_base desc limit 1) as alternate_unit';

    private const ALTERNATE_RATIO = '(select pu.ratio_to_base from product_units pu where pu.product_id = p.id and pu.is_base = false and pu.is_active = true order by pu.ratio_to_base desc limit 1) as alternate_ratio';

    private const RESERVATION = ['r.id', 'r.company_id', 'r.remaining_qty_base', 'p.project_number', 'p.public_id'];

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /**
     * PG-018 per product: the four quantities, the pending line, the restock advice over usable availability (BR-INV-10); keyset by name. Goods with nothing on hand stay: their shortage is what the advice is for.
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string, cursor_failed: bool}
     */
    public function products(int $viewerId, ?string $search, bool $below, ?string $after): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $binding = ['list' => 'products', 'search' => $search, 'below' => $below];
        $key = $after === null ? null : StockCursor::read($viewer, $after, $binding, 2);

        if ($after !== null && $key === null) {
            return ['rows' => [], 'more' => null, 'cursor_failed' => true];
        }

        $term = self::term($search);
        $found = DB::table('products as p')->leftJoin('stock_product_balances as b', 'b.product_id', '=', 'p.id')->join('units as u', 'u.id', '=', 'p.base_unit_id')->where('p.kind', 'GOODS')
            ->where(fn (Builder $live) => $live->where('p.is_active', true)->orWhere('b.on_hand_qty', '<>', 0))
            ->when($term !== null, fn (Builder $where) => self::matching($where, (string) $term))
            ->when($below, fn (Builder $where) => $where->whereRaw('coalesce(b.on_hand_qty, 0) - coalesce(b.reserved_qty, 0) - coalesce(b.unusable_qty, 0) < p.min_stock_qty_base'))
            ->when($key !== null, fn (Builder $where) => $where->whereRaw('(lower(p.name), p.id) > (lower(?), ?::bigint)', $key ?? []))
            ->orderByRaw('lower(p.name)')->orderBy('p.id')->limit(self::PAGE + 1)
            ->get(['p.id', 'p.public_id', 'p.name', 'p.sku', 'p.min_stock_qty_base', 'u.code as unit', 'b.on_hand_qty', 'b.reserved_qty', 'b.unusable_qty', self::pendingColumn()])->all();
        $page = array_slice($found, 0, self::PAGE);
        $alternates = self::alternates(array_map(fn (object $row): int => (int) Row::text($row, 'id'), $page));
        $rows = array_map(fn (object $row): array => self::productRow($row, $alternates[(int) Row::text($row, 'id')] ?? null), $page);
        $last = $page === [] ? null : $page[count($page) - 1];

        return ['rows' => $rows, 'more' => count($found) > self::PAGE && $last !== null ? StockCursor::of($viewer, [Row::text($last, 'name'), (int) Row::text($last, 'id')], $binding) : null, 'cursor_failed' => false];
    }

    /**
     * PG-018 per rack and condition (D-IA-17; IX-12): what stands at that rack in that condition, beside the product's own four quantities — a reservation binds to the product, never to a rack (DATABASE §5.5) —; keyset by rack, product and condition.
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string, cursor_failed: bool}
     */
    public function places(int $viewerId, ?string $search, ?string $condition, ?string $after): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $binding = ['list' => 'places', 'search' => $search, 'condition' => $condition];
        $key = $after === null ? null : StockCursor::read($viewer, $after, $binding, 3);

        if ($after !== null && $key === null) {
            return ['rows' => [], 'more' => null, 'cursor_failed' => true];
        }

        $term = self::term($search);
        $found = DB::table('stock_scope_balances as s')->join('products as p', 'p.id', '=', 's.product_id')->join('locations as l', 'l.id', '=', 's.location_id')->join('units as u', 'u.id', '=', 'p.base_unit_id')->leftJoin('stock_product_balances as b', 'b.product_id', '=', 'p.id')->where('s.qty', '<>', 0)
            ->when($term !== null, fn (Builder $where) => self::matching($where, (string) $term))
            ->when($condition !== null, fn (Builder $where) => $where->where('s.condition', $condition))
            ->when($key !== null, fn (Builder $where) => $where->whereRaw('(l.code, p.public_id::text, s.condition) > (?, ?, ?)', $key ?? []))
            ->orderBy('l.code')->orderByRaw('p.public_id::text')->orderBy('s.condition')->limit(self::PAGE + 1)
            ->get(['p.id', 'l.code as rack', 'p.public_id', 'p.name', 'p.sku', 's.condition', 's.qty', 'u.code as unit', 'b.on_hand_qty', 'b.reserved_qty', 'b.unusable_qty'])->all();
        $page = array_slice($found, 0, self::PAGE);
        $alternates = self::alternates(array_map(fn (object $row): int => (int) Row::text($row, 'id'), $page));
        $rows = array_map(function (object $row) use ($alternates): array {
            $alternate = $alternates[(int) Row::text($row, 'id')] ?? null;

            return ['id' => Row::text($row, 'public_id'), 'rack' => Row::text($row, 'rack'), 'name' => Row::text($row, 'name'), 'sku' => Row::nullable($row, 'sku'), 'condition' => Row::text($row, 'condition'), 'qty' => StockFigures::plain(Row::text($row, 'qty')), 'unit' => Row::text($row, 'unit'),
                'figures' => StockFigures::of(Row::nullable($row, 'on_hand_qty') ?? '0', Row::nullable($row, 'reserved_qty') ?? '0', Row::nullable($row, 'unusable_qty') ?? '0', Row::text($row, 'unit'), $alternate['unit'] ?? null, $alternate['ratio'] ?? null), 'href' => '/gudang/stok/'.Row::text($row, 'public_id')];
        }, $page);
        $last = $page === [] ? null : $page[count($page) - 1];

        return ['rows' => $rows, 'more' => count($found) > self::PAGE && $last !== null ? StockCursor::of($viewer, [Row::text($last, 'rack'), Row::text($last, 'public_id'), Row::text($last, 'condition')], $binding) : null, 'cursor_failed' => false];
    }

    /**
     * PG-019's head in one statement, the product's figures and pending line with its internal key for the page's other reads; an unknown id null (AZ-11; IP-21).
     *
     * @return array{internal: int, product: array<string, mixed>}|null
     */
    public function product(string $publicId): ?array
    {
        $product = preg_match(self::UUID, $publicId) === 1 ? DB::table('products as p')->leftJoin('stock_product_balances as b', 'b.product_id', '=', 'p.id')->join('units as u', 'u.id', '=', 'p.base_unit_id')->where('p.public_id', strtolower($publicId))->where('p.kind', 'GOODS')
            ->first(['p.id', 'p.public_id', 'p.name', 'p.sku', 'p.min_stock_qty_base', 'p.is_serialized', 'u.code as unit', 'b.on_hand_qty', 'b.reserved_qty', 'b.unusable_qty', self::pendingColumn(), DB::raw(self::ALTERNATE_UNIT), DB::raw(self::ALTERNATE_RATIO)]) : null;

        if ($product === null) {
            return null;
        }

        $unit = Row::nullable($product, 'alternate_unit');
        $ratio = Row::nullable($product, 'alternate_ratio');

        return ['internal' => (int) Row::text($product, 'id'), 'product' => [...self::productRow($product, $unit === null || $ratio === null ? null : ['unit' => $unit, 'ratio' => (int) $ratio]), 'serialized' => (bool) $product->is_serialized]];
    }

    /**
     * The product's racks and conditions.
     *
     * @return list<array{rack: string, condition: string, qty: string}>
     */
    public function placesOf(int $productId): array
    {
        return array_values(array_map(fn (object $row): array => ['rack' => Row::text($row, 'code'), 'condition' => Row::text($row, 'condition'), 'qty' => StockFigures::plain(Row::text($row, 'qty'))],
            DB::table('stock_scope_balances as s')->join('locations as l', 'l.id', '=', 's.location_id')->where('s.product_id', $productId)->where('s.qty', '<>', 0)->orderBy('l.code')->orderBy('s.condition')->limit(self::LOTS)->get(['l.code', 's.condition', 's.qty'])->all()));
    }

    /**
     * PG-019's deferred group (QB-05): lots with their oldest-first picking aid on the oldest lot holding usable units (BR-INV-04; PT-17) and serials with their presence, each list continued by its own cursor (PF-13; IP-18); at most three statements with the page's own head.
     *
     * @return array{lots: list<array<string, mixed>>, lots_more: ?string, serials: list<array<string, string>>, serials_more: ?string, cursor_failed: bool}
     */
    public function group(int $viewerId, int $productId, ?string $lotAfter = null, ?string $serialAfter = null): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $lotBinding = ['list' => 'lots', 'product' => $productId];
        $serialBinding = ['list' => 'serials', 'product' => $productId];
        $lotKey = $lotAfter === null ? null : StockCursor::read($viewer, $lotAfter, $lotBinding, 4);
        $serialKey = $serialAfter === null ? null : StockCursor::read($viewer, $serialAfter, $serialBinding, 1);

        if (($lotAfter !== null && $lotKey === null) || ($serialAfter !== null && $serialKey === null)) {
            return ['lots' => [], 'lots_more' => null, 'serials' => [], 'serials_more' => null, 'cursor_failed' => true];
        }

        $rank = "case when b.condition = 'USABLE' then 0 else 1 end";
        $found = DB::table('stock_lot_balances as b')->join('inventory_lots as i', 'i.id', '=', 'b.lot_id')->join('locations as l', 'l.id', '=', 'b.location_id')->join('companies as c', 'c.id', '=', 'i.source_company_id')->where('b.product_id', $productId)->where('b.qty', '>', 0)
            ->when($lotKey !== null, fn (Builder $where) => $where->whereRaw("(i.business_date, i.id, {$rank}, l.code) > (?::date, ?::bigint, ?::int, ?)", $lotKey ?? []))
            ->orderBy('i.business_date')->orderBy('i.id')->orderByRaw($rank)->orderBy('l.code')->limit(self::LOTS + 1)
            ->get(['i.id', 'i.lot_code', 'i.source_company_id', 'i.acquisition_kind', 'i.business_date', 'i.acquired_qty_base', 'b.qty', 'b.condition', 'l.code as rack', 'c.code as company_code', 'c.legal_name'])->all();
        $page = array_slice($found, 0, self::LOTS);
        $lots = [];
        $unflagged = $lotAfter === null;

        foreach ($page as $row) {
            $id = (int) Row::text($row, 'id');
            $condition = Row::text($row, 'condition');
            $oldest = $unflagged && $condition === 'USABLE';
            $unflagged = $unflagged && ! $oldest;
            $physical = ['id' => RowHandle::of('inventory_lots', $id), 'code' => Row::text($row, 'lot_code'), 'qty' => StockFigures::plain(Row::text($row, 'qty')), 'rack' => Row::text($row, 'rack'), 'condition' => $condition, 'oldest' => $oldest];
            $lots[] = $viewer->scope->includes((int) Row::text($row, 'source_company_id'))
                ? [...$physical, 'other' => false, 'company' => Row::text($row, 'company_code').' · '.Row::text($row, 'legal_name'), 'kind' => Row::text($row, 'acquisition_kind'), 'date' => Row::text($row, 'business_date'), 'acquired' => StockFigures::plain(Row::text($row, 'acquired_qty_base'))]
                : [...$physical, 'other' => true];
        }

        $lastLot = $page === [] ? null : $page[count($page) - 1];
        $foundSerials = DB::table('serial_units as s')->join('inventory_lots as i', 'i.id', '=', 's.current_lot_id')->join('locations as l', 'l.id', '=', 's.current_location_id')->where('s.product_id', $productId)->where('s.state', 'IN_STOCK')
            ->when($serialKey !== null, fn (Builder $where) => $where->whereRaw('s.serial_number > ?', $serialKey ?? []))->orderBy('s.serial_number')->limit(self::LOTS + 1)
            ->get(['s.serial_number', 'i.lot_code', 'l.code as rack', 's.current_condition'])->all();
        $serials = array_map(fn (object $row): array => ['serial' => Row::text($row, 'serial_number'), 'lot' => Row::text($row, 'lot_code'), 'rack' => Row::text($row, 'rack'), 'condition' => Row::text($row, 'current_condition')], array_slice($foundSerials, 0, self::LOTS));
        $lastSerial = $serials === [] ? null : $serials[count($serials) - 1];

        return [
            'lots' => $lots,
            'lots_more' => count($found) > self::LOTS && $lastLot !== null ? StockCursor::of($viewer, [Row::text($lastLot, 'business_date'), (int) Row::text($lastLot, 'id'), Row::text($lastLot, 'condition') === 'USABLE' ? 0 : 1, Row::text($lastLot, 'rack')], $lotBinding) : null,
            'serials' => $serials,
            'serials_more' => count($foundSerials) > self::LOTS && $lastSerial !== null ? StockCursor::of($viewer, [$lastSerial['serial']], $serialBinding) : null,
            'cursor_failed' => false,
        ];
    }

    /**
     * PG-019's reservations, continued by a cursor (PF-13; IP-18).
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string, cursor_failed: bool}
     */
    public function reservationPage(int $viewerId, int $productId, ?string $after): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $binding = ['list' => 'reservations', 'product' => $productId];
        $key = $after === null ? null : StockCursor::read($viewer, $after, $binding, 1);

        if ($after !== null && $key === null) {
            return ['rows' => [], 'more' => null, 'cursor_failed' => true];
        }

        $found = self::active($productId)->when($key !== null, fn (Builder $where) => $where->where('r.id', '>', $key[0] ?? 0))->orderBy('r.id')->limit(self::PAGE + 1)->get(self::RESERVATION)->all();
        $page = array_slice($found, 0, self::PAGE);
        $last = $page === [] ? null : $page[count($page) - 1];

        return ['rows' => array_map(fn (object $row): array => self::reservationRow($viewer, $row), $page), 'more' => count($found) > self::PAGE && $last !== null ? StockCursor::of($viewer, [(int) Row::text($last, 'id')], $binding) : null, 'cursor_failed' => false];
    }

    /**
     * The product's active reservations for a cut (IP-20), the largest first: a project named with `projects.view` where its company is in scope, otherwise the quantity and, for another company's, the relation marker only (row 61; PJ-04).
     *
     * @return list<array{id: string, reserved: string, other: bool, project: array{label: string, href: string}|null}>
     */
    public function reservations(Authority $viewer, int $productId): array
    {
        return array_values(array_map(fn (object $row): array => self::reservationRow($viewer, $row), self::active($productId)->orderByDesc('r.remaining_qty_base')->orderBy('r.id')->limit(self::CUT_CHOICES)->get(self::RESERVATION)->all()));
    }

    private static function active(int $productId): Builder
    {
        return DB::table('reservations as r')->join('project_lines as l', 'l.id', '=', 'r.project_line_id')->join('projects as p', 'p.id', '=', 'l.project_id')->where('r.product_id', $productId)->where('r.state', 'ACTIVE');
    }

    /** @return array{id: string, reserved: string, other: bool, project: array{label: string, href: string}|null} */
    private static function reservationRow(Authority $viewer, object $row): array
    {
        $company = (int) Row::text($row, 'company_id');
        $inScope = $viewer->scope->includes($company);

        return ['id' => RowHandle::of('reservations', (int) Row::text($row, 'id')), 'reserved' => StockFigures::plain(Row::text($row, 'remaining_qty_base')), 'other' => ! $inScope,
            'project' => $inScope && $viewer->allows('projects.view', $company) ? ['label' => Row::text($row, 'project_number'), 'href' => '/proyek/'.Row::text($row, 'public_id')] : null];
    }

    private static function term(?string $search): ?string
    {
        return $search === null ? null : '%'.addcslashes(mb_strtolower(trim($search)), '%_\\').'%';
    }

    private static function matching(Builder $where, string $term): Builder
    {
        return $where->where(fn (Builder $any) => $any->whereRaw('lower(p.name) like ?', [$term])->orWhereRaw('lower(p.sku) like ?', [$term]));
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
