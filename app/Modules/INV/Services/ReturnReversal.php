<?php

namespace App\Modules\INV\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\INV\Data\StockFigures;
use App\Modules\OPS\Services\CorrectionCases;
use App\Modules\OPS\Services\Corrections;
use App\Modules\PRJ\Services\DemandPosting;
use App\Modules\PRJ\Services\Revalidation;
use App\Modules\PUR\Services\PurchasePosting;
use App\Modules\PUR\Services\ReturnOutcomes;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Money;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Balikkan (Tercatat Keliru)" (ROUTE-211; AX-36; CM-37; C-55; DATABASE §5.3, §5.7): a restoration, a sales return or a purchase
 * return recorded in error taken back by its linked reversal, never more than the original net of earlier reversals, never as a
 * loss. A lot restoration — a dispatch's reversal, an undelivered return, a client's return — and a drop-ship return to the
 * warehouse by a MOVEMENT_REVERSAL of their movement, the same lots, serials and condition still present, the consumption, its
 * cost and the net quantities re-established, IP-20's cut when usable supply would fall below the reservations; a purchase
 * return by a RETURN_ERROR_REVERSAL restoration bringing its units back at a rack, the case's returned cost lowered; a drop-ship
 * supplier return or confirmation reversal by the one exact DROPSHIP_ERROR_REVERSAL. Both records stay visible (BR-CR-02).
 */
final class ReturnReversal
{
    public const MOVEMENTS = ['RESTORATION', 'PURCHASE_RETURN', 'DROPSHIP_RETURN_RECEIPT'];

    public function __construct(
        private readonly InventoryPosting $inventory,
        private readonly CostPosting $cost,
        private readonly DemandPosting $demand,
        private readonly PurchasePosting $purchases,
        private readonly CorrectionCases $cases,
        private readonly Corrections $corrections,
        private readonly Revalidation $revalidation,
    ) {}

    /**
     * AX-36's set (STATIC): the original's consumptions with their cost locks and guards, the product, each place and its reversal
     * guard, the serials, the project lines' guards, the cases, a cut's reservations; a drop-ship original's purchase cascade.
     *
     * @param  list<array{restoration: ?int, consumption: ?int, qty: string, location: ?int, condition: ?string}>  $items
     * @param  array<int, string>  $cuts
     */
    public static function locks(?int $movementId, ?int $confirmationId, array $items, array $cuts): LockSet
    {
        $set = new LockSet;
        $restorations = array_values(array_filter(array_column($items, 'restoration'), is_int(...)));
        $consumptions = array_values(array_filter(array_column($items, 'consumption'), is_int(...)));

        foreach ($restorations === [] ? [] : DB::table('stock_restorations as r')->leftJoin('stock_consumptions as c', 'c.id', '=', 'r.stock_consumption_id')->leftJoin('dispatch_lines as d', 'd.id', '=', 'r.cited_dispatch_line_id')
            ->leftJoin('dropship_confirmation_lines as l', 'l.id', '=', 'r.dropship_confirmation_line_id')->whereIn('r.id', $restorations)
            ->get(['r.id', 'r.stock_consumption_id', 'r.dropship_confirmation_line_id', 'r.correction_case_id', 'r.product_id', 'd.project_line_id', 'l.project_line_id as dropship_project_line', 'l.purchase_line_id']) as $row) {
            $product = (int) Row::text($row, 'product_id');
            $set->add('stock_product_balances', ['product_id' => $product]);
            $consumption = Row::nullable($row, 'stock_consumption_id');

            if ($consumption !== null) {
                $set = $set->union(CostPosting::consumptionLocks((int) $consumption));
                $set->add('stock_consumption_balances', ['stock_consumption_id' => (int) $consumption]);
            }

            $dropship = Row::nullable($row, 'dropship_confirmation_line_id');

            if ($dropship !== null) {
                $purchase = DB::table('purchase_lines')->where('id', (int) Row::text($row, 'purchase_line_id'))->value('purchase_id');
                $set = $set->union(CostPosting::purchaseLocks(is_numeric($purchase) ? (int) $purchase : 0));
                $set->add('stock_consumption_balances', ['dropship_confirmation_line_id' => (int) $dropship]);
            }

            $line = Row::nullable($row, 'project_line_id') ?? Row::nullable($row, 'dropship_project_line');

            if ($line !== null) {
                $set = $set->union(InventoryPosting::lineLocks((int) $line));
            }

            $case = Row::nullable($row, 'correction_case_id');

            if ($case !== null) {
                $set = $set->union(CorrectionCases::locks((int) $case));
            }

            $set = $set->union(self::placeLocks((int) Row::text($row, 'id'), $product, $movementId));
        }

        foreach ($consumptions as $consumption) {
            $set = $set->union(CostPosting::consumptionLocks($consumption));
            $set->add('stock_consumption_balances', ['stock_consumption_id' => $consumption]);
            $row = DB::table('stock_consumptions')->where('id', $consumption)->first(['lot_id']);
            $product = DB::table('inventory_lots')->where('id', $row === null ? 0 : (int) Row::text($row, 'lot_id'))->value('product_id');
            $product = is_numeric($product) ? (int) $product : 0;
            $set->add('stock_product_balances', ['product_id' => $product]);

            foreach ($items as $item) {
                if ($item['consumption'] === $consumption && $item['location'] !== null && $item['condition'] !== null) {
                    $set->add('stock_scope_balances', ['product_id' => $product, 'location_id' => $item['location'], 'condition' => $item['condition']]);
                    $set->add('stock_lot_balances', ['lot_id' => $row === null ? 0 : (int) Row::text($row, 'lot_id'), 'location_id' => $item['location'], 'condition' => $item['condition']]);
                }
            }

            foreach (DB::table('stock_movement_entries as e')->join('serial_units as s', 's.id', '=', 'e.serial_unit_id')->where('e.stock_consumption_id', $consumption)->pluck('s.serial_key') as $key) {
                $set->add('serial_units', ['product_id' => $product, 'serial_key' => is_string($key) ? $key : '']);
            }
        }

        if ($cuts !== []) {
            $product = DB::table('stock_restorations')->whereIn('id', $restorations === [] ? [0] : $restorations)->value('product_id');
            $set = $set->union(InventoryPosting::cutLocks(is_numeric($product) ? (int) $product : 0, array_keys($cuts)));
        }

        return $set;
    }

