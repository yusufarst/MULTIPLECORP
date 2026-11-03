<?php

namespace App\Modules\INV\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Data\StockFigures;
use App\Modules\OPS\Queries\CorrectionTrail;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-028 "Retur ke Pemasok" (ROUTE-028; SCR-26; J-PUR-03; PJ-16): the purchase-return cases of the companies where the viewer records returns, open first; one case with its units, figures, outcomes and trail; the form a purchase's lot opens. Money figures to `cost.view` of the case's company only (SM-25); another company's causal project named only within scope (XL-3).
 */
final class SupplierReturnQueries
{
    public const PAGE = 50;

    public const PROOFS = 50;

    /** The received lots of a product a lot's handle is looked up among, newest first. */
    private const LOTS = 5000;

    private const SUPPLIER = "pu.supplier_snapshot->>'name' as supplier";

    /** A drop-ship return's case names no lot: its purchase line is its confirmation line's (CM-15; TASK-046). */
    private const DROPSHIP_LINE = "(select d.purchase_line_id from stock_restorations s join dropship_confirmation_lines d on d.id = s.dropship_confirmation_line_id where s.correction_case_id = c.id and s.kind = 'DROPSHIP_SUPPLIER_RETURN' order by s.id limit 1)";

    /** The lot a case returned from, or a drop-ship return's supplier shipment reference in its place. */
    private const LOT = "coalesce(i.lot_code, (select k.supplier_shipment_reference from stock_restorations s join dropship_confirmation_lines d on d.id = s.dropship_confirmation_line_id join dropship_confirmations k on k.id = d.dropship_confirmation_id where s.correction_case_id = c.id and s.kind = 'DROPSHIP_SUPPLIER_RETURN' order by s.id limit 1)) as lot_code";

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly AdjustmentQueries $adjustments,
        private readonly CorrectionTrail $trail,
    ) {}

    /** @return array<string, bool> */
    public function can(int $viewerId): array
    {
        $viewer = $this->capabilities->for($viewerId);

        return ['record' => $viewer->has('purchase_return.record'), 'close' => $viewer->has('cases.close'), 'reserve' => $viewer->has('stock.reserve'), 'allocate' => $viewer->has('stock.allocate_intercompany'), 'evidence' => $viewer->has('evidence.upload')];
    }

    /**
     * The cases, open first, newest first within each, by cursor (QB-05).
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string, cursor_failed: bool}
     */
    public function list(int $viewerId, ?string $after): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $key = $after === null ? null : StockCursor::read($viewer, $after, ['list' => 'returns'], 2);

        if ($after !== null && $key === null) {
            return ['rows' => [], 'more' => null, 'cursor_failed' => true];
        }

        $open = (int) ($key[0] ?? 0);
        $last = (int) ($key[1] ?? 0);
        $found = self::cases($this->companies($viewer))
            ->when($key !== null, fn (Builder $keyset) => $keyset->where(fn (Builder $later) => $later
                ->where(fn (Builder $same) => $same->where('c.state', $open === 1 ? 'OPEN' : 'CLOSED')->where('c.id', '<', $last))
                ->when($open === 1, fn (Builder $closed) => $closed->orWhere('c.state', 'CLOSED'))))
            ->orderByRaw("c.state = 'OPEN' desc")->orderByDesc('c.id')->limit(self::PAGE + 1)
            ->get(['c.id', 'c.public_id', 'c.state', 'c.company_id', 'c.recorded_at', 'c.returned_cost', 'c.refunded_value', 'c.replaced_value', 'c.unrecovered_value', 'pu.public_id as purchase', 'pu.purchase_number', DB::raw(self::SUPPLIER), DB::raw(self::LOT), 'p.name as product'])->all();
        $rows = array_slice($found, 0, self::PAGE);
        $tail = $rows === [] ? null : $rows[count($rows) - 1];

        return [
            'rows' => array_map(fn (object $row): array => [
                'id' => Row::text($row, 'public_id'), 'state' => Row::text($row, 'state'), 'date' => substr(Row::text($row, 'recorded_at'), 0, 10),
                'purchase' => Row::text($row, 'purchase'), 'number' => Row::text($row, 'purchase_number'), 'supplier' => Row::nullable($row, 'supplier') ?? '', 'lot' => Row::text($row, 'lot_code'), 'product' => Row::text($row, 'product'),
                'open' => $viewer->allows('cost.view', (int) Row::text($row, 'company_id')) ? self::open($row)->toString() : null,
            ], $rows),
            'more' => count($found) > self::PAGE && $tail !== null ? StockCursor::of($viewer, [Row::text($tail, 'state') === 'OPEN' ? 1 : 0, (int) Row::text($tail, 'id')], ['list' => 'returns']) : null,
            'cursor_failed' => false,
        ];
    }

    /**
     * One case: its figures, the units returned, its outcomes and its trail.
     *
     * @return array<string, mixed>|null
     */
    public function detail(int $viewerId, string $publicId): ?array
    {
        $viewer = $this->capabilities->for($viewerId);
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? self::cases($this->companies($viewer))->leftJoin('projects as cp', 'cp.id', '=', 'c.causal_project_id')->where('c.public_id', strtolower($publicId))
            ->first(['c.id', 'c.public_id', 'c.state', 'c.company_id', 'c.reason', 'c.lock_version', 'c.recorded_at', 'c.returned_cost', 'c.refunded_value', 'c.replaced_value', 'c.unrecovered_value', 'c.source_lot_id', 'cp.project_number', 'cp.company_id as causal_company',
                'pu.public_id as purchase', 'pu.purchase_number', DB::raw(self::SUPPLIER), DB::raw(self::LOT), 'p.name as product', 'p.quantity_scale', 'pl.id as line']) : null;

        if ($row === null) {
            return null;
        }

        $id = (int) Row::text($row, 'id');
        $company = (int) Row::text($row, 'company_id');
        $cost = $viewer->allows('cost.view', $company);
        $reverse = Row::text($row, 'state') === 'OPEN' && $viewer->allows('stock.reverse', $company);
        $state = Row::text($row, 'state');
        $open = self::open($row);
        $causalCompany = Row::nullable($row, 'causal_company');
        $line = (int) Row::text($row, 'line');
        $guard = DB::table('purchase_line_balances as b')->where('purchase_line_id', $line)->first(['replacement_expected_qty_base', 'replacement_received_net_qty_base']);
        // A replacement is the case's own: never above the units it returned less what it already declared (C-29).
        $returnedQty = BigDecimal::of((string) DB::table('stock_consumptions')->where('correction_case_id', $id)->where('kind', 'PURCHASE_RETURN')->sum('qty_base'))
            ->plus((string) DB::table('stock_restorations')->where('correction_case_id', $id)->where('kind', 'DROPSHIP_SUPPLIER_RETURN')->sum('qty_base'))
            ->minus((string) DB::table('stock_restorations')->where('correction_case_id', $id)->whereIn('kind', ['RETURN_ERROR_REVERSAL', 'DROPSHIP_ERROR_REVERSAL'])->sum('qty_base'));
        $expected =BigDecimal::of($guard === null ? '0' : Row::text($guard, 'replacement_expected_qty_base'));

        return [
            'id' => Row::text($row, 'public_id'), 'state' => $state, 'reason' => Row::text($row, 'reason'), 'lock_version' => (int) Row::text($row, 'lock_version'), 'date' => substr(Row::text($row, 'recorded_at'), 0, 10),
            'purchase' => Row::text($row, 'purchase'), 'number' => Row::text($row, 'purchase_number'), 'supplier' => Row::nullable($row, 'supplier') ?? '', 'lot' => Row::text($row, 'lot_code'), 'product' => Row::text($row, 'product'), 'scale' => (int) Row::text($row, 'quantity_scale'),
            // XL-3: another company's causal project named only within scope.
            'causal' => $causalCompany === null ? null : ($viewer->scope->includes((int) $causalCompany) ? Row::nullable($row, 'project_number') : ''),
            'figures' => $cost ? ['returned' => Money::of(Row::text($row, 'returned_cost'))->toString(), 'refunded' => Money::of(Row::text($row, 'refunded_value'))->toString(), 'replaced' => Money::of(Row::text($row, 'replaced_value'))->toString(), 'unrecovered' => Money::of(Row::text($row, 'unrecovered_value'))->toString(), 'open' => $open->toString()] : null,
            'replacement' => ['returned' => StockFigures::plain((string) $returnedQty), 'expected' => StockFigures::plain((string) $expected), 'received' => StockFigures::plain($guard === null ? '0' : Row::text($guard, 'replacement_received_net_qty_base')), 'room' => StockFigures::plain((string) BigDecimal::max(BigDecimal::zero(), $returnedQty->minus(self::declared($id))))],
            'units' => $this->units($id, $cost, $reverse),
            // The racks a purchase return reversed brings its units back to (ROUTE-211).
            'racks' => $reverse ? array_values(array_map(fn (object $rack): array => ['id' => RowHandle::of('locations', (int) Row::text($rack, 'id')), 'code' => Row::text($rack, 'code'), 'name' => Row::text($rack, 'name')], DB::table('locations')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])->all())) : [],
            'events' => self::events($id),
            'trail' => $this->trail->of($viewer, 'correction_case', Row::text($row, 'public_id')),
            'can' => [
                'outcome' => $state === 'OPEN' && $viewer->allows('purchase_return.record', $company),
                'close' => $state === 'OPEN' && $open->isZero() && $viewer->allows('cases.close', $company),
            ],
        ];
    }

    /**
     * The form a purchase opens: its lots holding units now.
     *
     * @return array<string, mixed>|null
     */
    public function purchase(int $viewerId, string $publicId): ?array
    {
        $found = $this->purchaseOf($viewerId, $publicId);

        if ($found === null) {
            return null;
        }

        $lots = DB::table('inventory_lots as i')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->join('purchase_lines as l', 'l.id', '=', 'r.purchase_line_id')->join('products as p', 'p.id', '=', 'i.product_id')->join('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('l.purchase_id', $found['id'])->where('i.source_company_id', $found['company'])
            ->whereExists(fn (Builder $held) => $held->from('stock_lot_balances as b')->whereColumn('b.lot_id', 'i.id')->where('b.qty', '>', 0))
            ->orderBy('l.line_no')->orderBy('i.id')->get(['i.id', 'i.lot_code', 'p.public_id as product', 'p.name', 'p.is_serialized', 'p.quantity_scale', 'u.code as unit'])->all();

        return [
            'id' => $found['public_id'], 'number' => $found['number'], 'supplier' => $found['supplier'],
            'lots' => array_values(array_map(fn (object $lot): array => ['lot' => RowHandle::of('inventory_lots', (int) Row::text($lot, 'id')), 'code' => Row::text($lot, 'lot_code'), 'product' => Row::text($lot, 'product'), 'name' => Row::text($lot, 'name'), 'serialized' => (bool) Row::text($lot, 'is_serialized'), 'scale' => (int) Row::text($lot, 'quantity_scale'), 'unit' => Row::text($lot, 'unit')], $lots)),
        ];
    }

    /**
     * The chosen lot's places and serials, the product's reservations and availability (IP-20): PG-027's screen narrowed to the lot.
     *
     * @return array<string, mixed>
     */
    public function lotUnits(int $viewerId, int $lotId): array
    {
        $lot = DB::table('inventory_lots')->where('id', $lotId)->first(['product_id', 'lot_code']);

        if ($lot === null) {
            return ['places' => [], 'serials' => [], 'more' => false, 'available' => '0', 'reservations' => []];
        }

        $handle = RowHandle::of('inventory_lots', $lotId);
        $screen = $this->adjustments->screen($viewerId, (int) Row::text($lot, 'product_id'), Row::text($lot, 'lot_code'));
        $mine = fn (array $one): bool => ($one['lot'] ?? null) === $handle;

        return ['places' => array_values(array_filter($screen['places'], $mine)), 'serials' => array_values(array_filter($screen['serials'], $mine)), 'more' => $screen['more'], 'available' => $screen['available'], 'reservations' => $screen['reservations']];
    }

    /**
     * The case company's proofs a supplier credit may cite, the newest first, narrowed by what is looked for.
     *
     * @return array{find: ?string, rows: list<array{id: string, label: string}>, more: bool}
     */
    public function proofs(int $viewerId, string $casePublicId, ?string $find): array
    {
        $case = $this->caseOf($viewerId, $casePublicId);
        $types = $case === null ? [] : self::visibleTypes($this->capabilities->for($viewerId), $case['company']);

        if ($case === null || $types === []) {
            return ['find' => $find, 'rows' => [], 'more' => false];
        }

        $like = $find === null ? null : '%'.addcslashes(mb_strtolower($find), '%_\\').'%';
        $found = DB::table('evidence_documents as e')->where('e.company_id', $case['company'])->where('e.evidence_scope', 'COMPANY')->whereIn('e.evidence_type', $types)
            ->when($like !== null, fn (Builder $where) => $where->whereRaw('(lower(e.declared_issuer) like ? or lower(e.issuer_reference) like ?)', [$like, $like]))
            ->orderByDesc('e.id')->limit(self::PROOFS + 1)->get(['e.public_id', 'e.declared_issuer', 'e.issuer_reference', 'e.document_date'])->all();

        return [
            'find' => $find,
            'rows' => array_map(fn (object $row): array => ['id' => Row::text($row, 'public_id'), 'label' => Row::text($row, 'declared_issuer').' · '.Row::text($row, 'issuer_reference').' · '.Row::text($row, 'document_date')], array_slice($found, 0, self::PROOFS)),
            'more' => count($found) > self::PROOFS,
        ];
    }

    /** @return array{id: int, company: int, public_id: string, number: string, supplier: string}|null a purchase the viewer records returns of */
    public function purchaseOf(int $viewerId, string $publicId): ?array
    {
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('purchases as pu')->where('pu.public_id', strtolower($publicId))->first(['pu.id', 'pu.company_id', 'pu.public_id', 'pu.purchase_number', DB::raw(self::SUPPLIER)]) : null;

        if ($row === null || ! $this->capabilities->for($viewerId)->allows('purchase_return.record', (int) Row::text($row, 'company_id'))) {
            return null;
        }

        return ['id' => (int) Row::text($row, 'id'), 'company' => (int) Row::text($row, 'company_id'), 'public_id' => Row::text($row, 'public_id'), 'number' => Row::text($row, 'purchase_number'), 'supplier' => Row::nullable($row, 'supplier') ?? ''];
    }

    /** The purchase a product's received lot came from, for the lot's "Retur ke Pemasok" (ADMIN_FLOW §11 CM-09): its public id, or null outside the viewer's companies. */
    public function purchaseOfLot(int $viewerId, string $productId, string $handle): ?string
    {
        if (preg_match('/^[0-9a-f-]{36}$/i', $productId) !== 1 || ! RowHandle::valid($handle)) {
            return null;
        }

        $viewer = $this->capabilities->for($viewerId);

        foreach (DB::table('inventory_lots as i')->join('products as p', 'p.id', '=', 'i.product_id')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->join('purchase_lines as l', 'l.id', '=', 'r.purchase_line_id')->join('purchases as pu', 'pu.id', '=', 'l.purchase_id')
            ->where('p.public_id', strtolower($productId))->orderByDesc('i.id')->limit(self::LOTS)->get(['i.id', 'pu.public_id', 'pu.company_id'])->all() as $lot) {
            if (hash_equals(RowHandle::of('inventory_lots', (int) Row::text($lot, 'id')), $handle)) {
                return $viewer->allows('purchase_return.record', (int) Row::text($lot, 'company_id')) ? Row::text($lot, 'public_id') : null;
            }
        }

        return null;
    }

    /** @return array{id: int, company: int, purchase: string}|null a purchase-return case the viewer records outcomes of */
    public function caseOf(int $viewerId, string $publicId): ?array
    {
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? self::cases(null)->where('c.public_id', strtolower($publicId))->first(['c.id', 'c.company_id', 'pu.public_id as purchase']) : null;

        if ($row === null || ! $this->capabilities->for($viewerId)->allows('purchase_return.record', (int) Row::text($row, 'company_id'))) {
            return null;
        }

        return ['id' => (int) Row::text($row, 'id'), 'company' => (int) Row::text($row, 'company_id'), 'purchase' => Row::text($row, 'purchase')];
    }

    /** A lot of the purchase by its handle, among the purchase's own lots (RowHandle). */
    public function lotOf(int $purchaseId, string $handle): ?int
    {
        if (! RowHandle::valid($handle)) {
            return null;
        }

        foreach (DB::table('inventory_lots as i')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->join('purchase_lines as l', 'l.id', '=', 'r.purchase_line_id')->where('l.purchase_id', $purchaseId)->orderByDesc('i.id')->pluck('i.id') as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('inventory_lots', (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    /** The lot's product. */
    public function productOf(int $lotId): int
    {
        $product = DB::table('inventory_lots')->where('id', $lotId)->value('product_id');

        return is_numeric($product) ? (int) $product : 0;
    }

    /** The case a return opened, for its answer (NAV-028). */
    public function casePublicId(int $caseId): ?string
    {
        $publicId = DB::table('correction_cases')->where('id', $caseId)->value('public_id');

        return is_string($publicId) ? $publicId : null;
    }

    /** A proof of the case's company by its public identifier. */
    public function evidenceOf(int $viewerId, int $companyId, string $publicId): ?int
    {
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('evidence_documents')->where('public_id', strtolower($publicId))->where('company_id', $companyId)->where('evidence_scope', 'COMPANY')->first(['id', 'evidence_type']) : null;

        return $row !== null && EvidenceTypes::mayView($this->capabilities->for($viewerId), Row::text($row, 'evidence_type'), $companyId) ? (int) Row::text($row, 'id') : null;
    }

    /** @return list<string> the evidence types the viewer sees in the company: `evidence.view` and the type's family (PJ-08) */
    private static function visibleTypes(Authority $viewer, int $companyId): array
    {
        return array_values(array_filter(EvidenceTypes::TYPES, fn (string $type): bool => EvidenceTypes::mayView($viewer, $type, $companyId)));
    }

    /** @param  list<int>|null  $companies */
    private static function cases(?array $companies): Builder
    {
        return DB::table('correction_cases as c')->leftJoin('inventory_lots as i', 'i.id', '=', 'c.source_lot_id')->leftJoin('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')
            ->join('purchase_lines as pl', 'pl.id', '=', DB::raw('coalesce(r.purchase_line_id, '.self::DROPSHIP_LINE.')'))->join('products as p', 'p.id', '=', 'pl.product_id')->join('purchases as pu', 'pu.id', '=', 'pl.purchase_id')
            ->where('c.case_kind', 'PURCHASE_RETURN')->when($companies !== null, fn (Builder $scoped) => $scoped->whereIn('c.company_id', $companies ?? []));
    }

    /** @return list<int> the companies where the viewer records purchase returns */
    private function companies(Authority $viewer): array
    {
        return array_values(array_filter(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, DB::table('companies')->orderBy('id')->pluck('id')->all()), fn (int $id): bool => $viewer->allows('purchase_return.record', $id)));
    }

    /** @return list<array<string, mixed>> */
    private function units(int $caseId, bool $cost, bool $reverse): array
    {
        $rows = DB::table('stock_consumptions as c')->join('inventory_lots as i', 'i.id', '=', 'c.lot_id')->join('stock_movement_entries as e', 'e.stock_consumption_id', '=', 'c.id')->join('locations as l', 'l.id', '=', 'e.location_id')->leftJoin('serial_units as s', 's.id', '=', 'e.serial_unit_id')
            ->join('stock_movements as m', 'm.id', '=', 'c.stock_movement_id')->where('c.correction_case_id', $caseId)->where('c.kind', 'PURCHASE_RETURN')->whereColumn('e.stock_movement_id', 'c.stock_movement_id')->orderBy('c.id')
            ->get(['c.id', 'c.qty_base', 'i.lot_code', 'l.code as rack', 'e.condition', 's.serial_number', 'm.public_id as movement',
                DB::raw("(select coalesce(sum(r.qty_base), 0) from stock_restorations r where r.stock_consumption_id = c.id and r.kind = 'RETURN_ERROR_REVERSAL') as reversed")])->all();
        $costs = [];

        foreach ($cost && $rows !== [] ? DB::table('cost_attributions')->whereIn('stock_consumption_id', array_map(fn (object $row): int => (int) Row::text($row, 'id'), $rows))->where('cost_class', 'SUPPLIER_RETURN')->where('cause', 'INITIAL')->get(['stock_consumption_id', 'amount']) : [] as $attribution) {
            $key = (int) Row::text($attribution, 'stock_consumption_id');
            $costs[$key] = Money::of($costs[$key] ?? '0')->plus(Money::of(Row::text($attribution, 'amount')))->toString();
        }

        // ROUTE-211 "Balikkan (Tercatat Keliru)" on a unit still returned, with `stock.reverse` (CM-37; AX-36).
        $units = array_values(array_map(function (object $row) use ($cost, $costs, $reverse): array {
            $standing = BigDecimal::of(Row::text($row, 'qty_base'))->minus(Row::text($row, 'reversed'));

            return ['lot' => Row::text($row, 'lot_code'), 'rack' => Row::text($row, 'rack'), 'condition' => Row::text($row, 'condition'), 'serial' => Row::nullable($row, 'serial_number'), 'qty' => StockFigures::plain(Row::text($row, 'qty_base')), 'cost' => $cost ? ($costs[(int) Row::text($row, 'id')] ?? '0.00') : null,
                'reverse' => $reverse && $standing->isPositive() ? ['original' => Row::text($row, 'movement'), 'consumption' => RowHandle::of('stock_consumptions', (int) Row::text($row, 'id')), 'restoration' => null, 'standing' => StockFigures::plain((string) $standing)] : null];
        }, $rows));

        // A drop-ship return moved no stock: its units are the confirmation lines' restorations, named by the supplier's shipment reference (CM-15); one is undone whole (DROPSHIP_ERROR_REVERSAL).
        foreach (DB::table('stock_restorations as r')->join('dropship_confirmation_lines as d', 'd.id', '=', 'r.dropship_confirmation_line_id')->join('dropship_confirmations as k', 'k.id', '=', 'd.dropship_confirmation_id')
            ->leftJoin('serial_units as s', 's.id', '=', 'd.serial_unit_id')->where('r.correction_case_id', $caseId)->where('r.kind', 'DROPSHIP_SUPPLIER_RETURN')->orderBy('r.id')
            ->get(['r.id', 'r.qty_base', 'k.public_id as confirmation', 'k.supplier_shipment_reference', 's.serial_number', DB::raw("(select coalesce(sum(a.amount), 0) from cost_attributions a where a.stock_restoration_id = r.id and a.cost_class = 'SUPPLIER_RETURN') as cost"),
                DB::raw('exists (select 1 from stock_restorations x where x.reverses_restoration_id = r.id) as undone')])->all() as $row) {
            $units[] = ['lot' => Row::text($row, 'supplier_shipment_reference'), 'rack' => '', 'condition' => null, 'serial' => Row::nullable($row, 'serial_number'), 'qty' => StockFigures::plain(Row::text($row, 'qty_base')), 'cost' => $cost ? Money::of(Row::text($row, 'cost'))->toString() : null,
                'reverse' => $reverse && ! (bool) Row::text($row, 'undone') ? ['original' => Row::text($row, 'confirmation'), 'consumption' => null, 'restoration' => RowHandle::of('stock_restorations', (int) Row::text($row, 'id')), 'standing' => StockFigures::plain(Row::text($row, 'qty_base'))] : null];
        }

        return $units;
    }

    /** @return list<array{action: string, reason: ?string, by: ?string, at: string, outcome: ?string, correction: bool}> the case's own audit events, oldest first (BR-CR-01) */
    private static function events(int $caseId): array
    {
        return array_values(array_map(fn (object $row): array => [
            'action' => Row::text($row, 'action'), 'reason' => Row::nullable($row, 'reason'), 'by' => Row::nullable($row, 'name'), 'at' => Row::text($row, 'occurred_at'),
            'outcome' => self::outcomeOf(Row::nullable($row, 'after')), 'correction' => self::correctionOf(Row::nullable($row, 'after')),
        ], DB::table('audit_events as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')->where('a.entity_type', 'correction_case')->where('a.entity_id', $caseId)->orderBy('a.occurred_at')->orderBy('a.id')->get(['a.action', 'a.reason', 'a.occurred_at', 'a.after', 'u.name'])->all()));
    }

    /** Whether an event's after-image takes back an earlier outcome. */
    private static function correctionOf(?string $after): bool
    {
        $decoded = $after === null ? null : json_decode($after, true);

        return is_array($decoded) && ($decoded['correction'] ?? false) === true;
    }

    /** The replacement the case declared, net of its corrections. */
    private static function declared(int $caseId): BigDecimal
    {
        $net = BigDecimal::zero();

        foreach (DB::table('audit_events')->where('entity_type', 'correction_case')->where('entity_id', $caseId)->where('action', 'PUR.ReturnOutcomeRecorded')->orderBy('id')->pluck('after')->all() as $after) {
            $decoded = is_string($after) ? json_decode($after, true) : null;

            if (! is_array($decoded) || ($decoded['outcome'] ?? null) !== 'REPLACEMENT' || ! is_string($decoded['quantity'] ?? null)) {
                continue;
            }

            $net = ($decoded['correction'] ?? false) === true ? $net->minus($decoded['quantity']) : $net->plus($decoded['quantity']);
        }

        return $net;
    }

    /** The outcome an event's after-image names, if any. */
    private static function outcomeOf(?string $after): ?string
    {
        $decoded = $after === null ? null : json_decode($after, true);
        $outcome = is_array($decoded) ? ($decoded['outcome'] ?? null) : null;

        return is_string($outcome) ? $outcome : null;
    }

    private static function open(object $row): Money
    {
        return Money::of(Row::text($row, 'returned_cost'))->minus(Money::of(Row::text($row, 'refunded_value')))->minus(Money::of(Row::text($row, 'replaced_value')))->minus(Money::of(Row::text($row, 'unrecovered_value')));
    }
}
