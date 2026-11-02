<?php

namespace App\Modules\INV\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Data\StockFigures;
use App\Modules\INV\Services\InventoryPosting;
use App\Modules\INV\Services\MovementReversal;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-020 *Riwayat Stok*'s read (ROUTE-020; PJ-03; rows 50, 51; CS-07; PF-13; PF-41): the ledger itemized for movements of companies in scope and for pool movements without a company, one bounded branch for each with the scope inside the query, keyed by date, movement and entry (IX-11); a movement of a company outside scope is never itemized. A movement's project with `projects.view`, its purchase with `cost.view`, the supplier's delivery reference with `projects.view` or `stock.receive`; never a cost.
 */
final class MovementHistory
{
    /** A pool movement's loss bearers, which its reason follows. */
    private const ASSIGNED = "(select string_agg(distinct x.assigned_company_id::text, ',') from unattributed_loss_resolutions r join unattributed_loss_resolution_lines x on x.unattributed_loss_resolution_id = r.id where r.stock_movement_id in (m.id, m.reverses_movement_id)) as assigned";

    private const BEARERS = "(select string_agg(distinct c.bearer_company_id::text, ',') from stock_consumptions c where c.kind in ('LOSS', 'UNATTRIBUTED_LOSS') and m.company_id is null and (c.stock_movement_id = m.id or c.id in (select r.stock_consumption_id from stock_restorations r where r.stock_movement_id = m.id))) as bearers";

    public const PAGE = 50;

