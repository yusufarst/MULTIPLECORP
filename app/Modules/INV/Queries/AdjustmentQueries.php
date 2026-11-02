<?php

namespace App\Modules\INV\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Data\StockFigures;
use App\Modules\INV\Services\InventoryPosting;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-027 *Penyesuaian & Kondisi*'s and "Pindah Lokasi"'s reads (ROUTE-027; ROUTE-190; NAV-027; PJ-01; PJ-04; CS-05): one product's units at their racks, oldest first as a picking aid only, another company's by its code, rack, condition and quantity with the relation marker; the reservations a cut chooses among; the open cases its units may belong to; the proofs a viewer may cite; the product's recent losses. Physical only: a cost is CST's, by family.
 */
final class AdjustmentQueries
{
    public const CHOICES = 200;

    public const LOSSES = 20;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /** @var array<string, array<string, int>> each list's handles, computed once a request (QB-13) */
    private array $handles = [];

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly StockViews $stock,
    ) {}

    /**
     * The product chosen, by its public id.
     *
     * @return array{id: int, public_id: string, name: string, sku: ?string, serialized: bool, scale: int, unit: string}|null
     */
    public function product(string $publicId): ?array
    {
        $row = preg_match(self::UUID, $publicId) === 1 ? DB::table('products as p')->join('units as u', 'u.id', '=', 'p.base_unit_id')->where('p.public_id', strtolower($publicId))->first(['p.id', 'p.public_id', 'p.name', 'p.sku', 'p.is_serialized', 'p.quantity_scale', 'u.code as unit']) : null;

        return $row === null ? null : ['id' => (int) Row::text($row, 'id'), 'public_id' => Row::text($row, 'public_id'), 'name' => Row::text($row, 'name'), 'sku' => Row::nullable($row, 'sku'), 'serialized' => (bool) Row::text($row, 'is_serialized'), 'scale' => (int) Row::text($row, 'quantity_scale'), 'unit' => Row::text($row, 'unit')];
    }

    /** @return array<string, bool> what the viewer may do here; each command absent without it (IG-01). */
    public function can(int $viewerId): array
    {
        $viewer = $this->capabilities->for($viewerId);

        return ['condition' => $viewer->has('stock.condition'), 'adjust' => $viewer->has('stock.adjust'), 'move' => $viewer->has('stock.move'), 'reverse' => $viewer->has('stock.reverse'), 'reserve' => $viewer->has('stock.reserve'), 'evidence' => $viewer->has('evidence.upload'), 'allocate' => $viewer->has('stock.allocate_intercompany')];
    }

    /**
     * The product's units in QB-05's bound, the oldest usable lot marked; a list longer than CHOICES says so and is narrowed by what is looked for or scanned.
     *
     * @return array{find: ?string, places: list<array<string, mixed>>, serials: list<array<string, mixed>>, more: bool, available: string, reservations: list<array<string, mixed>>}
     */
    public function screen(int $viewerId, int $productId, ?string $find = null): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $like = $find === null ? null : '%'.addcslashes(mb_strtolower($find), '%_\\').'%';
        $foundPlaces = DB::table('stock_lot_balances as b')->join('inventory_lots as i', 'i.id', '=', 'b.lot_id')->join('locations as l', 'l.id', '=', 'b.location_id')->where('b.product_id', $productId)->where('b.qty', '>', 0)
            ->when($like !== null, fn (Builder $where) => $where->whereRaw("(lower(i.lot_code) like ? or exists (select 1 from serial_units f where f.current_lot_id = i.id and f.current_location_id = b.location_id and f.current_condition = b.condition and f.state = 'IN_STOCK' and lower(f.serial_number) like ?))", [$like, $like]))
            ->orderBy('i.business_date')->orderBy('i.id')->orderBy('l.code')->limit(self::CHOICES + 1)->get(['i.id', 'i.lot_code', 'i.source_company_id', 'b.qty', 'b.location_id', 'b.condition', 'l.code as rack'])->all();
        $places = [];
        $unflagged = $find === null;

        foreach (array_slice($foundPlaces, 0, self::CHOICES) as $row) {
            $usable = Row::text($row, 'condition') === 'USABLE';
            $places[] = ['lot' => RowHandle::of('inventory_lots', (int) Row::text($row, 'id')), 'code' => Row::text($row, 'lot_code'), 'location' => RowHandle::of('locations', (int) Row::text($row, 'location_id')), 'rack' => Row::text($row, 'rack'), 'condition' => Row::text($row, 'condition'), 'qty' => StockFigures::plain(Row::text($row, 'qty')), 'other' => ! $viewer->scope->includes((int) Row::text($row, 'source_company_id')), 'oldest' => $unflagged && $usable];
            $unflagged = $unflagged && ! $usable;
        }

        $foundSerials = DB::table('serial_units as s')->join('inventory_lots as i', 'i.id', '=', 's.current_lot_id')->join('locations as l', 'l.id', '=', 's.current_location_id')->where('s.product_id', $productId)->where('s.state', 'IN_STOCK')
            ->when($like !== null, fn (Builder $where) => $where->whereRaw('(lower(s.serial_number) like ? or lower(i.lot_code) like ?)', [$like, $like]))
            ->orderBy('s.serial_number')->limit(self::CHOICES + 1)->get(['s.id', 's.serial_number', 's.current_lot_id', 's.current_location_id', 's.current_condition', 'i.lot_code', 'i.source_company_id', 'l.code as rack'])->all();
        $serials = array_map(fn (object $row): array => ['handle' => RowHandle::of('serial_units', (int) Row::text($row, 'id')), 'serial' => Row::text($row, 'serial_number'), 'lot' => RowHandle::of('inventory_lots', (int) Row::text($row, 'current_lot_id')), 'location' => RowHandle::of('locations', (int) Row::text($row, 'current_location_id')), 'condition' => Row::text($row, 'current_condition'), 'code' => Row::text($row, 'lot_code'), 'rack' => Row::text($row, 'rack'), 'other' => ! $viewer->scope->includes((int) Row::text($row, 'source_company_id'))],
            array_slice($foundSerials, 0, self::CHOICES));

        return [
            'find' => $find,
            'places' => $places,
            'serials' => $serials,
            'more' => count($foundPlaces) > self::CHOICES || count($foundSerials) > self::CHOICES,
            'available' => StockFigures::plain((string) InventoryPosting::available($productId)),
            'reservations' => $viewer->has('stock.reserve') ? $this->stock->reservations($viewer, $productId) : [],
        ];
    }

    /**
     * The active racks, for the move and a chosen destination.
     *
     * @return list<array{id: string, code: string, name: string}>
     */
    public function racks(): array
    {
        return array_values(array_map(fn (object $row): array => ['id' => RowHandle::of('locations', (int) Row::text($row, 'id')), 'code' => Row::text($row, 'code'), 'name' => Row::text($row, 'name')], DB::table('locations')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])->all()));
    }

    /**
     * The open cases of a damaged unit of this product within the viewer's scope (CM-11), newest first, narrowed by lot or project when the list is cut: a lost unit under one of them is that case's single loss outcome.
     *
     * @return array{find: ?string, rows: list<array{id: string, kind: string, date: string, lot: ?string, project: ?string, cross: bool}>, more: bool}
     */
    public function cases(int $viewerId, int $productId, ?string $find = null): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $like = $find === null ? null : '%'.addcslashes(mb_strtolower($find), '%_\\').'%';

        $found = array_values(array_map(fn (object $row): array => ['id' => RowHandle::of('correction_cases', (int) Row::text($row, 'id')), 'kind' => Row::text($row, 'case_kind'), 'date' => substr(Row::text($row, 'recorded_at'), 0, 10), 'lot' => Row::nullable($row, 'lot_code'), 'project' => Row::nullable($row, 'project_number'), 'cross' => Row::nullable($row, 'project_company') !== null && Row::nullable($row, 'project_company') !== Row::text($row, 'source_company_id')],
            $viewer->scope->apply(DB::table('correction_cases as k'), 'k.company_id')->join('inventory_lots as i', 'i.id', '=', 'k.source_lot_id')->leftJoin('projects as p', 'p.id', '=', 'k.causal_project_id')->where('i.product_id', $productId)->where('k.state', 'OPEN')->whereIn('k.case_kind', ['SALES_RETURN', 'DAMAGED_UNIT'])
                ->when($like !== null, fn (Builder $where) => $where->whereRaw("lower(coalesce(i.lot_code, '') || ' ' || coalesce(p.project_number, '')) like ?", [$like]))
                ->orderByDesc('k.id')->limit(self::LOSSES + 1)->get(['k.id', 'k.case_kind', 'k.recorded_at', 'i.lot_code', 'i.source_company_id', 'p.project_number', 'p.company_id as project_company'])->all()));

        return ['find' => $find, 'rows' => array_slice($found, 0, self::LOSSES), 'more' => count($found) > self::LOSSES];
    }

    /**
     * The evidence already registered that a command may cite, newest first, for a viewer who may see it: pool evidence, as shared stock's is (PJ-08), narrowed by issuer or reference when the list is cut. A movement of the pool has no company for a company's evidence to link to.
     *
     * @return array{rows: list<array{id: string, label: string}>, more: bool}
     */
    public function proofs(int $viewerId, ?string $find = null): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $like = $find === null ? null : '%'.addcslashes(mb_strtolower($find), '%_\\').'%';
        $found = self::poolEvidence()
            ->when($like !== null, fn (Builder $where) => $where->whereRaw("lower(e.declared_issuer || ' ' || coalesce(e.issuer_reference, '')) like ?", [$like]))
            ->orderByDesc('e.id')->limit(self::LOSSES * 3 + 1)->get(['e.public_id', 'e.evidence_type', 'e.declared_issuer', 'e.issuer_reference', 'e.document_date'])->all();
        $rows = [];

        foreach (array_slice($found, 0, self::LOSSES * 3) as $row) {
            if (EvidenceTypes::mayView($viewer, Row::text($row, 'evidence_type'), null)) {
                $type = __('evidence.type.'.Row::text($row, 'evidence_type'));
                $rows[] = ['id' => Row::text($row, 'public_id'), 'label' => implode(' · ', array_filter([is_string($type) ? $type : '', Row::nullable($row, 'declared_issuer'), Row::nullable($row, 'issuer_reference'), Row::nullable($row, 'document_date')], fn (?string $part): bool => $part !== null && $part !== ''))];
            }
        }

        return ['find' => $find, 'rows' => $rows, 'more' => count($found) > self::LOSSES * 3];
    }

    /** A pool evidence the viewer may see, by public id (PJ-08; CS-04). */
    public function evidence(int $viewerId, string $publicId): ?int
    {
        $row = preg_match(self::UUID, $publicId) === 1 ? self::poolEvidence()->where('e.public_id', strtolower($publicId))->first(['e.id', 'e.evidence_type']) : null;

        return $row !== null && EvidenceTypes::mayView($this->capabilities->for($viewerId), Row::text($row, 'evidence_type'), null) ? (int) Row::text($row, 'id') : null;
    }

    /**
     * The product's recent losses and adjustments, each with what is still open, its movement's id for the reversal: only those whose lot or bearer company is in the viewer's scope, never the other side's reason (PJ-05; PJ-06).
     *
     * @return list<array<string, mixed>>
     */
    public function losses(int $viewerId, int $productId): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $rows = DB::table('stock_movements as m')->join('stock_consumptions as c', 'c.stock_movement_id', '=', 'm.id')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->join('inventory_lots as i', 'i.id', '=', 'c.lot_id')
            ->where('c.product_id', $productId)->where('c.kind', 'LOSS')->whereIn('m.movement_type', ['ADJUSTMENT_DECREASE', 'LOSS'])
            ->where(fn (Builder $where) => $where->where(fn (Builder $bearer) => $viewer->scope->apply($bearer, 'c.bearer_company_id'))->orWhere(fn (Builder $source) => $viewer->scope->apply($source, 'c.source_company_id')))
            ->groupBy('m.id', 'm.public_id', 'm.movement_type', 'm.business_date')->orderByDesc('m.id')->limit(self::LOSSES)
            ->selectRaw('m.id, m.public_id, m.movement_type, m.business_date, sum(b.qty_base) as qty, sum(b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base) as open, min(i.lot_code) as lot')->get()->all();

        return array_values(array_map(fn (object $row): array => ['internal' => (int) Row::text($row, 'id'), 'id' => Row::text($row, 'public_id'), 'type' => Row::text($row, 'movement_type'), 'date' => Row::text($row, 'business_date'), 'qty' => StockFigures::plain(Row::text($row, 'qty')), 'open' => StockFigures::plain(Row::text($row, 'open')), 'lot' => Row::text($row, 'lot')], $rows));
    }

    public function lot(int $productId, string $handle): ?int
    {
        return $this->found("lots|{$productId}", 'inventory_lots', fn (): array => DB::table('inventory_lots')->where('product_id', $productId)->pluck('id')->all(), $handle);
    }

    public function location(string $handle): ?int
    {
        return $this->found('racks', 'locations', fn (): array => DB::table('locations')->pluck('id')->all(), $handle);
    }

    public function serial(int $productId, string $handle): ?int
    {
        return $this->found("serials|{$productId}", 'serial_units', fn (): array => DB::table('serial_units')->where('product_id', $productId)->pluck('id')->all(), $handle);
    }

    public function reservation(int $productId, string $handle): ?int
    {
        return $this->found("reservations|{$productId}", 'reservations', fn (): array => DB::table('reservations')->where('product_id', $productId)->pluck('id')->all(), $handle);
    }

    /** An open case of the product's lots within the viewer's scope (CS-04). */
    public function case(int $viewerId, int $productId, string $handle): ?int
    {
        $viewer = $this->capabilities->for($viewerId);

        return $this->found("cases|{$viewerId}|{$productId}", 'correction_cases', fn (): array => $viewer->scope->apply(DB::table('correction_cases as k'), 'k.company_id')->join('inventory_lots as i', 'i.id', '=', 'k.source_lot_id')->where('i.product_id', $productId)->pluck('k.id')->all(), $handle);
    }

    private static function poolEvidence(): Builder
    {
        return DB::table('evidence_documents as e')->where('e.evidence_scope', 'POOL')->whereIn('e.evidence_type', EvidenceTypes::POOL_TYPES);
    }

    /** @param  Closure(): array<int|string, mixed>  $ids */
    private function found(string $list, string $table, Closure $ids, string $handle): ?int
    {
        // A handle not yet known reads the list again: a row may have come since; one known needs no statement (QB-13).
        if (! isset($this->handles[$list][$handle])) {
            $this->handles[$list] = [];

            foreach ($ids() as $id) {
                if (is_numeric($id)) {
                    $this->handles[$list][RowHandle::of($table, (int) $id)] = (int) $id;
                }
            }
        }

        return $this->handles[$list][$handle] ?? null;
    }

    public static function today(): string
    {
        return BusinessDate::today();
    }
}
