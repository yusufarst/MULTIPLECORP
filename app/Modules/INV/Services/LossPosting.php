<?php

namespace App\Modules\INV\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\INV\Data\LossCause;
use App\Modules\INV\Data\StockPicks;
use App\Modules\OPS\Services\CorrectionCases;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\DuplicateWarning;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Money;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SF-LOSS, the one loss path (AX-06; AX-30; BR-INV-13; DIR-024 D-3; DATABASE §6.1, §6.4): every lost unit recognized once, in the action that removes it, at its lot's current net cost through CST, on its causal project (L-38) or else its owning company, a bearer of another company than the lot's carrying one allocation with its reason and authority (L-44; XL-1); a reclassification a pair of rows and never a second draw, the dispatch's allocation kept (CM-07; TASK-045); a supplier return's unrecovered share a cost-only loss (CM-15; CM-16; TASK-044). The exact reversal is MovementReversal's capped restorations (CM-34), and a reclassification's its own inverse here. A loss is a residual command: it never revalidates a completed project (L-11).
 */
final class LossPosting
{
    /** The commands' kinds of a decrease (WF-INV-06): a disposal or shrinkage is an adjustment, anything else a loss. */
    public const KINDS = ['DISPOSAL' => 'ADJUSTMENT_DECREASE', 'SHRINKAGE' => 'ADJUSTMENT_DECREASE', 'OTHER' => 'LOSS'];

    public function __construct(
        private readonly InventoryPosting $inventory,
        private readonly CostPosting $cost,
        private readonly AuditRecorder $audit,
    ) {}

    /** AX-06's and AX-30's set: the places, the serials, the cut and evidence; each lot's cost guard, the causal projects, the case. */
    public function locks(StockPicks $entry, ?int $case): LockSet
    {
        $set = StockPlaces::locks($entry->productId, $entry->picks, $entry->cuts, $entry->evidence);

        foreach ($entry->picks as $pick) {
            $set->add('lot_cost_balances', ['lot_id' => $pick['lot']]);
        }

        foreach (self::parts($entry->picks, $case) as $part) {
            if ($part['project'] !== null) {
                $set->add('projects', ['id' => $part['project']]);
            }

            if ($part['case'] !== null) {
                $set = $set->union(CorrectionCases::locks($part['case']));
            }
        }

        return $case === null ? $set : $set->union(CorrectionCases::locks($case));
    }