    /** The movement types a filter offers. */
    public const TYPES = ['SERIAL_REGISTRATION', 'RECEIPT', 'RECEIPT_REVERSAL', 'CONDITION_CHANGE', 'LOCATION_MOVE', 'ADJUSTMENT_DECREASE', 'LOSS', 'COUNT_VARIANCE', 'SURPLUS_ATTRIBUTION', 'UNATTRIBUTED_RECOGNITION', 'UNATTRIBUTED_CONVERSION', 'UNATTRIBUTED_FOUND', 'DISPATCH', 'RESTORATION', 'PURCHASE_RETURN', 'OPENING', 'MOVEMENT_REVERSAL'];

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly StockViews $views,
    ) {}

    /**
     * The address's filters, a value outside the whitelist ignored (INT-007).
     *
     * @param  array<string, ?string>  $query
     * @return array{produk: ?string, jenis: ?string, dari: ?string, sampai: ?string, urut: ?string, gerakan: ?string}
     */
    public static function filters(array $query): array
    {
        $id = fn (?string $value): ?string => is_string($value) && preg_match(self::UUID, $value) === 1 ? strtolower($value) : null;
        $type = $query['jenis'] ?? null;

        return ['produk' => $id($query['produk'] ?? null), 'jenis' => in_array($type, self::TYPES, true) ? $type : null, 'dari' => self::date($query['dari'] ?? null), 'sampai' => self::date($query['sampai'] ?? null), 'urut' => ($query['urut'] ?? null) === 'terlama' ? 'terlama' : null, 'gerakan' => $id($query['gerakan'] ?? null)];
    }

    /**
     * @param  array{produk: ?string, jenis: ?string, dari: ?string, sampai: ?string, urut: ?string, gerakan: ?string}  $filters
     * @return array{rows: list<array<string, mixed>>, more: ?string, cursor_failed: bool}
     */
    public function page(int $viewerId, array $filters, ?string $after = null, int $limit = self::PAGE): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $ascending = $filters['urut'] === 'terlama';
        $binding = ['list' => 'movements', 'filters' => $filters];
        $key = $after === null ? null : StockCursor::read($viewer, $after, $binding, 3);

        if ($after !== null && $key === null) {
            return ['rows' => [], 'more' => null, 'cursor_failed' => true];
        }

        $product = self::productId($filters['produk']);

        if ($product === false) {
            return ['rows' => [], 'more' => null, 'cursor_failed' => false];
        }

        $found = $this->rowsOf($viewer, $product, $filters, $key, $ascending, $limit);
        $page = array_slice($found, 0, $limit);
        $last = $page === [] ? null : $page[count($page) - 1];

        return ['rows' => self::rows($viewer, $page), 'more' => count($found) > $limit && $last !== null ? StockCursor::of($viewer, [Row::text($last, 'business_date'), (int) Row::text($last, 'movement_id'), (int) Row::text($last, 'entry_id')], $binding) : null, 'cursor_failed' => false];
    }

    /**
     * PG-019's recent movements in one statement (QB-05).
     *
     * @return list<array<string, mixed>>
     */
    public function recent(int $viewerId, int $productId, int $limit = 10): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $none = ['produk' => null, 'jenis' => null, 'dari' => null, 'sampai' => null, 'urut' => null, 'gerakan' => null];

        return array_map(function (object $row): array {
            $qty = Row::text($row, 'qty_delta_base');

            return ['id' => RowHandle::of('stock_movement_entries', (int) Row::text($row, 'entry_id')), 'direction' => str_starts_with($qty, '-') ? 'OUT' : 'IN', 'type' => Row::text($row, 'movement_type'), 'date' => Row::text($row, 'business_date'), 'qty' => StockFigures::plain(ltrim($qty, '-')), 'unit' => Row::text($row, 'unit'), 'rack' => Row::text($row, 'rack'), 'condition' => Row::text($row, 'condition'), 'lot' => Row::nullable($row, 'lot_code')];
        }, array_slice($this->rowsOf($viewer, $productId, $none, null, false, $limit), 0, $limit));
    }

    /**
     * PF-41's statement: one bounded branch per company in scope and one for the movements of no company, merged by key, the row's details joined once on what they found.
     *
     * @param  array{produk: ?string, jenis: ?string, dari: ?string, sampai: ?string, urut: ?string, gerakan: ?string}  $filters
     * @param  list<int|string>|null  $key
     * @return list<object>
     */
    private function rowsOf(Authority $viewer, ?int $product, array $filters, ?array $key, bool $ascending, int $limit): array
    {
        $direction = $ascending ? 'asc' : 'desc';
        $companies = $viewer->scope->all ? array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, DB::table('companies')->pluck('id')->all()) : $viewer->scope->companyIds;
        $union = self::branch(null, $product, $filters, $key, $ascending, $limit);

        foreach ($companies as $company) {
            $union->unionAll(self::branch($company, $product, $filters, $key, $ascending, $limit));
        }

        $union->orderBy('business_date', $direction)->orderBy('movement_id', $direction)->orderBy('entry_id', $direction)->limit($limit + 1);

        return array_values(DB::query()->fromSub($union, 't')->join('stock_movement_entries as e', 'e.id', '=', 't.entry_id')->join('stock_movements as m', 'm.id', '=', 't.movement_id')->join('products as p', 'p.id', '=', 'e.product_id')->join('units as u', 'u.id', '=', 'p.base_unit_id')
            ->join('locations as l', 'l.id', '=', 'e.location_id')->leftJoin('inventory_lots as i', 'i.id', '=', 'e.lot_id')->leftJoin('serial_units as s', 's.id', '=', 'e.serial_unit_id')->leftJoin('users as a', 'a.id', '=', 'm.recorded_by')
            ->leftJoin('companies as c', 'c.id', '=', 'm.company_id')->leftJoin('projects as pr', 'pr.id', '=', 'm.project_id')->leftJoin('purchases as x', 'x.id', '=', 'm.purchase_id')->leftJoin('stock_movements as o', 'o.id', '=', 'm.reverses_movement_id')
            ->orderBy('t.business_date', $direction)->orderBy('t.movement_id', $direction)->orderBy('t.entry_id', $direction)
            ->select(['t.entry_id', 't.movement_id', 't.business_date', 'm.public_id', 'e.movement_type', 'm.reason', DB::raw(self::BEARERS), DB::raw(self::ASSIGNED), 'm.company_id', 'm.supplier_delivery_reference', 'e.qty_delta_base', 'e.condition', 'e.lot_id', 'i.lot_code', 'i.receiving_line_id', 's.serial_number', 'l.code as rack', 'p.public_id as product', 'p.name as product_name', 'u.code as unit', 'a.name as actor', 'c.code as company_code', 'pr.public_id as project', 'pr.project_number', 'x.public_id as purchase', 'x.purchase_number', 'o.public_id as corrects', 'o.movement_type as corrects_type', 'o.business_date as corrects_date'])
            ->get()->all());
    }

    /**
     * @param  array{produk: ?string, jenis: ?string, dari: ?string, sampai: ?string, urut: ?string, gerakan: ?string}  $filters
     * @param  list<int|string>|null  $key
     */
    private static function branch(?int $company, ?int $product, array $filters, ?array $key, bool $ascending, int $limit): Builder
    {
        $direction = $ascending ? 'asc' : 'desc';
        $query = DB::table('stock_movement_entries as e')->join('stock_movements as m', 'm.id', '=', 'e.stock_movement_id')
            ->when($company === null, fn (Builder $where) => $where->whereNull('m.company_id'))
            ->when($company !== null, fn (Builder $where) => $where->where('m.company_id', $company))
            ->when($product !== null, fn (Builder $where) => $where->where('e.product_id', $product))
            ->when($filters['jenis'] !== null, fn (Builder $where) => $where->where('e.movement_type', $filters['jenis']))
            ->when($filters['gerakan'] !== null, fn (Builder $where) => $where->where('m.public_id', $filters['gerakan']))
            ->when($filters['dari'] !== null, fn (Builder $where) => $where->where('m.business_date', '>=', $filters['dari']))
            ->when($filters['sampai'] !== null, fn (Builder $where) => $where->where('m.business_date', '<=', $filters['sampai']))
            ->when($key !== null, fn (Builder $where) => $where->whereRaw('(m.business_date, m.id, e.id) '.($ascending ? '>' : '<').' (?::date, ?::bigint, ?::bigint)', $key ?? []));

        return $query->orderBy('m.business_date', $direction)->orderBy('m.id', $direction)->orderBy('e.id', $direction)->limit($limit + 1)->select(['m.business_date', 'm.id as movement_id', 'e.id as entry_id']);
    }

    /**
     * @param  list<object>  $page
     * @return list<array<string, mixed>>
     */
    private static function rows(Authority $viewer, array $page): array
    {
        $ids = array_values(array_unique(array_map(fn (object $row): int => (int) Row::text($row, 'movement_id'), $page)));
        [$corrected, $restoring] = self::links($ids);
        $open = self::stillReversible($ids);
        $seen = [];
        $rows = [];

        foreach ($page as $row) {
            $movement = (int) Row::text($row, 'movement_id');
            $company = Row::nullable($row, 'company_id');
            $companyId = $company === null ? null : (int) $company;
            $type = Row::text($row, 'movement_type');
            $qty = Row::text($row, 'qty_delta_base');
            $firstOfMovement = ! isset($seen[$movement]);
            $seen[$movement] = true;
            $mayReverse = $viewer->allows('stock.reverse', $companyId);
            $corrects = Row::nullable($row, 'corrects');
            $rows[] = [
                'id' => RowHandle::of('stock_movement_entries', (int) Row::text($row, 'entry_id')),
                'movement' => Row::text($row, 'public_id'), 'direction' => str_starts_with($qty, '-') ? 'OUT' : 'IN', 'type' => $type, 'date' => Row::text($row, 'business_date'), 'product' => Row::text($row, 'product_name'), 'product_href' => '/gudang/stok/'.Row::text($row, 'product'),
                'lot' => Row::nullable($row, 'lot_code'), 'rack' => Row::text($row, 'rack'), 'condition' => Row::text($row, 'condition'), 'qty' => StockFigures::plain(ltrim($qty, '-')), 'unit' => Row::text($row, 'unit'), 'serial' => Row::nullable($row, 'serial_number'), 'reason' => self::mayReadDecision($viewer, $row) ? self::reasonFor($viewer, $row) : null, 'actor' => self::mayReadDecision($viewer, $row) ? Row::nullable($row, 'actor') : null, 'company' => Row::nullable($row, 'company_code'),
                'project' => $companyId !== null && Row::nullable($row, 'project') !== null && $viewer->allows('projects.view', $companyId) ? ['label' => Row::text($row, 'project_number'), 'href' => '/proyek/'.Row::text($row, 'project')] : null,
                'purchase' => $companyId !== null && Row::nullable($row, 'purchase') !== null && $viewer->allows('cost.view', $companyId) ? ['label' => Row::text($row, 'purchase_number'), 'href' => '/pembelian/'.Row::text($row, 'purchase')] : null,
                'reference' => $companyId !== null && ($viewer->allows('projects.view', $companyId) || $viewer->allows('stock.receive', $companyId)) ? Row::nullable($row, 'supplier_delivery_reference') : null,
                'corrected_by' => $corrected[$movement] ?? null, 'corrects' => $corrects === null ? ($restoring[$movement] ?? null) : self::link($corrects, Row::text($row, 'corrects_type'), Row::text($row, 'corrects_date')),
                'reversible' => $firstOfMovement && $mayReverse && MovementReversal::reversible($type) && isset($open[$movement]) && ($type !== 'SURPLUS_ATTRIBUTION' || $viewer->ownerRole),
                'receipt' => $firstOfMovement && $type === 'RECEIPT' && $mayReverse && Row::nullable($row, 'receiving_line_id') !== null ? RowHandle::of('receiving_lines', (int) Row::text($row, 'receiving_line_id')) : null,
            ];
        }

        return $rows;
    }

    /** @return array{movement: string, type: string, date: string, href: string} A linked movement: "Dikoreksi oleh" and "Mengoreksi" open it on this page (BR-CR-02; ADMIN_FLOW §11). */
    private static function link(string $publicId, string $type, string $date): array
    {
        return ['movement' => $publicId, 'type' => $type, 'date' => $date, 'href' => '/gudang/riwayat-stok?gerakan='.$publicId];
    }

    /**
     * The page's movements' links in one statement: what corrects each — a MOVEMENT_REVERSAL or RECEIPT_REVERSAL naming it, or the restorations of its consumptions ("Dikoreksi oleh") — and what a restoration movement corrects, through its consumptions ("Mengoreksi").
     *
     * @param  list<int>  $ids
     * @return array{array<int, array{movement: string, type: string, date: string, href: string}>, array<int, array{movement: string, type: string, date: string, href: string}>}
     */
    private static function links(array $ids): array
    {
        $corrected = [];
        $restoring = [];

        if ($ids === []) {
            return [$corrected, $restoring];
        }

        $found = DB::table('stock_movements')->whereIn('reverses_movement_id', $ids)->selectRaw("'by' as side, reverses_movement_id as owner, public_id, movement_type, business_date")
            ->unionAll(DB::table('stock_consumptions as c')->join('stock_restorations as r', 'r.stock_consumption_id', '=', 'c.id')->join('stock_movements as m', 'm.id', '=', 'r.stock_movement_id')->whereIn('c.stock_movement_id', $ids)->where('r.kind', 'LOSS_REVERSAL')->selectRaw("'by' as side, c.stock_movement_id as owner, m.public_id, m.movement_type, m.business_date"))
            ->unionAll(DB::table('stock_restorations as r')->join('stock_consumptions as c', 'c.id', '=', 'r.stock_consumption_id')->join('stock_movements as m', 'm.id', '=', 'c.stock_movement_id')->whereIn('r.stock_movement_id', $ids)->where('r.kind', 'LOSS_REVERSAL')->selectRaw("'of' as side, r.stock_movement_id as owner, m.public_id, m.movement_type, m.business_date"))
            ->get();

        foreach ($found as $row) {
            $link = self::link(Row::text($row, 'public_id'), Row::text($row, 'movement_type'), Row::text($row, 'business_date'));

            if (Row::text($row, 'side') === 'by') {
                $corrected[(int) Row::text($row, 'owner')] = $link;
            } else {
                $restoring[(int) Row::text($row, 'owner')] = $link;
            }
        }

        return [$corrected, $restoring];
    }

    /**
     * The page's movements with something still to reverse, as ROUTE-212 would cap it (C-55; DATABASE §5.3; §5.8): a lot key above its reversal guard; lot-less entries each above what they have given back with their case's counter open; a consumption not yet wholly restored. A later correction can reopen a case, so a reversal alone never ends it.
     *
     * @param  list<int>  $ids
     * @return array<int, true>
     */
    private static function stillReversible(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $in = implode(', ', array_fill(0, count($ids), '?'));
        $found = [];

        foreach (DB::select(<<<SQL
            select e.stock_movement_id as id from stock_movement_entries e
            where e.stock_movement_id in ({$in}) and e.lot_id is not null
            group by e.stock_movement_id, e.lot_id, e.location_id, e.condition
            having abs(sum(e.qty_delta_base)) > coalesce((select g.reversed_qty_base from stock_reversal_balances g where g.reversed_movement_id = e.stock_movement_id and g.lot_id = e.lot_id and g.location_id = e.location_id and g.condition = e.condition), 0)
            union
            select e.stock_movement_id from stock_movement_entries e join unattributed_loss_cases k on k.id = e.unattributed_case_id
            where e.stock_movement_id in ({$in})
            group by e.stock_movement_id
            having bool_and(
                abs(e.qty_delta_base) > coalesce((select abs(sum(r.qty_delta_base)) from stock_movement_entries r join stock_movements rm on rm.id = r.stock_movement_id where rm.reverses_movement_id = e.stock_movement_id and r.unattributed_case_id = e.unattributed_case_id and r.location_id = e.location_id and r.condition = e.condition), 0)
                and case when e.movement_type = 'UNATTRIBUTED_FOUND' then k.found_qty_base > 0 when e.movement_type = 'UNATTRIBUTED_CONVERSION' then k.converted_from_case_id is null or k.pending_qty_base > 0 else k.pending_qty_base > 0 end
            )
            union
            select e.stock_movement_id from stock_movement_entries e join stock_consumption_balances b on b.stock_consumption_id = e.stock_consumption_id
            where e.stock_movement_id in ({$in}) and b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base > 0
            SQL, [...$ids, ...$ids, ...$ids]) as $row) {
            if (is_object($row)) {
                $found[(int) Row::text($row, 'id')] = true;
            }
        }

        return $found;
    }

    /**
     * A movement the viewer may reverse, by public id, itemized within scope; otherwise unknown (AZ-11; SM-23).
     *
     * @return array{id: int, company: ?int, type: string}|null
     */
    public function movement(int $viewerId, string $publicId): ?array
    {
        $row = preg_match(self::UUID, $publicId) === 1 ? DB::table('stock_movements')->where('public_id', strtolower($publicId))->first(['id', 'company_id', 'movement_type']) : null;

        if ($row === null) {
            return null;
        }

        $company = Row::nullable($row, 'company_id');
        $viewer = $this->capabilities->for($viewerId);

        if ($company !== null && ! $viewer->scope->includes((int) $company)) {
            return null;
        }

        return ['id' => (int) Row::text($row, 'id'), 'company' => $company === null ? null : (int) $company, 'type' => Row::text($row, 'movement_type')];
    }

    /**
     * The reversal's reservation-cut block (IP-20), naming its movement: what it takes from usable supply, what is available, the product's reservations by `stock.reserve`.
     *
     * @return array<string, mixed>|null
     */
    public function cutOf(int $viewerId, string $publicId): ?array
    {
        $movement = $this->movement($viewerId, $publicId);

        if ($movement === null) {
            return null;
        }

        $viewer = $this->capabilities->for($viewerId);
        $entries = DB::table('stock_movement_entries')->where('stock_movement_id', $movement['id'])->get(['product_id', 'condition', 'qty_delta_base'])->all();
        $product = $entries === [] ? 0 : (int) Row::text($entries[0], 'product_id');
        $taken = BigDecimal::zero();

        foreach ($entries as $entry) {
            // Its inverse lowers usable supply where the original raised it.
            $taken = Row::text($entry, 'condition') === 'USABLE' && in_array($movement['type'], MovementReversal::BY_INVERSE, true) ? $taken->plus(Row::text($entry, 'qty_delta_base')) : $taken;
        }

        $mayCut = $viewer->allows('stock.reserve', $movement['company']);
        $short = $taken->isPositive() ? InventoryPosting::shortfall($product, (string) $taken) : BigDecimal::zero();

        return ['movement' => strtolower($publicId), 'shortfall' => $short->isPositive() ? StockFigures::plain((string) $short) : null, 'may_cut' => $mayCut, 'reservations' => $mayCut && $short->isPositive() ? $this->views->reservations($viewer, $product) : []];
    }

    /** A reservation of the movement's product by its handle, for a cut (IP-20), in whatever state: `cut` re-reads it under its lock (C-05). */
    public function reservation(int $movementId, string $handle): ?int
    {
        $product = DB::table('stock_movement_entries')->where('stock_movement_id', $movementId)->value('product_id');

        foreach (is_numeric($product) ? DB::table('reservations')->where('product_id', (int) $product)->pluck('id') : [] as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('reservations', (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    /** Whether the restock advice links to PG-017 (J-MD-05). */
    /**
     * The likely duplicates' labels (IP-05).
     *
     * @param  list<string>  $publicIds
     * @return list<array{id: string, label: string, href: null}>
     */
    public function labels(array $publicIds): array
    {
        return array_values(array_map(function (object $row): array {
            $word = __('stock.movement.'.Row::text($row, 'movement_type'));

            return ['id' => Row::text($row, 'public_id'), 'label' => CarbonImmutable::parse(Row::text($row, 'business_date'))->format('d/m/Y').' · '.(is_string($word) ? $word : ''), 'href' => null];
        }, DB::table('stock_movements')->whereIn('public_id', $publicIds)->orderBy('id')->get(['public_id', 'business_date', 'movement_type'])->all()));
    }

    /** A resolution's decider and reason: to the Owner and a `cost.view` holder of an assigned company (PJ-07). */
    private static function mayReadDecision(Authority $viewer, object $row): bool
    {
        $assigned = Row::nullable($row, 'assigned');

        return $assigned === null || $viewer->ownerRole || array_filter(explode(',', $assigned), fn (string $company): bool => $viewer->allows('cost.view', (int) $company)) !== [];
    }

    /** A loss on a movement of the pool, and its reversal, say their reason to a holder of its bearer's side only (PJ-05; PJ-06). */
    private static function reasonFor(Authority $viewer, object $row): ?string
    {
        $bearers = Row::nullable($row, 'bearers');

        if ($bearers === null) {
            return Row::nullable($row, 'reason');
        }

        return array_filter(explode(',', $bearers), fn (string $company): bool => $viewer->scope->includes((int) $company)) === [] ? null : Row::nullable($row, 'reason');
    }

    public function mayPurchase(int $viewerId): bool
    {
        return $this->capabilities->for($viewerId)->has('purchase.manage');
    }

    /** @return int|false|null a product's id, false when none has that id, null when none is asked */
    private static function productId(?string $publicId): int|false|null
    {
        if ($publicId === null) {
            return null;
        }

        $id = DB::table('products')->where('public_id', $publicId)->value('id');

        return is_numeric($id) ? (int) $id : false;
    }

    private static function date(?string $value): ?string
    {
        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1) {
            return null;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : null;
    }
}
