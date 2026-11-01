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
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * PG-020 *Riwayat Stok*'s read (ROUTE-020; PJ-03; rows 50, 51; CS-07; PF-13; PF-41): the ledger itemized for movements of companies in scope and for pool movements without a company, one branch for each with the scope inside the query; a movement of a company outside scope is never itemized. A movement's project with `projects.view`, its purchase with `cost.view`, the supplier's delivery reference with `projects.view` or `stock.receive`; never a cost.
 */
final class MovementHistory
{
    public const PAGE = 50;

    /** The movement types a filter offers. */
    public const TYPES = ['RECEIPT', 'RECEIPT_REVERSAL', 'CONDITION_CHANGE', 'LOCATION_MOVE', 'ADJUSTMENT_DECREASE', 'LOSS', 'COUNT_VARIANCE', 'SURPLUS_ATTRIBUTION', 'UNATTRIBUTED_RECOGNITION', 'UNATTRIBUTED_FOUND', 'DISPATCH', 'RESTORATION', 'PURCHASE_RETURN', 'OPENING', 'MOVEMENT_REVERSAL'];

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly StockViews $views,
    ) {}

    /**
     * @param  array{produk?: ?string, jenis?: ?string, dari?: ?string, sampai?: ?string, urut?: ?string, setelah?: ?string}  $filters
     * @return array{rows: list<array<string, mixed>>, more: ?string}
     */
    public function page(int $viewerId, array $filters, int $limit = self::PAGE): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $ascending = ($filters['urut'] ?? null) === 'terlama';
        $product = self::productId($filters['produk'] ?? null);

        if ($product === false) {
            return ['rows' => [], 'more' => null];
        }

        $after = self::cursor($filters['setelah'] ?? null);
        $branch = function (?int $company, bool $pool) use ($filters, $product, $after, $ascending, $limit): Builder {
            return self::base($limit, $ascending)
                ->when($pool, fn (Builder $where) => $where->whereNull('m.company_id'))
                ->when($company !== null, fn (Builder $where) => $where->where('m.company_id', $company))
                ->when($product !== null, fn (Builder $where) => $where->where('e.product_id', $product))
                ->when(is_string($filters['jenis'] ?? null), fn (Builder $where) => $where->where('e.movement_type', $filters['jenis'] ?? ''))
                ->when(self::date($filters['dari'] ?? null) !== null, fn (Builder $where) => $where->where('m.business_date', '>=', self::date($filters['dari'] ?? null)))
                ->when(self::date($filters['sampai'] ?? null) !== null, fn (Builder $where) => $where->where('m.business_date', '<=', self::date($filters['sampai'] ?? null)))
                ->when($after !== null, fn (Builder $where) => $where->where('e.id', $ascending ? '>' : '<', $after));
        };

        if ($viewer->scope->all) {
            $query = self::base($limit, $ascending)
                ->when($product !== null, fn (Builder $where) => $where->where('e.product_id', $product))
                ->when(is_string($filters['jenis'] ?? null), fn (Builder $where) => $where->where('e.movement_type', $filters['jenis'] ?? ''))
                ->when(self::date($filters['dari'] ?? null) !== null, fn (Builder $where) => $where->where('m.business_date', '>=', self::date($filters['dari'] ?? null)))
                ->when(self::date($filters['sampai'] ?? null) !== null, fn (Builder $where) => $where->where('m.business_date', '<=', self::date($filters['sampai'] ?? null)))
                ->when($after !== null, fn (Builder $where) => $where->where('e.id', $ascending ? '>' : '<', $after));
            $found = $query->get()->all();
        } else {
            $query = $branch(null, true);

            foreach ($viewer->scope->companyIds as $company) {
                $query->unionAll($branch($company, false));
            }

            $found = $query->orderBy('entry_id', $ascending ? 'asc' : 'desc')->limit($limit + 1)->get()->all();
        }

        $page = array_slice($found, 0, $limit);
        $rows = self::rows($viewer, $page);
        $last = $page === [] ? null : (int) Row::text($page[count($page) - 1], 'entry_id');

        return ['rows' => $rows, 'more' => count($found) > $limit && $last !== null ? Crypt::encryptString((string) $last) : null];
    }

    private static function base(int $limit, bool $ascending): Builder
    {
        return DB::table('stock_movement_entries as e')->join('stock_movements as m', 'm.id', '=', 'e.stock_movement_id')->join('products as p', 'p.id', '=', 'e.product_id')->join('units as u', 'u.id', '=', 'p.base_unit_id')
            ->join('locations as l', 'l.id', '=', 'e.location_id')->leftJoin('inventory_lots as i', 'i.id', '=', 'e.lot_id')->leftJoin('serial_units as s', 's.id', '=', 'e.serial_unit_id')->leftJoin('users as a', 'a.id', '=', 'm.recorded_by')
            ->leftJoin('companies as c', 'c.id', '=', 'm.company_id')->leftJoin('projects as pr', 'pr.id', '=', 'm.project_id')->leftJoin('purchases as x', 'x.id', '=', 'm.purchase_id')->leftJoin('stock_movements as o', 'o.id', '=', 'm.reverses_movement_id')
            ->orderBy('e.id', $ascending ? 'asc' : 'desc')->limit($limit + 1)
            ->select(['e.id as entry_id', 'm.id as movement_id', 'm.public_id', 'e.movement_type', 'm.business_date', 'm.reason', 'm.company_id', 'm.supplier_delivery_reference', 'e.qty_delta_base', 'e.condition', 'e.lot_id', 'i.lot_code', 'i.receiving_line_id', 's.serial_number', 'l.code as rack', 'p.public_id as product', 'p.name as product_name', 'u.code as unit', 'a.name as actor', 'c.code as company_code', 'pr.public_id as project', 'pr.project_number', 'x.public_id as purchase', 'x.purchase_number', 'o.public_id as corrects']);
    }

    /**
     * @param  list<object>  $page
     * @return list<array<string, mixed>>
     */
    private static function rows(Authority $viewer, array $page): array
    {
        $ids = array_values(array_unique(array_map(fn (object $row): int => (int) Row::text($row, 'movement_id'), $page)));
        $corrected = self::correctedBy($ids);
        $restoring = self::restoring($ids);
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
            $rows[] = [
                'id' => Row::text($row, 'entry_id') === '' ? '' : RowHandle::of('stock_movement_entries', (int) Row::text($row, 'entry_id')),
                'movement' => Row::text($row, 'public_id'), 'direction' => str_starts_with($qty, '-') ? 'OUT' : 'IN', 'type' => $type, 'date' => Row::text($row, 'business_date'), 'product' => Row::text($row, 'product_name'), 'product_href' => '/gudang/stok/'.Row::text($row, 'product'),
                'lot' => Row::nullable($row, 'lot_code'), 'rack' => Row::text($row, 'rack'), 'condition' => Row::text($row, 'condition'), 'qty' => StockFigures::plain(ltrim($qty, '-')), 'unit' => Row::text($row, 'unit'), 'serial' => Row::nullable($row, 'serial_number'), 'reason' => Row::nullable($row, 'reason'), 'actor' => Row::nullable($row, 'actor'), 'company' => Row::nullable($row, 'company_code'),
                'project' => $companyId !== null && Row::nullable($row, 'project') !== null && $viewer->allows('projects.view', $companyId) ? ['label' => Row::text($row, 'project_number'), 'href' => '/proyek/'.Row::text($row, 'project')] : null,
                'purchase' => $companyId !== null && Row::nullable($row, 'purchase') !== null && $viewer->allows('cost.view', $companyId) ? ['label' => Row::text($row, 'purchase_number'), 'href' => '/pembelian/'.Row::text($row, 'purchase')] : null,
                'reference' => $companyId !== null && ($viewer->allows('projects.view', $companyId) || $viewer->allows('stock.receive', $companyId)) ? Row::nullable($row, 'supplier_delivery_reference') : null,
                'corrected_by' => $corrected[$movement] ?? null, 'corrects' => Row::nullable($row, 'corrects') ?? ($restoring[$movement] ?? null),
                'reversible' => $firstOfMovement && $mayReverse && MovementReversal::reversible($type) && ! isset($corrected[$movement]),
                'receipt' => $firstOfMovement && $type === 'RECEIPT' && $mayReverse && Row::nullable($row, 'receiving_line_id') !== null ? RowHandle::of('receiving_lines', (int) Row::text($row, 'receiving_line_id')) : null,
            ];
        }

        return $rows;
    }

    /**
     * The page's movements' reversals: a MOVEMENT_REVERSAL or RECEIPT_REVERSAL naming each, or the restorations of its consumptions ("Dikoreksi oleh").
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private static function correctedBy(array $ids): array
    {
        $found = [];

        foreach ($ids === [] ? [] : DB::table('stock_movements')->whereIn('reverses_movement_id', $ids)->orderBy('id')->get(['reverses_movement_id', 'public_id']) as $row) {
            $found[(int) Row::text($row, 'reverses_movement_id')] = Row::text($row, 'public_id');
        }

        foreach ($ids === [] ? [] : DB::table('stock_consumptions as c')->join('stock_restorations as r', 'r.stock_consumption_id', '=', 'c.id')->join('stock_movements as m', 'm.id', '=', 'r.stock_movement_id')->whereIn('c.stock_movement_id', $ids)->where('r.kind', 'LOSS_REVERSAL')->orderBy('r.id')->get(['c.stock_movement_id', 'm.public_id']) as $row) {
            $found[(int) Row::text($row, 'stock_movement_id')] = Row::text($row, 'public_id');
        }

        return $found;
    }

    /**
     * A restoration movement's original, through its consumptions ("Mengoreksi").
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private static function restoring(array $ids): array
    {
        $found = [];

        foreach ($ids === [] ? [] : DB::table('stock_restorations as r')->join('stock_consumptions as c', 'c.id', '=', 'r.stock_consumption_id')->join('stock_movements as m', 'm.id', '=', 'c.stock_movement_id')->whereIn('r.stock_movement_id', $ids)->where('r.kind', 'LOSS_REVERSAL')->get(['r.stock_movement_id', 'm.public_id']) as $row) {
            $found[(int) Row::text($row, 'stock_movement_id')] = Row::text($row, 'public_id');
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
     * The reversal's reservation-cut block (IP-20): what it takes from usable supply, what is available, the product's reservations by `stock.reserve`.
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
            $taken = Row::text($entry, 'condition') === 'USABLE' && MovementReversal::reversible($movement['type']) && in_array($movement['type'], MovementReversal::BY_INVERSE, true) ? $taken->plus(Row::text($entry, 'qty_delta_base')) : $taken;
        }

        $mayCut = $viewer->allows('stock.reserve', $movement['company']);
        $short = $taken->isPositive() ? InventoryPosting::shortfall($product, (string) $taken) : BigDecimal::zero();

        return ['shortfall' => $short->isPositive() ? StockFigures::plain((string) $short) : null, 'may_cut' => $mayCut, 'reservations' => $mayCut && $short->isPositive() ? $this->views->reservations($viewer, $product) : []];
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

        $id = preg_match(self::UUID, $publicId) === 1 ? DB::table('products')->where('public_id', strtolower($publicId))->value('id') : null;

        return is_numeric($id) ? (int) $id : false;
    }

    private static function cursor(?string $encrypted): ?int
    {
        if ($encrypted === null) {
            return null;
        }

        try {
            $value = Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }

        return ctype_digit($value) ? (int) $value : null;
    }

    private static function date(?string $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