    /**
     * ROUTE-211 on a movement: a lot restoration's or a drop-ship return's to the warehouse, or a purchase return's; the reversal's movement.
     *
     * @param  list<array{restoration: ?int, consumption: ?int, qty: string, location: ?int, condition: ?string}>  $items
     * @param  array<int, string>  $cuts  reservation => quantity
     */
    public function reverse(CommandContext $context, int $movementId, array $items, array $cuts, string $reason, string $businessDate, bool $prefilled): int
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $context->require(self::locks($movementId, null, $items, $cuts));
        $original = DB::table('stock_movements')->where('id', $movementId)->first(['id', 'public_id', 'movement_type', 'company_id', 'project_id', 'correction_case_id']);

        if ($original === null || ! in_array(Row::text($original, 'movement_type'), self::MOVEMENTS, true) || $items === []) {
            throw Refused::rejected('C-55', ['reason' => 'not_reversible']);
        }

        return Row::text($original, 'movement_type') === 'PURCHASE_RETURN'
            ? $this->purchaseReturn($context, $original, $items, $reason, $businessDate)
            : $this->restorations($context, $original, $items, $cuts, $reason, $businessDate);
    }

    /**
     * ROUTE-211 on a drop-ship confirmation: one supplier return or confirmation reversal of it undone once and exactly (CM-37; C-55); the undoing restoration.
     */
    public function dropshipError(CommandContext $context, int $confirmationId, int $restorationId, string $reason, string $businessDate, bool $prefilled): int
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $context->require(self::locks(null, $confirmationId, [['restoration' => $restorationId, 'consumption' => null, 'qty' => '0', 'location' => null, 'condition' => null]], []));
        $row = DB::table('stock_restorations as r')->join('dropship_confirmation_lines as l', 'l.id', '=', 'r.dropship_confirmation_line_id')->where('r.id', $restorationId)->where('l.dropship_confirmation_id', $confirmationId)
            ->first(['r.id', 'r.kind', 'r.qty_base', 'r.entered_unit_id', 'r.cited_unit_id', 'r.cited_ratio_to_base', 'r.after_delivery', 'r.correction_case_id', 'r.case_company_id', 'r.product_id', 'r.quantity_scale', 'r.source_company_id',
                'l.id as line', 'l.purchase_line_id', 'l.project_line_id', 'l.serial_unit_id', 'l.is_replacement', 'l.correction_case_id as line_case']);

        if ($row === null || ! in_array(Row::text($row, 'kind'), ['DROPSHIP_SUPPLIER_RETURN', 'DROPSHIP_CONFIRMATION_REVERSAL'], true)) {
            throw Refused::rejected('C-55', ['reason' => 'not_reversible']);
        }

        if (DB::table('stock_restorations')->where('reverses_restoration_id', $restorationId)->exists()) {
            throw Refused::rejected('C-55', ['reason' => 'reversed']);
        }

        $kind = Row::text($row, 'kind');
        $qty = Row::text($row, 'qty_base');
        $case = Row::nullable($row, 'correction_case_id');

        // The supplier's outcomes already settled against the return stand: the case's open figure must still hold the return's cost (C-46).
        if ($kind === 'DROPSHIP_SUPPLIER_RETURN' && $case !== null) {
            $carried = Money::of((string) DB::table('cost_attributions')->where('stock_restoration_id', $restorationId)->where('cost_class', 'SUPPLIER_RETURN')->sum('amount'));
            $figures = DB::table('correction_cases')->where('id', (int) $case)->first(['returned_cost', 'refunded_value', 'replaced_value', 'unrecovered_value']);
            $open = $figures === null ? Money::zero() : Money::of(Row::text($figures, 'returned_cost'))->minus(Money::of(Row::text($figures, 'refunded_value')))->minus(Money::of(Row::text($figures, 'replaced_value')))->minus(Money::of(Row::text($figures, 'unrecovered_value')));

            if ($carried->compareTo($open) > 0) {
                throw Refused::rejected('C-46', ['reason' => 'settled', 'open' => $open->toString()]);
            }
        }

        $now = $context->now();
        $undoing = (int) DB::table('stock_restorations')->insertGetId([
            'stock_movement_id' => null, 'stock_consumption_id' => null, 'dropship_confirmation_line_id' => (int) Row::text($row, 'line'), 'source_company_id' => (int) Row::text($row, 'source_company_id'),
            'product_id' => (int) Row::text($row, 'product_id'), 'quantity_scale' => (int) Row::text($row, 'quantity_scale'), 'cited_unit_id' => Row::nullable($row, 'cited_unit_id'), 'cited_ratio_to_base' => Row::nullable($row, 'cited_ratio_to_base'),
            'entered_qty' => $qty, 'entered_unit_id' => (int) Row::text($row, 'entered_unit_id'), 'ratio_to_base' => 1, 'qty_base' => $qty, 'kind' => 'DROPSHIP_ERROR_REVERSAL', 'after_delivery' => null,
            'reverses_restoration_id' => $restorationId, 'reverses_restoration_kind' => $kind, 'returned_condition' => null, 'correction_case_id' => $case, 'case_company_id' => Row::nullable($row, 'case_company_id'),
            'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId,
        ]);
        $posted = $this->cost->dropshipErrorReversed($context, $undoing, $businessDate);
        GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['dropship_confirmation_line_id' => (int) Row::text($row, 'line')], 'deltas' => ['restored_qty_base' => '-'.$qty]]]);
        $purchaseLine = (int) Row::text($row, 'purchase_line_id');
        $replacement = (bool) Row::text($row, 'is_replacement');

        if ($kind === 'DROPSHIP_CONFIRMATION_REVERSAL') {
            if ($replacement) {
                // A replacement reversed in error stands again: its line's replacement quantity and its case's replaced value back (C-59; C-46).
                $this->purchases->move($context, $purchaseLine, ['replacement_received_net_qty_base' => $qty]);
                $basis = Money::of((string) DB::table('cost_attributions')->where('stock_restoration_id', $undoing)->sum('amount'));
                $lineCase = Row::nullable($row, 'line_case');

                if ($lineCase !== null && ! $basis->isZero()) {
                    $this->cases->post($context, (int) $lineCase, ['replaced_value' => $basis->toString()], 'A replacement drop-ship confirmation reversed in error stands again (CM-37).');
                }
            } else {
                $this->purchases->move($context, $purchaseLine, ['dropship_confirmed_net_qty_base' => $qty, 'posted_goods_value' => $posted->goods->toString(), 'posted_noncreditable_tax_value' => $posted->tax->toString()]);
            }
        }

        $projectLine = (int) Row::text($row, 'project_line_id');
        $this->demand->dropship($context, [$projectLine => $qty], Row::nullable($row, 'after_delivery') === '1' || Row::nullable($row, 'after_delivery') === 'true' ? [$projectLine => $qty] : []);
        $serial = Row::nullable($row, 'serial_unit_id');

        if ($serial !== null && DB::table('serial_units')->where('id', (int) $serial)->whereIn('state', ['RETURNED_TO_SUPPLIER', 'NEVER_RECEIVED'])->update(['state' => 'DROPSHIPPED']) !== 1) {
            throw Refused::rejected('C-03', ['reason' => 'serial']);
        }

        $confirmation = DB::table('dropship_confirmations')->where('id', $confirmationId)->first(['public_id', 'company_id', 'project_id']) ?? throw Refused::rejected('C-14', ['reason' => 'confirmation']);
        $project = (int) Row::text($confirmation, 'project_id');
        $this->revalidation->revalidate($context, $project, Row::text($confirmation, 'public_id'));
        $casePublic = $case === null ? null : DB::table('correction_cases')->where('id', (int) $case)->value('public_id');
        $this->corrections->record($context, 'INV.ReturnReversed', AuthorityClass::AdmPlus, (int) Row::text($confirmation, 'company_id'), 'dropship_confirmation', $confirmationId, Row::text($confirmation, 'public_id'), AuditRelation::Reverses, 'dropship_confirmation',
            Row::text($confirmation, 'public_id'), $reason, AuditChange::created(['kind' => $kind, 'reversed' => $qty, 'reversal' => 'DROPSHIP_ERROR_REVERSAL']), is_string($casePublic) ? $casePublic : null, ['business_date' => $businessDate]);

        return $undoing;
    }

    /**
     * A lot restoration's or a drop-ship return's movement reversed: per restoration at most what of it still stands, its units
     * still at their place; the reversal guard per lot, place and condition (C-55).
     *
     * @param  list<array{restoration: ?int, consumption: ?int, qty: string, location: ?int, condition: ?string}>  $items
     * @param  array<int, string>  $cuts
     */
    private function restorations(CommandContext $context, object $original, array $items, array $cuts, string $reason, string $businessDate): int
    {
        $movementId = (int) Row::text($original, 'id');
        $warehouse = Row::text($original, 'movement_type') === 'DROPSHIP_RETURN_RECEIPT';
        $plan = [];
        $seen = [];
        $asked = [];
        $byCase = [];

        foreach ($items as $item) {
            $restoration = $item['restoration'];

            if ($restoration === null || isset($seen[$restoration])) {
                throw Refused::rejected('C-14', ['reason' => 'restoration']);
            }

            $seen[$restoration] = true;
            $row = DB::table('stock_restorations as r')->join('stock_movement_entries as e', fn (JoinClause $join): JoinClause => $join->on('e.stock_restoration_id', '=', 'r.id')->on('e.stock_movement_id', '=', 'r.stock_movement_id'))
                ->leftJoin('stock_consumptions as c', 'c.id', '=', 'r.stock_consumption_id')->leftJoin('dispatch_lines as d', 'd.id', '=', 'c.dispatch_line_id')
                ->leftJoin('dropship_confirmation_lines as l', 'l.id', '=', 'r.dropship_confirmation_line_id')->where('r.id', $restoration)->where('r.stock_movement_id', $movementId)
                ->first(['r.id', 'r.kind', 'r.qty_base', 'r.stock_consumption_id', 'r.dropship_confirmation_line_id', 'r.after_delivery', 'r.correction_case_id', 'e.lot_id', 'e.location_id', 'e.condition', 'e.serial_unit_id', 'e.product_id', 'e.quantity_scale',
                    'd.project_line_id', 'l.project_line_id as dropship_project_line', DB::raw("(select coalesce(sum(x.qty_delta_base), 0) from stock_movement_entries x where x.stock_restoration_id = r.id and x.movement_type = 'MOVEMENT_REVERSAL') as reversed")]);

            if ($row === null || ($warehouse ? Row::text($row, 'kind') !== 'DROPSHIP_RETURN' : ! in_array(Row::text($row, 'kind'), CostPosting::UNDOABLE, true))) {
                throw Refused::rejected('C-55', ['reason' => 'not_reversible']);
            }

            $qty = ReceivingRecords::base($item['qty'] === '' ? '0' : $item['qty'], 1, max(0, (int) Row::text($row, 'quantity_scale')));
            $standing = BigDecimal::of(Row::text($row, 'qty_base'))->plus(Row::text($row, 'reversed'));

            if (! $qty->isPositive() || $qty->isGreaterThan($standing)) {
                throw Refused::rejected('C-55', ['reason' => 'above_original', 'open' => StockFigures::plain((string) $standing)]);
            }

            $lot = (int) Row::text($row, 'lot_id');
            $location = (int) Row::text($row, 'location_id');
            $condition = Row::text($row, 'condition');
            $held = DB::table('stock_lot_balances as b')->where('b.lot_id', $lot)->where('b.location_id', $location)->where('b.condition', $condition)->value('b.qty');
            $serial = Row::nullable($row, 'serial_unit_id');
            $place = "{$lot}|{$location}|{$condition}";
            $asked[$place] = ($asked[$place] ?? BigDecimal::zero())->plus($qty);

            // The same lots, serials and condition still present, the command's items at one place summed (C-03).
            if (BigDecimal::of(is_numeric($held) ? (string) $held : '0')->isLessThan($asked[$place])
                || ($serial !== null && ! DB::table('serial_units')->where('id', (int) $serial)->where('state', 'IN_STOCK')->where('current_lot_id', $lot)->where('current_location_id', $location)->where('current_condition', $condition)->exists())) {
                throw Refused::rejected('C-03', ['reason' => 'not_present']);
            }

            $plan[] = ['row' => $row, 'qty' => $qty, 'standing' => $standing, 'lot' => $lot, 'location' => $location, 'condition' => $condition, 'serial' => $serial];
            $case = Row::nullable($row, 'correction_case_id');

            if (Row::text($row, 'kind') === 'SALES_RETURN' && $case !== null) {
                $byCase[(int) $case] = ($byCase[(int) $case] ?? BigDecimal::zero())->plus($qty);
            }
        }

        // A client's return whose units its case already lost (CM-11) is no longer back to take away: never more than the case still answers for (C-55).
        foreach ($byCase as $caseId => $wanted) {
            $back = self::answeredFor($caseId);

            if ($wanted->isGreaterThan($back)) {
                throw Refused::rejected('C-55', ['reason' => 'above_original', 'open' => StockFigures::plain((string) BigDecimal::max(BigDecimal::zero(), $back))]);
            }
        }

        $now = $context->now();
        $publicId = (string) Str::uuid7();
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'MOVEMENT_REVERSAL', 'business_date' => $businessDate, 'reason' => $reason, 'company_id' => Row::nullable($original, 'company_id'), 'project_id' => Row::nullable($original, 'project_id'),
            'reverses_movement_id' => $movementId, 'reverses_movement_type' => Row::text($original, 'movement_type'), 'command_id' => $context->request->commandId, 'command_line_no' => InventoryPosting::lineNo('stock_movements', $context), 'recorded_at' => $now,
            'recorded_by' => $context->request->actorUserId,
        ]);
        $entries = [];
        $usable = BigDecimal::zero();
        $guards = [];

        foreach ($plan as $step) {
            $row = $step['row'];
            $consumption = Row::nullable($row, 'stock_consumption_id');
            $entries[] = ['lot' => $step['lot'], 'product' => (int) Row::text($row, 'product_id'), 'scale' => (int) Row::text($row, 'quantity_scale'), 'location' => $step['location'], 'condition' => $step['condition'],
                'qty' => (string) $step['qty']->negated(), 'restoration' => (int) Row::text($row, 'id'), ...($consumption === null ? [] : ['consumption' => (int) $consumption]),
                ...($step['serial'] === null ? [] : ['serial' => (int) $step['serial']])];
            $usable = $step['condition'] === 'USABLE' ? $usable->plus($step['qty']) : $usable;
            $key = "{$step['lot']}|{$step['location']}|{$step['condition']}";
            $guards[$key] = ['lot' => $step['lot'], 'location' => $step['location'], 'condition' => $step['condition'], 'qty' => ($guards[$key]['qty'] ?? BigDecimal::zero())->plus($step['qty'])];
        }

        $product = $entries[0]['product'];
        $reserved = null;

        // IP-20: usable supply falling below the reservations commits only with the actor's cuts.
        if (($usable->isPositive() && InventoryPosting::shortfall($product, (string) $usable)->isPositive()) || $cuts !== []) {
            $reserved = $this->inventory->cut($context, $product, $cuts, (string) $usable, $movement, 'INV.ReverseRestorationOrReturn');
        }

        // Net dispatched rises again: the line's own reservations must leave it room, cut by the actor where they do not (C-05; C-06; IP-20).
        $lines = [];

        foreach ($plan as $step) {
            $line = Row::nullable($step['row'], 'project_line_id');

            if (! $warehouse && $line !== null) {
                $lines[(int) $line] = ($lines[(int) $line] ?? BigDecimal::zero())->plus($step['qty']);
            }
        }

        foreach ($lines as $projectLine => $raised) {
            $balance = DB::table('project_line_balances')->where('project_line_id', $projectLine)->first(['demand_qty_base', 'reserved_qty_base', 'dispatched_net_qty_base', 'cancelled_qty_base']);
            $excess = $balance === null ? BigDecimal::zero() : BigDecimal::of(Row::text($balance, 'reserved_qty_base'))->plus(Row::text($balance, 'dispatched_net_qty_base'))->plus(Row::text($balance, 'cancelled_qty_base'))
                ->plus($raised)->minus(Row::text($balance, 'demand_qty_base'));

            if ($excess->isPositive()) {
                throw Refused::rejected('C-06', ['reason' => 'line', 'shortfall' => StockFigures::plain((string) $excess), 'covered' => '0']);
            }
        }

        $ids = $this->inventory->ledger($context, $movement, 'MOVEMENT_REVERSAL', $entries, $reserved);

        foreach ($guards as $guard) {
            $identity = ['reversed_movement_id' => $movementId, 'lot_id' => $guard['lot'], 'location_id' => $guard['location'], 'condition' => $guard['condition']];
            $initial = DB::table('stock_movement_entries')->where('stock_movement_id', $movementId)->where('lot_id', $guard['lot'])->where('location_id', $guard['location'])->where('condition', $guard['condition'])->sum('qty_delta_base');
            $context->connection->table('stock_reversal_balances')->insertOrIgnore([...$identity, 'original_qty_base' => (string) $initial, 'recorded_at' => $now]);
            GuardUpdate::apply($context->connection, 'stock_reversal_balances', [['key' => $identity, 'deltas' => ['reversed_qty_base' => (string) $guard['qty']]]]);
        }

        $projects = [];
        $cases = [];

        foreach ($plan as $index => $step) {
            $row = $step['row'];
            $qty = (string) $step['qty'];
            $consumption = Row::nullable($row, 'stock_consumption_id');

            if ($warehouse) {
                // A drop-ship return to the warehouse taken back: its lot's basis gives the HPP back to the confirmation line (CM-37; DATABASE §6.3).
                $this->cost->reverseBasis($context, $step['lot'], $qty, $businessDate);
                $line = (int) Row::text($row, 'dropship_confirmation_line_id');
                GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['dropship_confirmation_line_id' => $line], 'deltas' => ['restored_qty_base' => '-'.$qty]]]);
                $projectLine = (int) Row::text($row, 'dropship_project_line');
                $after = Row::nullable($row, 'after_delivery') === '1' || Row::nullable($row, 'after_delivery') === 'true';
                $this->demand->dropship($context, [$projectLine => $qty], $after ? [$projectLine => $qty] : []);
                $state = 'DROPSHIPPED';
            } else {
                $this->cost->unrestore($context, (int) Row::text($row, 'id'), $qty, (string) $step['standing'], $businessDate);
                GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['stock_consumption_id' => (int) $consumption], 'deltas' => ['restored_qty_base' => '-'.$qty]]]);
                $projectLine = (int) Row::text($row, 'project_line_id');
                $this->demand->dispatched($context, $projectLine, $qty);

                // A client's return reversed: the goods are the client's again (PX-08; CM-37).
                if (Row::text($row, 'kind') === 'SALES_RETURN') {
                    $this->demand->delivered($context, [$projectLine => $qty]);
                }

                $state = 'DISPATCHED';
            }

            if ($step['serial'] !== null && DB::table('serial_units')->where('id', (int) $step['serial'])->where('state', 'IN_STOCK')
                ->update(['state' => $state, 'current_lot_id' => null, 'current_location_id' => null, 'current_condition' => null, 'last_entry_id' => $ids[$index]]) !== 1) {
                throw Refused::rejected('C-03', ['reason' => 'serial']);
            }

            $bearer = $consumption === null ? null : DB::table('stock_consumptions')->where('id', (int) $consumption)->value('bearer_project_id');
            $projects[is_numeric($bearer) ? (int) $bearer : (int) (Row::nullable($original, 'project_id') ?? 0)] = true;
            $case = Row::nullable($row, 'correction_case_id');

            if ($case !== null) {
                $cases[(int) $case] = true;
            }
        }

        foreach (array_keys($projects) as $project) {
            if ($project > 0) {
                $this->revalidation->revalidate($context, $project, $publicId);
            }
        }

        $case = array_key_first($cases);
        $casePublic = $case === null ? null : DB::table('correction_cases')->where('id', $case)->value('public_id');
        $this->corrections->record($context, 'INV.ReturnReversed', AuthorityClass::AdmPlus, Row::nullable($original, 'company_id') === null ? null : (int) Row::text($original, 'company_id'), 'stock_movement', $movement, $publicId,
            AuditRelation::Reverses, 'stock_movement', Row::text($original, 'public_id'), $reason, AuditChange::created(['reversed' => array_map(fn (array $step): string => (string) $step['qty'], $plan), 'movement' => Row::text($original, 'movement_type')]),
            is_string($casePublic) ? $casePublic : null, ['business_date' => $businessDate, 'reserved' => $reserved]);

        return $movement;
    }

    /**
     * A purchase return recorded in error taken back: per returned consumption at most what of it is still out, its units back
     * at the rack and condition named — a serial in the condition it left —, the SUPPLIER_RETURN contra'd and the case's returned
     * cost, and so its refund expectation, lowered (CM-37; C-46).
     *
     * @param  list<array{restoration: ?int, consumption: ?int, qty: string, location: ?int, condition: ?string}>  $items
     */
    private function purchaseReturn(CommandContext $context, object $original, array $items, string $reason, string $businessDate): int
    {
        $movementId = (int) Row::text($original, 'id');
        $now = $context->now();
        $publicId = (string) Str::uuid7();
        $plan = [];
        $taken = [];

        foreach ($items as $item) {
            $consumption = $item['consumption'];
            $row = $consumption === null ? null : DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->join('inventory_lots as i', 'i.id', '=', 'c.lot_id')->join('products as p', 'p.id', '=', 'i.product_id')
                ->where('c.id', $consumption)->where('c.stock_movement_id', $movementId)->where('c.kind', 'PURCHASE_RETURN')
                ->first(['c.id', 'c.lot_id', 'c.source_company_id', 'c.correction_case_id', 'c.case_company_id', 'i.product_id', 'i.quantity_scale', 'p.base_unit_id', DB::raw('b.qty_base - b.restored_qty_base as open')]);

            if ($row === null || $item['location'] === null || ! in_array($item['condition'], ['USABLE', 'UNUSABLE'], true)) {
                throw Refused::rejected('C-14', ['reason' => 'consumption']);
            }

            $qty = ReceivingRecords::base($item['qty'] === '' ? '0' : $item['qty'], 1, max(0, (int) Row::text($row, 'quantity_scale')));
            $open = BigDecimal::of(Row::text($row, 'open'))->minus($taken[(int) $consumption] ?? BigDecimal::zero());

            if (! $qty->isPositive() || $qty->isGreaterThan($open)) {
                throw Refused::rejected('C-55', ['reason' => 'above_original', 'open' => StockFigures::plain((string) $open)]);
            }

            // A serialized return comes back one serial at a time (C-02; BR-INV-09).
            if (! $qty->isEqualTo(1) && DB::table('stock_movement_entries')->where('stock_consumption_id', (int) $consumption)->whereNotNull('serial_unit_id')->exists()) {
                throw Refused::rejected('C-02', ['reason' => 'serial_return']);
            }

            $taken[(int) $consumption] = ($taken[(int) $consumption] ?? BigDecimal::zero())->plus($qty);
            $plan[] = ['row' => $row, 'qty' => $qty, 'location' => (int) $item['location'], 'condition' => (string) $item['condition']];
        }

        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'RESTORATION', 'business_date' => $businessDate, 'reason' => $reason, 'company_id' => Row::nullable($original, 'company_id'), 'project_id' => Row::nullable($original, 'project_id'),
            'correction_case_id' => Row::nullable($original, 'correction_case_id'), 'command_id' => $context->request->commandId, 'command_line_no' => InventoryPosting::lineNo('stock_movements', $context), 'recorded_at' => $now,
            'recorded_by' => $context->request->actorUserId,
        ]);
        $entries = [];
        $number = 0;
        $case = Row::nullable($original, 'correction_case_id');
        $openBefore = $case === null ? null : self::caseOpen((int) $case);

        foreach ($plan as $step) {
            $row = $step['row'];
            $qty = (string) $step['qty'];
            $restoration = (int) DB::table('stock_restorations')->insertGetId([
                'stock_movement_id' => $movement, 'stock_consumption_id' => (int) Row::text($row, 'id'), 'source_company_id' => (int) Row::text($row, 'source_company_id'), 'product_id' => (int) Row::text($row, 'product_id'),
                'quantity_scale' => (int) Row::text($row, 'quantity_scale'), 'entered_qty' => $qty, 'entered_unit_id' => (int) Row::text($row, 'base_unit_id'), 'ratio_to_base' => 1, 'qty_base' => $qty,
                'kind' => 'RETURN_ERROR_REVERSAL', 'after_delivery' => null, 'returned_condition' => $step['condition'], 'correction_case_id' => Row::nullable($row, 'correction_case_id'), 'case_company_id' => Row::nullable($row, 'case_company_id'),
                'command_id' => $context->request->commandId, 'command_line_no' => ++$number, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId,
            ]);
            $this->cost->restore($context, $restoration, $businessDate);
            GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['stock_consumption_id' => (int) Row::text($row, 'id')], 'deltas' => ['restored_qty_base' => $qty]]]);
            $serial = DB::table('serial_units as s')->join('stock_movement_entries as e', 'e.id', '=', 's.last_entry_id')->where('e.stock_consumption_id', (int) Row::text($row, 'id'))->where('s.state', 'RETURNED_TO_SUPPLIER')->orderBy('s.id')
                ->whereNotIn('s.id', array_values(array_filter(array_map(fn (array $entry): ?int => $entry['serial'] ?? null, $entries), is_int(...))) ?: [0])->value('s.id');
            $entries[] = ['lot' => (int) Row::text($row, 'lot_id'), 'product' => (int) Row::text($row, 'product_id'), 'scale' => (int) Row::text($row, 'quantity_scale'), 'location' => $step['location'], 'condition' => $step['condition'],
                'qty' => $qty, 'restoration' => $restoration, ...(is_numeric($serial) && $step['qty']->isEqualTo(1) ? ['serial' => (int) $serial] : [])];
        }

        if ($case !== null && $openBefore !== null) {
            // The supplier's outcomes already settled against the return stand: the case's open figure must still hold the cost taken back (C-46).
            if (self::caseOpen((int) $case)->isNegative()) {
                throw Refused::rejected('C-46', ['reason' => 'settled', 'open' => $openBefore->toString()]);
            }

            // A replacement already declared stays within what is still returned (C-29).
            $returned = BigDecimal::of((string) DB::table('stock_consumptions')->where('correction_case_id', (int) $case)->where('kind', 'PURCHASE_RETURN')->sum('qty_base'))
                ->minus((string) DB::table('stock_restorations')->where('correction_case_id', (int) $case)->where('kind', 'RETURN_ERROR_REVERSAL')->sum('qty_base'));

            if (ReturnOutcomes::declared((int) $case)->isGreaterThan($returned)) {
                throw Refused::rejected('C-29', ['reason' => 'replaced', 'remainder' => StockFigures::plain((string) BigDecimal::max(BigDecimal::zero(), $returned))]);
            }
        }

        $ids = $this->inventory->ledger($context, $movement, 'RESTORATION', $entries);

        foreach ($entries as $index => $entry) {
            if (isset($entry['serial']) && DB::table('serial_units')->where('id', $entry['serial'])->where('state', 'RETURNED_TO_SUPPLIER')
                ->update(['state' => 'IN_STOCK', 'current_lot_id' => $entry['lot'], 'current_location_id' => $entry['location'], 'current_condition' => $entry['condition'], 'last_entry_id' => $ids[$index]]) !== 1) {
                throw Refused::rejected('C-02', ['reason' => 'serial_return']);
            }
        }

        $casePublic = $case === null ? null : DB::table('correction_cases')->where('id', (int) $case)->value('public_id');
        $this->corrections->record($context, 'INV.ReturnReversed', AuthorityClass::AdmPlus, Row::nullable($original, 'company_id') === null ? null : (int) Row::text($original, 'company_id'), 'stock_movement', $movement, $publicId,
            AuditRelation::Reverses, 'stock_movement', Row::text($original, 'public_id'), $reason, AuditChange::created(['reversed' => array_map(fn (array $step): string => (string) $step['qty'], $plan), 'movement' => 'PURCHASE_RETURN']),
            is_string($casePublic) ? $casePublic : null, ['business_date' => $businessDate]);

        return $movement;
    }

    /** A purchase-return case's open figure (C-46): returned less refunded, replaced and unrecovered. */
    private static function caseOpen(int $caseId): Money
    {
        $figures = DB::table('correction_cases')->where('id', $caseId)->first(['returned_cost', 'refunded_value', 'replaced_value', 'unrecovered_value']);

        return $figures === null ? Money::zero() : Money::of(Row::text($figures, 'returned_cost'))->minus(Money::of(Row::text($figures, 'refunded_value')))->minus(Money::of(Row::text($figures, 'replaced_value')))->minus(Money::of(Row::text($figures, 'unrecovered_value')));
    }

    /** What a client's return case still answers for (CM-11): its units back, net of reversals, less what it lost. */
    private static function answeredFor(int $caseId): BigDecimal
    {
        $back = DB::table('stock_restorations')->where('correction_case_id', $caseId)->where('kind', 'SALES_RETURN')->sum('qty_base');
        $reversed = DB::table('stock_movement_entries as e')->join('stock_restorations as r', 'r.id', '=', 'e.stock_restoration_id')->where('r.correction_case_id', $caseId)->where('r.kind', 'SALES_RETURN')->where('e.movement_type', 'MOVEMENT_REVERSAL')->sum('e.qty_delta_base');
        $lost = DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->where('c.correction_case_id', $caseId)->where('c.kind', 'LOSS')->sum(DB::raw('b.qty_base - b.restored_qty_base'));

        return BigDecimal::of(is_numeric($back) ? (string) $back : '0')->plus(is_numeric($reversed) ? (string) $reversed : '0')->minus(is_numeric($lost) ? (string) $lost : '0');
    }

    /** The place a restoration's units sit at and its reversal guard (LK-14; LK-15; LK-16); its serial (LK-17). */
    private static function placeLocks(int $restorationId, int $product, ?int $movementId): LockSet
    {
        $set = new LockSet;

        foreach (DB::table('stock_movement_entries as e')->leftJoin('serial_units as s', 's.id', '=', 'e.serial_unit_id')->where('e.stock_restoration_id', $restorationId)->whereNotNull('e.lot_id')
            ->get(['e.stock_movement_id', 'e.lot_id', 'e.location_id', 'e.condition', 's.serial_key']) as $entry) {
            $place = ['lot_id' => (int) Row::text($entry, 'lot_id'), 'location_id' => (int) Row::text($entry, 'location_id'), 'condition' => Row::text($entry, 'condition')];
            $set->add('stock_lot_balances', $place);
            $set->add('stock_scope_balances', ['product_id' => $product, 'location_id' => $place['location_id'], 'condition' => $place['condition']]);
            $set->add('stock_reversal_balances', ['reversed_movement_id' => $movementId ?? (int) Row::text($entry, 'stock_movement_id'), ...$place]);
            $key = Row::nullable($entry, 'serial_key');

            if ($key !== null) {
                $set->add('serial_units', ['product_id' => $product, 'serial_key' => $key]);
            }
        }

        return $set;
    }
}