    /** ROUTE-194: the stock-out, the cut and the loss as one action; the movement. */
    public function record(CommandContext $context, StockPicks $entry, string $kind, ?int $case, ?string $allocationReason): int
    {
        BusinessDateRule::check($entry->businessDate, $entry->prefilled);
        $context->require($this->locks($entry, $case));
        $type = self::KINDS[$kind] ?? throw Refused::rejected('C-07', ['reason' => 'kind']);
        $scale = StockPlaces::ensurePolicy($entry);

        foreach ($entry->picks as $pick) {
            // WF-INV-06: what is disposed of is unusable; usable units leave as shrinkage or another loss.
            if ($kind === 'DISPOSAL' && $pick['condition'] !== 'UNUSABLE') {
                throw Refused::rejected('C-03', ['reason' => 'disposal_usable']);
            }

            if ($pick['serial'] !== null && DB::table('serial_units')->where('id', $pick['serial'])->where('state', 'LOST')->exists()) {
                throw Refused::rejected('C-24', ['reason' => 'serial']);
            }
        }

        $reduced = StockPlaces::ensureHeld($entry->picks, $entry->productId, $scale, $entry->serializedSeen);

        if ($entry->unidentified) {
            StockPlaces::ensureConfirmable($entry->picks, $entry->productId);
        }

        if ($case !== null) {
            self::ensureCase($case, $entry);
        }

        $parts = self::parts($entry->picks, $case);
        $this->ensureBearers($context, $parts, $allocationReason, $entry->productId);
        $cases = array_values(array_unique(array_filter(array_map(fn (array $part): ?int => $part['case'], $parts), fn (?int $id): bool => $id !== null)));
        $matches = StockPlaces::likelyDuplicates($type, $entry->picks, $entry->businessDate, count($cases) === 1 ? $cases[0] : null);
        DuplicateWarning::check('L-49', $matches, $entry->acknowledged, $entry->duplicateReason);
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $now = $context->now();
        $bearers = array_values(array_unique(array_map(fn (array $part): string => $part['company'].'|'.($part['project'] ?? ''), $parts)));
        $company = count($bearers) === 1 ? $parts[0]['company'] : null;
        $publicId = (string) Str::uuid7();
        // A movement of the pool, as its evidence is (C-58; PJ-08): the bearer and the project are its consumptions' and their cost rows'.
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => $type, 'business_date' => $entry->businessDate, 'reason' => $entry->reason,
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $reserved = null;

        if ($reduced->isPositive() && (InventoryPosting::shortfall($entry->productId, (string) $reduced)->isPositive() || $entry->cuts !== [])) {
            $reserved = $this->inventory->cut($context, $entry->productId, $entry->cuts, (string) $reduced, $movement, 'INV.AdjustStock');
        }

        $consumptions = [];
        $rows = [];
        $number = 0;

        // One consumption for each lost unit or place, so its exact reversal restores each to where it left (CM-34).
        foreach ($parts as $part) {
            $crossed = $part['company'] !== $part['source'];
            $consumption = (int) DB::table('stock_consumptions')->insertGetId([
                'stock_movement_id' => $movement, 'lot_id' => $part['pick']['lot'], 'source_company_id' => $part['source'], 'product_id' => $entry->productId, 'quantity_scale' => $scale, 'qty_base' => (string) $part['qty'], 'kind' => 'LOSS',
                'bearer_company_id' => $part['company'], 'bearer_project_id' => $part['project'], 'allocation_reason' => $crossed ? $allocationReason : null, 'allocation_authorized_by' => $crossed ? $actor : null,
                'correction_case_id' => $part['case'], 'case_company_id' => $part['case_company'],
                'command_id' => $commandId, 'command_line_no' => ++$number, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
            $context->connection->table('stock_consumption_balances')->insert(['stock_consumption_id' => $consumption, 'qty_base' => (string) $part['qty']]);
            $consumptions[] = $consumption;
            $rows[] = ['lot' => $part['pick']['lot'], 'product' => $entry->productId, 'scale' => $scale, 'location' => $part['pick']['location'], 'condition' => $part['pick']['condition'], 'qty' => (string) $part['qty']->negated(), 'consumption' => $consumption, ...($part['pick']['serial'] === null ? [] : ['serial' => $part['pick']['serial']])];
        }

        $ids = $this->inventory->ledger($context, $movement, $type, $rows, $reserved);

        foreach ($rows as $index => $row) {
            // A unit is lost once (C-24): conditional on what it was just read to be.
            if (isset($row['serial']) && DB::table('serial_units')->where('id', $row['serial'])->where('state', 'IN_STOCK')->update(['state' => 'LOST', 'current_lot_id' => null, 'current_location_id' => null, 'current_condition' => null, 'last_entry_id' => $ids[$index]]) !== 1) {
                throw Refused::rejected('C-24', ['reason' => 'serial']);
            }
        }

        foreach ($consumptions as $consumption) {
            $this->cost->draw($context, $consumption, $entry->businessDate);
        }

        StockPlaces::linkEvidence($context, $entry->evidence, $movement);
        $action = $type === 'LOSS' ? 'INV.LossRecognized' : 'INV.StockAdjusted';
        $allocated = array_filter($parts, fn (array $part): bool => $part['company'] !== $part['source']) !== [];
        $this->audit->record(
            action: $matches === [] ? $action : $action.'DuplicateConfirmed',
            authorityClass: AuthorityClass::AdmPlus,
            actorUserId: $actor,
            companyId: $company,
            entityType: 'stock_movement',
            entityId: $movement,
            entityPublicId: $publicId,
            reason: $entry->reason,
            commandId: $commandId,
            change: AuditChange::created(['kind' => $kind, 'quantity' => (string) array_reduce($parts, fn (BigDecimal $sum, array $part): BigDecimal => $sum->plus($part['qty']), BigDecimal::zero()), 'evidence' => count($entry->evidence), 'cuts' => count($entry->cuts), ...($allocated ? ['allocated' => true, 'allocation_reason' => $allocationReason] : []), ...($matches === [] ? [] : ['matches' => $matches, 'duplicate_reason' => $entry->duplicateReason])]),
            metadata: ['business_date' => $entry->businessDate],
        );

        return $movement;
    }

    /**
     * A shipment's loss in transit, as TASK-045's delivery closure calls it (CM-07; annex 15): the pair HPP −a / LOSS +a on the dispatch's consumption, no stock movement, its allocation kept; never above the open quantity (C-27). The caller holds `CostPosting::consumptionLocks` and the consumption's guard.
     */
    public function reclassify(CommandContext $context, int $consumptionId, string $qty, string $businessDate): void
    {
        $open = self::open($consumptionId);

        if (! BigDecimal::of($qty)->isPositive() || BigDecimal::of($qty)->isGreaterThan($open)) {
            throw Refused::rejected('C-27', ['reason' => 'above_open', 'open' => (string) $open]);
        }

        $this->cost->reclassify($context, $consumptionId, $qty, $businessDate);
        GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['stock_consumption_id' => $consumptionId], 'deltas' => ['reclassified_qty_base' => $qty]]]);
    }

    /**
     * Goods found after a loss in transit (CM-07; CM-34): the exact inverse of a reclassification, the HPP re-attributed and the loss contra'd, no stock movement; never above what was reclassified (C-27).
     */
    public function reverseReclassification(CommandContext $context, int $consumptionId, string $qty, string $businessDate): void
    {
        $row = DB::table('stock_consumption_balances as b')->where('stock_consumption_id', $consumptionId)->value('reclassified_qty_base');

        if (! is_string($row) || ! BigDecimal::of($qty)->isPositive() || BigDecimal::of($qty)->isGreaterThan($row)) {
            throw Refused::rejected('C-27', ['reason' => 'above_reclassified', 'open' => is_string($row) ? $row : '0']);
        }

        $this->cost->reverseReclassification($context, $consumptionId, $qty, $row, $businessDate);
        GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['stock_consumption_id' => $consumptionId], 'deltas' => ['reclassified_qty_base' => '-'.$qty]]]);
    }

    /**
     * A supplier return's unrecovered share, as TASK-044's outcome calls it (CM-15; CM-16; NX-16): a cost-only loss on the case's causal project, otherwise the lot's owning company, once; an allocation with its reason and authority when the project's company differs from the lot's (L-44), otherwise refused and routed (AZ-08).
     */
    public function unrecovered(CommandContext $context, int $consumptionId, Money $amount, ?string $allocationReason, string $businessDate): void
    {
        $row = DB::table('stock_consumptions as c')->join('correction_cases as k', 'k.id', '=', 'c.correction_case_id')->leftJoin('projects as p', 'p.id', '=', 'k.causal_project_id')->where('c.id', $consumptionId)
            ->first(['c.source_company_id', 'c.product_id', 'k.causal_project_id', 'p.company_id as project_company']) ?? throw Refused::rejected('C-14', ['reason' => 'case']);
        $net = DB::table('cost_attributions')->where('stock_consumption_id', $consumptionId)->where('cost_class', 'SUPPLIER_RETURN')->sum('amount');

        // Recognized once: never more than the return still carries (C-24).
        if (BigDecimal::of($amount->toString())->isGreaterThan((string) $net)) {
            throw Refused::rejected('C-24', ['reason' => 'above_return']);
        }

        $source = (int) Row::text($row, 'source_company_id');
        $project = Row::nullable($row, 'causal_project_id');
        $company = $project === null ? $source : (int) Row::text($row, 'project_company');
        $this->ensureBearers($context, [['company' => $company, 'source' => $source]], $allocationReason, (int) Row::text($row, 'product_id'));
        $this->cost->settleReturn($context, $consumptionId, $amount, $company, $project === null ? null : (int) $project, $company === $source ? null : $allocationReason, $businessDate);
    }

    /**
     * L-38 for each pick's quantity, derived and never taken from the payload (CS-04): a damaged unit of a lot under an open case is that case's, the actor naming the case only where several are open on the lot; what a case or a receipt's damage answers for spent across the picks, so a quantity on several racks or serials is charged once.
     *
     * @param  list<array{lot: int, location: int, condition: string, qty: string, serial: ?int}>  $picks
     * @return list<array{company: int, project: ?int, case: ?int, case_company: ?int, qty: BigDecimal, source: int, pick: array{lot: int, location: int, condition: string, qty: string, serial: ?int}}>
     */
    public static function parts(array $picks, ?int $case): array
    {
        $spent = [];
        $parts = [];

        foreach ($picks as $pick) {
            $qty = BigDecimal::of(is_numeric($pick['qty']) ? $pick['qty'] : '0');
            $company = DB::table('inventory_lots')->where('id', $pick['lot'])->value('source_company_id');
            $source = is_numeric($company) ? (int) $company : 0;
            $kase = self::caseOf($pick['lot'], $pick['condition'], $case);

            if ($kase !== null && $kase['open'] !== null) {
                $kase['open'] = $kase['open']->minus($spent['case'.$kase['id']] ?? BigDecimal::zero());
            }

            $receipt = $pick['condition'] === 'UNUSABLE' ? self::receiptDamage($pick['lot'], $pick['serial']) : null;

            if ($receipt !== null && $pick['serial'] === null) {
                $receipt['open'] = $receipt['open']->minus($spent['lot'.$pick['lot']] ?? BigDecimal::zero());
            }

            foreach (LossCause::of($source, $kase, $receipt, $pick['condition'], $qty) as $part) {
                $parts[] = [...$part, 'source' => $source, 'pick' => $pick];

                if ($part['case'] !== null) {
                    $spent['case'.$part['case']] = ($spent['case'.$part['case']] ?? BigDecimal::zero())->plus($part['qty']);
                } elseif ($part['project'] !== null) {
                    $spent['lot'.$pick['lot']] = ($spent['lot'.$pick['lot']] ?? BigDecimal::zero())->plus($part['qty']);
                }
            }
        }

        return $parts;
    }

    /**
     * The case a pick's units answer to: the one named, or the one open on the lot for its unusable units (CM-11); several open and none named asks the actor.
     *
     * @return array{id: int, company: int, project: ?int, project_company: ?int, open: ?BigDecimal}|null
     */
    private static function caseOf(int $lot, string $condition, ?int $named): ?array
    {
        if ($named === null && $condition !== 'UNUSABLE') {
            return null;
        }

        $rows = DB::table('correction_cases as k')->leftJoin('projects as p', 'p.id', '=', 'k.causal_project_id')->where('k.state', 'OPEN')
            ->when($named !== null, fn (Builder $where) => $where->where('k.id', $named), fn (Builder $where) => $where->where('k.source_lot_id', $lot)->whereIn('k.case_kind', ['SALES_RETURN', 'DAMAGED_UNIT']))
            ->orderBy('k.id')->get(['k.id', 'k.company_id', 'k.causal_project_id', 'p.company_id as project_company'])->all();
        $open = [];

        foreach ($rows as $row) {
            $capacity = self::capacity((int) Row::text($row, 'id'));

            if ($capacity === null || $capacity->isPositive()) {
                $open[] = ['id' => (int) Row::text($row, 'id'), 'company' => (int) Row::text($row, 'company_id'), 'project' => Row::nullable($row, 'causal_project_id') === null ? null : (int) Row::text($row, 'causal_project_id'), 'project_company' => Row::nullable($row, 'project_company') === null ? null : (int) Row::text($row, 'project_company'), 'open' => $capacity];
            }
        }

        if (count($open) > 1) {
            throw Refused::rejected('C-14', ['reason' => 'case_required']);
        }

        return $open[0] ?? null;
    }

    /** What a case still answers for (CM-11): what it brought back, less what it lost; a case that brought nothing back has one outcome. Null while that outcome is open. */
    private static function capacity(int $case): ?BigDecimal
    {
        $lost = BigDecimal::of((string) DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->where('c.correction_case_id', $case)->where('c.kind', 'LOSS')->sum(DB::raw('b.qty_base - b.restored_qty_base')));
        $back = DB::table('stock_restorations')->where('correction_case_id', $case)->where('kind', 'SALES_RETURN');

        if (! $back->exists()) {
            return $lost->isPositive() ? BigDecimal::zero() : null;
        }

        return BigDecimal::of((string) $back->sum('qty_base'))->minus($lost);
    }

    /**
     * The damage at the receipt of a project-linked line not yet lost on that project, nor repaired by a re-inspection (L-38): a serial by its own history, the unit itself damaged at the receipt and not repaired since.
     *
     * @return array{project: int, company: int, open: BigDecimal}|null
     */
    private static function receiptDamage(int $lot, ?int $serial = null): ?array
    {
        $row = DB::table('inventory_lots as i')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->join('purchase_lines as pl', 'pl.id', '=', 'r.purchase_line_id')->join('project_lines as l', 'l.id', '=', 'pl.project_line_id')->where('i.id', $lot)->where('r.damaged_qty_base', '>', 0)
            ->first(['r.damaged_qty_base', 'l.project_id', 'l.company_id']);

        if ($row === null) {
            return null;
        }

        $project = (int) Row::text($row, 'project_id');
        // A re-inspection's usable units, net of its linked reversal (C-55): undone, they are damage again.
        $repaired = DB::table('stock_movement_entries as e')->join('stock_movements as m', 'm.id', '=', 'e.stock_movement_id')->where('e.lot_id', $lot)->where('e.condition', 'USABLE')
            ->when($serial !== null, fn (Builder $where) => $where->where('e.serial_unit_id', $serial))
            ->where(fn (Builder $where) => $where->where(fn (Builder $change) => $change->where('m.movement_type', 'CONDITION_CHANGE')->where('e.qty_delta_base', '>', 0))
                ->orWhere(fn (Builder $undone) => $undone->where('m.movement_type', 'MOVEMENT_REVERSAL')->where('m.reverses_movement_type', 'CONDITION_CHANGE')->where('e.qty_delta_base', '<', 0)))
            ->sum('e.qty_delta_base');

        if ($serial !== null) {
            $damaged = DB::table('stock_movement_entries')->where('serial_unit_id', $serial)->where('lot_id', $lot)->where('movement_type', 'RECEIPT')->where('condition', 'UNUSABLE')->exists();

            return ['project' => $project, 'company' => (int) Row::text($row, 'company_id'), 'open' => $damaged && ! BigDecimal::of((string) $repaired)->isPositive() ? BigDecimal::one() : BigDecimal::zero()];
        }

        $lost = DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->where('c.lot_id', $lot)->where('c.kind', 'LOSS')->where('c.bearer_project_id', $project)->sum(DB::raw('b.qty_base - b.restored_qty_base'));

        return ['project' => $project, 'company' => (int) Row::text($row, 'company_id'), 'open' => BigDecimal::of(Row::text($row, 'damaged_qty_base'))->minus((string) $lost)->minus((string) $repaired)];
    }

    /** A named case: open, of the lot picked, its units unusable, never more than it answers for (CM-11; C-24). */
    private static function ensureCase(int $case, StockPicks $entry): void
    {
        $row = DB::table('correction_cases')->where('id', $case)->first(['state', 'source_lot_id']);

        if ($row === null || Row::text($row, 'state') !== 'OPEN') {
            throw Refused::rejected('C-14', ['reason' => 'case']);
        }

        $total = BigDecimal::zero();
        $source = Row::nullable($row, 'source_lot_id');

        foreach ($entry->picks as $pick) {
            if (($source !== null && $pick['lot'] !== (int) $source) || $pick['condition'] !== 'UNUSABLE') {
                throw Refused::rejected('C-14', ['reason' => 'case_lot']);
            }

            $total = $total->plus($pick['qty']);
        }

        $capacity = self::capacity($case);

        if ($capacity !== null && $total->isGreaterThan($capacity)) {
            throw Refused::rejected('C-24', ['reason' => 'case_outcome']);
        }
    }

    /**
     * AZ-08; XL-1: each bearer company in the actor's scope with the command's authority, another company than the lot's also with `stock.allocate_intercompany` and the reason; otherwise refused and routed before anything is written, one audit event per affected company committed with the refusal (SQ-23).
     *
     * @param  list<array<string, mixed>>  $parts  each with its int `company` and `source`
     */
    private function ensureBearers(CommandContext $context, array $parts, ?string $reason, int $product): void
    {
        $authority = $context->authority();
        $capability = $context->request->capability;
        $outside = [];
        $explained = $reason !== null && trim($reason) !== '';

        foreach ($parts as $part) {
            $company = is_int($part['company'] ?? null) ? $part['company'] : 0;
            $source = is_int($part['source'] ?? null) ? $part['source'] : 0;
            $allowed = $authority !== null && $authority->scope->includes($company) && $authority->allows($capability, $company)
                && ($company === $source || ($explained && $authority->allows('stock.allocate_intercompany', $company)));

            if (! $allowed) {
                $outside[$company] = true;
                $outside[$source] = true;
            }
        }

        if ($outside === []) {
            return;
        }

        $actor = $context->request->actorUserId;
        $command = $context->request->commandId;
        $type = $context->request->commandType;

        throw Refused::rejected('AZ-08', ['routed' => true, 'reason' => 'bearer'], function (ConnectionInterface $connection) use ($outside, $actor, $command, $type, $product): void {
            foreach (array_keys($outside) as $company) {
                $this->audit->record(action: 'INV.LossRouted', authorityClass: AuthorityClass::System, actorUserId: $actor, companyId: $company, entityType: 'product', entityId: $product, commandId: $command, metadata: ['routed_action' => $type], connection: $connection);
            }
        });
    }

    private static function open(int $consumptionId): BigDecimal
    {
        $row = DB::table('stock_consumption_balances as b')->where('stock_consumption_id', $consumptionId)->first(['qty_base', 'restored_qty_base', 'reclassified_qty_base', 'substituted_qty_base']) ?? throw Refused::rejected('C-14', ['reason' => 'consumption']);

        return BigDecimal::of(Row::text($row, 'qty_base'))->minus(Row::text($row, 'restored_qty_base'))->minus(Row::text($row, 'reclassified_qty_base'))->minus(Row::text($row, 'substituted_qty_base'));
    }
}
