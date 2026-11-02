<?php

namespace App\Modules\INV\Queries;

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Data\StockFigures;
use App\Modules\INV\Services\InventoryPosting;
use App\Modules\INV\Services\LossRestorations;
use App\Modules\INV\Services\UnattributedCases;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-026 *Stok Opname*'s reads (ROUTE-026; NAV-026; J-INV-05; CS-05; PJ-01; PJ-04): the counts with their state, a count's scope, lines and variance, and what its application needs — the lots of the scope physically, another company's by its marker alone, the pending cases and open finding of the scope, the serials missing and unexpected. Physical-only: no S3 table here.
 */
final class CountQueries
{
    public const PAGE = 50;

    public const LINES = 500;

    private const STATES = ['OPEN', 'APPLIED', 'STALE', 'CANCELLED'];

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
    ) {}

    /** @return array<string, bool> what the viewer may do here; each command absent without it (IG-01). */
    public function can(int $viewerId): array
    {
        $viewer = $this->capabilities->for($viewerId);

        return ['count' => $viewer->has('stock.count'), 'apply' => $viewer->has('stock.count_apply'), 'reserve' => $viewer->has('stock.reserve'), 'evidence' => $viewer->has('evidence.upload'), 'allocate' => $viewer->has('stock.allocate_intercompany'), 'register' => $viewer->has('products.edit')];
    }

    /**
     * The counts, newest first, by state; keyset by id.
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string, state: ?string, cursor_failed: bool}
     */
    public function list(int $viewerId, ?string $state, ?string $after): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $state = in_array($state, self::STATES, true) ? $state : null;
        $binding = ['list' => 'counts', 'state' => $state];
        $key = $after === null ? null : StockCursor::read($viewer, $after, $binding, 1);

        if ($after !== null && $key === null) {
            return ['rows' => [], 'more' => null, 'state' => $state, 'cursor_failed' => true];
        }

        $found = DB::table('stock_counts as c')->leftJoin('locations as l', 'l.id', '=', 'c.location_id')
            ->when($state !== null, fn (Builder $where) => $where->where('c.state', $state))
            ->when($key !== null, fn (Builder $where) => $where->where('c.id', '<', (int) ($key[0] ?? 0)))
            ->orderByDesc('c.id')->limit(self::PAGE + 1)
            ->get(['c.id', 'c.public_id', 'c.state', 'c.purpose', 'c.condition', 'c.started_at', 'c.product_ids', 'l.code as rack', DB::raw('(select count(*) from stock_count_lines x where x.stock_count_id = c.id) as lines')])->all();
        $page = array_slice($found, 0, self::PAGE);
        $names = self::productNames($page);
        $last = $page === [] ? null : $page[count($page) - 1];

        return [
            'rows' => array_map(fn (object $row): array => ['id' => Row::text($row, 'public_id'), 'state' => Row::text($row, 'state'), 'purpose' => Row::text($row, 'purpose'), 'product' => $names[self::productOf($row)] ?? '', 'rack' => Row::nullable($row, 'rack'), 'condition' => Row::nullable($row, 'condition'), 'started' => substr(Row::text($row, 'started_at'), 0, 10), 'lines' => (int) Row::text($row, 'lines')], $page),
            'more' => count($found) > self::PAGE && $last !== null ? StockCursor::of($viewer, [(int) Row::text($last, 'id')], $binding) : null,
            'state' => $state,
            'cursor_failed' => false,
        ];
    }

    /**
     * A count by its public id: its scopes with basis, counted and variance; its lines; what its application needs.
     *
     * @return array<string, mixed>|null
     */
    public function count(int $viewerId, string $publicId): ?array
    {
        $viewer = $this->capabilities->for($viewerId);
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('stock_counts')->where('public_id', strtolower($publicId))->first(['id', 'public_id', 'state', 'purpose', 'location_id', 'condition', 'started_at', 'product_ids']) : null;

        if ($row === null) {
            return null;
        }

        $id = (int) Row::text($row, 'id');
        $product = self::productOf($row);
        $info = DB::table('products as p')->join('units as u', 'u.id', '=', 'p.base_unit_id')->where('p.id', $product)->first(['p.public_id', 'p.name', 'p.sku', 'p.is_serialized', 'p.quantity_scale', 'u.code as unit']);
        $lines = DB::table('stock_count_lines as x')->join('locations as l', 'l.id', '=', 'x.location_id')->leftJoin('serial_units as s', 's.id', '=', 'x.serial_unit_id')->leftJoin('inventory_lots as i', 'i.id', '=', 'x.lot_id')->where('x.stock_count_id', $id)->orderByDesc('x.id')->limit(self::LINES)
            ->get(['x.id', 'x.counted_qty', 'x.condition', 'x.location_id', 'x.unregistered_serial_text', 's.serial_number', 's.state as serial_state', 's.current_location_id', 's.current_condition', 'l.code as rack', 'i.lot_code'])->all();
        $scopes = [];

        foreach (DB::table('stock_count_scopes as s')->join('locations as l', 'l.id', '=', 's.location_id')->where('s.stock_count_id', $id)->orderBy('l.code')->orderBy('s.condition')->get(['s.location_id', 's.condition', 's.system_qty_at_start', 'l.code as rack', DB::raw('(select coalesce(sum(x.counted_qty), 0) from stock_count_lines x where x.stock_count_id = s.stock_count_id and x.location_id = s.location_id and x.condition = s.condition) as counted')]) as $scope) {
            $counted = BigDecimal::of(Row::text($scope, 'counted'));
            $scopes[] = ['location' => RowHandle::of('locations', (int) Row::text($scope, 'location_id')), 'rack' => Row::text($scope, 'rack'), 'condition' => Row::text($scope, 'condition'), 'system' => StockFigures::plain(Row::text($scope, 'system_qty_at_start')), 'counted' => StockFigures::plain((string) $counted), 'variance' => StockFigures::plain((string) $counted->minus(Row::text($scope, 'system_qty_at_start')))];
        }

        $location = Row::nullable($row, 'location_id');
        $condition = Row::nullable($row, 'condition');
        $ordinary = $location !== null && $condition !== null;

        return [
            'id' => Row::text($row, 'public_id'), 'state' => Row::text($row, 'state'), 'purpose' => Row::text($row, 'purpose'), 'started' => substr(Row::text($row, 'started_at'), 0, 10),
            'product' => $info === null ? null : ['id' => Row::text($info, 'public_id'), 'name' => Row::text($info, 'name'), 'sku' => Row::nullable($info, 'sku'), 'unit' => Row::text($info, 'unit'), 'serialized' => (bool) Row::text($info, 'is_serialized'), 'scale' => (int) Row::text($info, 'quantity_scale')],
            'scopes' => $scopes,
            'lines' => array_values(array_map(fn (object $line): array => self::line($line), $lines)),
            'places' => $ordinary ? $this->places($viewerId, $product, (int) $location, $condition) : $this->allPlaces($viewerId, $product),
            'across' => $ordinary && UnattributedCases::acrossCompanies($product, (int) $location, $condition),
            'pending' => $ordinary ? StockFigures::plain((string) DB::table('unattributed_loss_cases')->where('product_id', $product)->where('location_id', (int) $location)->where('condition', $condition)->where('case_kind', 'LOSS')->sum('pending_qty_base')) : '0',
            'changes' => $ordinary && $condition === 'UNUSABLE' ? $this->pendingChanges($product, (int) $location) : [],
            'finding' => $ordinary ? StockFigures::plain((string) DB::table('stock_count_findings')->where('product_id', $product)->where('location_id', (int) $location)->where('condition', $condition)->where('state', 'OPEN')->sum('surplus_qty_base')) : '0',
            'losses' => $ordinary && $viewer->has('stock.count_apply') ? $this->identifiable($viewerId, $product, (int) $location, $condition) : [],
            'losses_across' => $ordinary && LossRestorations::acrossCompanies($product, (int) $location, $condition),
            'missing' => $ordinary && $info !== null && (bool) Row::text($info, 'is_serialized') ? $this->missing($id, $product, (int) $location, $condition) : [],
            // Every unseen serial is lost and may need a cut, however many the bounded list shows (SF-RSV-CUT).
            'missing_total' => $ordinary && $info !== null && (bool) Row::text($info, 'is_serialized') ? self::unseen($id, $product, (int) $location, $condition)->count() : 0,
        ];
    }

    /**
     * The next counted scope a page opens for: the racks holding the product, or all active ones.
     *
     * @return list<array{id: string, code: string, name: string}>
     */
    public function racks(): array
    {
        return array_values(array_map(fn (object $row): array => ['id' => RowHandle::of('locations', (int) Row::text($row, 'id')), 'code' => Row::text($row, 'code'), 'name' => Row::text($row, 'name')], DB::table('locations')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])->all()));
    }

    public function publicId(int $count): ?string
    {
        $id = DB::table('stock_counts')->where('id', $count)->value('public_id');

        return is_string($id) ? $id : null;
    }

    /** AVAILABLE, for the cut the application may need (IP-20). */
    public function available(int $product): string
    {
        return StockFigures::plain((string) InventoryPosting::available($product));
    }

    /** A loss of the product the viewer may reverse, among every open one however old: the bearer in scope (AZ-08). */
    public function consumption(int $viewerId, int $product, string $handle): ?int
    {
        foreach ($this->capabilities->for($viewerId)->scope->apply(DB::table('stock_consumptions as c'), 'c.bearer_company_id')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->where('c.product_id', $product)->whereIn('c.kind', ['LOSS', 'UNATTRIBUTED_LOSS'])
            ->whereRaw('b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base > 0')->orderByDesc('c.id')->pluck('c.id') as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('stock_consumptions', (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    /** @return array{id: int, public: string, product: int, location: ?int, condition: ?string, state: string}|null */
    public function internal(string $publicId): ?array
    {
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('stock_counts')->where('public_id', strtolower($publicId))->first(['id', 'public_id', 'state', 'location_id', 'condition', 'product_ids']) : null;

        if ($row === null) {
            return null;
        }

        $location = Row::nullable($row, 'location_id');

        return ['id' => (int) Row::text($row, 'id'), 'public' => Row::text($row, 'public_id'), 'product' => self::productOf($row), 'location' => $location === null ? null : (int) $location, 'condition' => Row::nullable($row, 'condition'), 'state' => Row::text($row, 'state')];
    }

    /**
     * The lots in the scope, oldest first as a picking aid only (L-42), another company's by its marker alone (PJ-04).
     *
     * @return list<array<string, mixed>>
     */
    private function places(int $viewerId, int $product, int $location, string $condition): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $places = [];
        $first = true;

        foreach (DB::table('stock_lot_balances as b')->join('inventory_lots as i', 'i.id', '=', 'b.lot_id')->join('locations as l', 'l.id', '=', 'b.location_id')->where('b.product_id', $product)->where('b.location_id', $location)->where('b.condition', $condition)->where('b.qty', '>', 0)->orderBy('i.business_date')->orderBy('i.id')->limit(self::LINES)->get(['b.lot_id', 'b.qty', 'i.lot_code', 'i.source_company_id', 'l.code as rack']) as $row) {
            $places[] = ['lot' => RowHandle::of('inventory_lots', (int) Row::text($row, 'lot_id')), 'code' => Row::text($row, 'lot_code'), 'location' => RowHandle::of('locations', $location), 'rack' => Row::text($row, 'rack'), 'condition' => $condition, 'qty' => StockFigures::plain(Row::text($row, 'qty')), 'other' => ! $viewer->scope->includes((int) Row::text($row, 'source_company_id')), 'oldest' => $first];
            $first = false;
        }

        return $places;
    }

    /**
     * A registration count's places: every lot of the product, in each place.
     *
     * @return list<array<string, mixed>>
     */
    private function allPlaces(int $viewerId, int $product): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $places = [];

        foreach (DB::table('stock_lot_balances as b')->join('inventory_lots as i', 'i.id', '=', 'b.lot_id')->join('locations as l', 'l.id', '=', 'b.location_id')->where('b.product_id', $product)->where('b.qty', '>', 0)->orderBy('i.id')->orderBy('l.code')->limit(self::LINES)->get(['b.lot_id', 'b.location_id', 'b.condition', 'b.qty', 'i.lot_code', 'i.source_company_id', 'l.code as rack']) as $row) {
            $places[] = ['lot' => RowHandle::of('inventory_lots', (int) Row::text($row, 'lot_id')), 'code' => Row::text($row, 'lot_code'), 'location' => RowHandle::of('locations', (int) Row::text($row, 'location_id')), 'rack' => Row::text($row, 'rack'), 'condition' => Row::text($row, 'condition'), 'qty' => StockFigures::plain(Row::text($row, 'qty')), 'other' => ! $viewer->scope->includes((int) Row::text($row, 'source_company_id')), 'oldest' => false];
        }

        return $places;
    }

    /**
     * The pending condition-changed units of this rack a shortage may dispose of (§5.8); no company named.
     *
     * @return list<array{id: string, qty: string}>
     */
    private function pendingChanges(int $product, int $location): array
    {
        return array_values(array_map(fn (object $row): array => ['id' => RowHandle::of('unattributed_loss_cases', (int) Row::text($row, 'id')), 'qty' => StockFigures::plain(Row::text($row, 'pending_qty_base'))],
            DB::table('unattributed_loss_cases')->where('product_id', $product)->where('location_id', $location)->where('case_kind', 'CONDITION_CHANGE')->where('pending_qty_base', '>', 0)->whereNotExists(fn (Builder $next) => $next->from('unattributed_loss_cases as n')->whereColumn('n.converted_from_case_id', 'unattributed_loss_cases.id'))->orderBy('id')->get(['id', 'pending_qty_base'])->all()));
    }

    /**
     * The fungible losses in this scope a surplus may reverse once identified, the bearer's own to the viewer (AZ-08; PJ-05); a serial comes back by being seen.
     *
     * @return list<array{id: string, date: string, code: string, open: string}>
     */
    private function identifiable(int $viewerId, int $product, int $location, string $condition): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $found = $viewer->scope->apply(DB::table('stock_consumptions as c'), 'c.bearer_company_id')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->join('stock_movement_entries as e', 'e.stock_consumption_id', '=', 'c.id')->join('inventory_lots as i', 'i.id', '=', 'c.lot_id')->join('stock_movements as m', 'm.id', '=', 'c.stock_movement_id')
            ->where('c.product_id', $product)->where('e.location_id', $location)->where('e.condition', $condition)->whereNull('e.serial_unit_id')->whereIn('c.kind', ['LOSS', 'UNATTRIBUTED_LOSS'])->whereRaw('b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base > 0')->orderByDesc('c.id')->limit(50)
            ->get(['c.id', 'm.business_date', 'i.lot_code', DB::raw('b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base as open')])->all();

        return array_values(array_map(fn (object $row): array => ['id' => RowHandle::of('stock_consumptions', (int) Row::text($row, 'id')), 'date' => Row::text($row, 'business_date'), 'code' => Row::text($row, 'lot_code'), 'open' => StockFigures::plain(Row::text($row, 'open'))], $found));
    }

    /**
     * The serials in stock here the count has not seen yet.
     *
     * @return list<array{serial: string, code: string}>
     */
    private function missing(int $count, int $product, int $location, string $condition): array
    {
        return array_values(array_map(fn (object $row): array => ['serial' => Row::text($row, 'serial_number'), 'code' => Row::text($row, 'lot_code')],
            self::unseen($count, $product, $location, $condition)->join('inventory_lots as i', 'i.id', '=', 's.current_lot_id')->orderBy('s.serial_number')->limit(self::LINES)->get(['s.serial_number', 'i.lot_code'])->all()));
    }

    /** The serials in stock here the count has not seen. */
    private static function unseen(int $count, int $product, int $location, string $condition): Builder
    {
        return DB::table('serial_units as s')->where('s.product_id', $product)->where('s.state', 'IN_STOCK')->where('s.current_location_id', $location)->where('s.current_condition', $condition)
            ->whereNotExists(fn (Builder $seen) => $seen->from('stock_count_lines as x')->where('x.stock_count_id', $count)->whereColumn('x.serial_unit_id', 's.id'));
    }

    /** @return array<string, mixed> */
    private static function line(object $line): array
    {
        $serial = Row::nullable($line, 'serial_number') ?? Row::nullable($line, 'unregistered_serial_text');
        $state = Row::nullable($line, 'serial_state');
        $here = $state === 'IN_STOCK' && Row::nullable($line, 'current_location_id') === Row::text($line, 'location_id') && Row::nullable($line, 'current_condition') === Row::text($line, 'condition');

        return ['id' => RowHandle::of('stock_count_lines', (int) Row::text($line, 'id')), 'rack' => Row::text($line, 'rack'), 'condition' => Row::text($line, 'condition'), 'qty' => StockFigures::plain(Row::text($line, 'counted_qty')), 'serial' => $serial, 'code' => Row::nullable($line, 'lot_code'), 'unexpected' => $serial !== null && Row::nullable($line, 'lot_code') === null && ! $here];
    }

    /** @param  list<object>  $rows
     * @return array<int, string>
     */
    private static function productNames(array $rows): array
    {
        $ids = array_values(array_unique(array_map(fn (object $row): int => self::productOf($row), $rows)));
        $names = [];

        foreach ($ids === [] ? [] : DB::table('products')->whereIn('id', $ids)->get(['id', 'name']) as $row) {
            $names[(int) Row::text($row, 'id')] = Row::text($row, 'name');
        }

        return $names;
    }

    private static function productOf(object $row): int
    {
        return (int) trim(Row::text($row, 'product_ids'), '{}');
    }
}
