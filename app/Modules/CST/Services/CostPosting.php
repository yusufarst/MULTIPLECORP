<?php

namespace App\Modules\CST\Services;

use App\Modules\CST\Data\ReceiptPosted;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\OPS\Services\CorrectionCases;
use App\Modules\PRJ\Services\Revalidation;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Costing\ChargeAllocator;
use App\Support\Costing\CostSplitter;
use App\Support\Costing\ProportionalContra;
use App\Support\GuardUpdate;
use App\Support\Money;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * CST's posting service, the sole normal writer of the lot cost basis, the lot-cost guard, the one managerial cost
 * ledger, the charge and charge-share guards and the system-created expenses (ARCHITECTURE §7; DATABASE §4.8, §6).
 * Its callers are the commands of the purchasing, inventory and expense Tasks; each calls one entry point per fact,
 * inside its transaction, holding the locks the entry point's lock set names (CONCURRENCY_IDEMPOTENCY §5; LK-21–LK-23;
 * LK-29) — a data-dependent set that grew since the caller took its locks restarts the action (§5 step 5).
 *
 * Every split is CostSplitter's, ChargeAllocator's or ProportionalContra's (BR-FIN-13); every row is a new signed row
 * naming what it reverses or corrects, never an update of an old one (DATABASE §13); each guard moves once per entry
 * point by its net delta (GU-01). A value the caller's own guard holds — the purchase line's posted goods and tax, PUR's
 * — is returned as {@see ReceiptPosted} for the caller to post. Nothing here writes Cash-Out (BR-FIN-01).
 *
 * A cost sits in a lot or on a direct source — a drop-ship confirmation line or a non-stock purchase line — and a cost
 * that moves reaches every holder of the units it belongs to (§6.3 step 3): a lot's consumptions by their units still
 * out, a drop-ship line's returns by theirs — the return lot, or the supplier-return case —, and a case's replacements by
 * the replaced units.
 *
 * Callers record their own facts first: the receiving lot and its quantity on the line, the consumption and its
 * movement, the restoration (before its quantity moves the consumption's balance), the reversal movement, the closed
 * quantity, the adjustment and the corrected value, the charge and its shares.
 *
 * @phpstan-type Affected array{cases: array<int, Money>, replaced: array<int, Money>, projects: array<int, Money>, returns: array<string, Money>}
 * @phpstan-type Target array{kind: string, id: int, qty: string, company?: int, project?: int, line?: int}
 */
final class CostPosting
{
    /** The cost class a consumption draws, by its kind (DATABASE §6.1). */
    private const CLASS_OF = ['DISPATCH' => 'HPP', 'LOSS' => 'LOSS', 'UNATTRIBUTED_LOSS' => 'LOSS', 'PURCHASE_RETURN' => 'SUPPLIER_RETURN'];

    /** The class a restoration reverses and the cause it is recorded with, by its kind (DATABASE §6.2; L-31). */
    private const RESTORATION = [
        'DISPATCH_REVERSAL' => ['HPP', 'RESTORATION_CONTRA'],
        'UNDELIVERED_RETURN' => ['HPP', 'RESTORATION_CONTRA'],
        'SALES_RETURN' => ['HPP', 'RESTORATION_CONTRA'],
        'SUBSTITUTION' => ['HPP', 'SUBSTITUTION'],
        'LOSS_REVERSAL' => ['LOSS', 'LOSS_REVERSAL'],
    ];

    /** The consumption kinds each restoration reverses (DATABASE §6.2; L-31). */
    private const RESTORES = [
        'DISPATCH_REVERSAL' => ['DISPATCH'],
        'UNDELIVERED_RETURN' => ['DISPATCH'],
        'SALES_RETURN' => ['DISPATCH'],
        'SUBSTITUTION' => ['DISPATCH'],
        'LOSS_REVERSAL' => ['LOSS', 'UNATTRIBUTED_LOSS'],
    ];

    /** The basis kinds a lot other than a normal receipt carries (DATABASE §6.3), with the acquisition each needs. */
    private const BASIS_KINDS = ['OPENING_COST', 'SURPLUS_FLAGGED_BASIS', 'DROPSHIP_RETURN_BASIS', 'REPLACEMENT_BASIS'];

    /** A drop-ship line's restorations that move its units away — to a return lot, to the supplier (DATABASE §5.7). */
    private const DROPSHIP_AWAY = ['DROPSHIP_RETURN', 'DROPSHIP_SUPPLIER_RETURN'];

    /** The order a split over a source's cost components takes, the last absorbing the residual (§6.1). */
    private const COMPONENT_ORDER = ['GOODS' => 0, 'NONCREDITABLE_TAX' => 1, 'BASIS' => 2, 'CHARGE' => 3];

    private const REOPENED = 'A cost posting changed its returned cost (DATABASE §6.3 step 4; AX-34).';

    public function __construct(
        private readonly EvidenceGuard $evidence,
        private readonly CorrectionCases $cases,
        private readonly Revalidation $revalidation,
    ) {}

    /*
     * Lock sets — data-dependent, read before the caller locks and again under the locks (§5).
     */

    /**
     * The lock set of a cascade over lots (AX-29; AX-32; AX-34): LK-23 for the lots, LK-01 for the bearer projects and
     * LK-10 — with the cases' own projects — for the cases of the consumptions drawn from them, every one a dependent the
     * cascade may re-attribute, and the lots and confirmations those cases' replacements created (§6.3 step 3), followed
     * until no holder is new.
     *
     * @param  list<int>  $lotIds
     */
    public static function lotLocks(array $lotIds): LockSet
    {
        return self::cascadeLocks($lotIds, [], []);
    }

    /**
     * The lock set of one consumption's own posting (AX-03; AX-10; AX-11; AX-30 — STATIC): LK-23 for its lot's cost guard,
     * LK-01 for its bearer project and, for a purchase return, LK-10 for its case with the case's projects; never the
     * lot's other dependents.
     */
    public static function consumptionLocks(int $consumptionId): LockSet
    {
        $consumption = DB::table('stock_consumptions')->where('id', $consumptionId)->first(['lot_id', 'kind', 'bearer_project_id', 'correction_case_id'])
            ?? throw new InvalidArgumentException("No consumption {$consumptionId}.");
        $set = (new LockSet)->add('lot_cost_balances', ['lot_id' => (int) Row::text($consumption, 'lot_id')]);
        $project = Row::nullable($consumption, 'bearer_project_id');
        $case = Row::nullable($consumption, 'correction_case_id');

        if ($project !== null) {
            $set->add('projects', ['id' => (int) $project]);
        }

        return $case !== null && Row::text($consumption, 'kind') === 'PURCHASE_RETURN' ? $set->union(CorrectionCases::locks((int) $case)) : $set;
    }

    /**
     * LK-21 for the purchase, its lines and their guards, LK-22 for its charges and their shares, LK-01 for its project,
     * its drop-ship confirmations' projects and its non-stock lines' bearer projects, and the cascade of the lots of its
     * lines and its drop-ship lines' returns (§6.3).
     */
    public static function purchaseLocks(int $purchaseId): LockSet
    {
        $lines = array_values(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, DB::table('purchase_lines')->where('purchase_id', $purchaseId)->orderBy('id')->pluck('id')->all()));
        $dropship = $lines === [] ? [] : array_values(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0,
            DB::table('dropship_confirmation_lines')->whereIn('purchase_line_id', $lines)->pluck('id')->all()));
        $set = self::cascadeLocks(self::lotsOfLines($lines), [], $dropship);
        $set->add('purchases', ['id' => $purchaseId]);
        $project = DB::table('purchases')->where('id', $purchaseId)->value('project_id');

        if (is_numeric($project)) {
            $set->add('projects', ['id' => (int) $project]);
        }

        foreach ($lines as $line) {
            $set->add('purchase_lines', ['id' => $line]);
        }

        foreach (DB::table('purchase_line_balances as b')->whereIn('b.purchase_line_id', $lines)->pluck('b.purchase_line_id') as $line) {
            $set->add('purchase_line_balances', ['purchase_line_id' => is_numeric($line) ? (int) $line : 0]);
        }

        foreach (DB::table('purchase_charges as c')->join('purchase_charge_balances as b', 'b.purchase_charge_id', '=', 'c.id')->where('c.purchase_id', $purchaseId)->pluck('c.id') as $charge) {
            $set->add('purchase_charge_balances', ['purchase_charge_id' => is_numeric($charge) ? (int) $charge : 0]);
        }

        foreach (DB::table('purchase_charge_share_balances as b')->whereIn('purchase_line_id', $lines)->get(['purchase_charge_id', 'purchase_line_id']) as $share) {
            $set->add('purchase_charge_share_balances', ['purchase_charge_id' => (int) Row::text($share, 'purchase_charge_id'), 'purchase_line_id' => (int) Row::text($share, 'purchase_line_id')]);
        }

        // A non-stock line's cost sits on the project it was attributed to at the purchase (L-18).
        foreach ($lines === [] ? [] : DB::table('cost_attributions')->whereIn('purchase_line_id', $lines)->where('cause', 'INITIAL')->whereNotNull('bearer_project_id')->distinct()->pluck('bearer_project_id') as $bearer) {
            $set->add('projects', ['id' => is_numeric($bearer) ? (int) $bearer : 0]);
        }

        return $set;
    }

    /** {@see self::purchaseLocks()} for the charge's purchase, with LK-29 for its source bill. */
    public static function chargeLocks(int $chargeId): LockSet
    {
        $charge = DB::table('purchase_charges')->where('id', $chargeId)->first(['purchase_id', 'source_bill_evidence_id']);

        if (! is_object($charge)) {
            throw new InvalidArgumentException("No purchase charge {$chargeId}.");
        }

        return self::purchaseLocks((int) Row::text($charge, 'purchase_id'))->union(EvidenceGuard::locks((int) Row::text($charge, 'source_bill_evidence_id')));
    }

    /*
     * Acquisition.
     */

    /**
     * A receipt's lot cost basis (DATABASE §6.1; C-57): GOODS and NONCREDITABLE_TAX at the line's cumulative targets for
     * its received quantity after this event less what is posted, and CHARGE_SHARE for each of the line's current
     * charge shares, capped by what is still pending on it — the line's received quantity already counting this lot.
     */
    public function receipt(CommandContext $context, int $lotId, string $businessDate): ReceiptPosted
    {
        $lot = self::lot($lotId);
        $receiving = DB::table('receiving_lines')->where('id', Row::nullable($lot, 'receiving_line_id') ?? 0)->first(['purchase_line_id', 'receipt_kind']);

        if (Row::text($lot, 'acquisition_kind') !== 'RECEIPT' || ! is_object($receiving) || Row::text($receiving, 'receipt_kind') === 'REPLACEMENT') {
            throw new InvalidArgumentException("Lot {$lotId} is no normal receipt's lot: its basis is posted by basis().");
        }

        $lineId = (int) Row::text($receiving, 'purchase_line_id');
        $context->require(self::purchaseLocks(self::purchaseOf($lineId)));
        $line = self::lineBalance($lineId);
        $received = self::received($line);
        $ordered = Row::text($line, 'ordered_qty_base');
        $goods = CostSplitter::target(Money::of(Row::text($line, 'goods_value')), $received, $ordered)->minus(Money::of(Row::text($line, 'posted_goods_value')));
        $tax = CostSplitter::target(Money::of(Row::text($line, 'noncreditable_tax_value')), $received, $ordered)->minus(Money::of(Row::text($line, 'posted_noncreditable_tax_value')));
        $ledger = new CostLedger($context);
        $base = ['lot_id' => $lotId, 'source_company_id' => (int) Row::text($lot, 'source_company_id'), 'purchase_line_id' => $lineId, 'business_date' => $businessDate];
        $ledger->entry([...$base, 'entry_kind' => 'GOODS', 'cost_component' => 'GOODS', 'amount' => $goods->toString()]);
        $total = $goods;

        if (! $tax->isZero()) {
            $ledger->entry([...$base, 'entry_kind' => 'NONCREDITABLE_TAX', 'cost_component' => 'NONCREDITABLE_TAX', 'amount' => $tax->toString()]);
            $total = $total->plus($tax);
        }

        foreach (self::sharesOf($lineId) as $share) {
            $amount = ChargeAllocator::posting($share['share'], $received, $ordered, $share['posted'], $share['respread_out'], $share['expensed_out']);

            if ($amount->isZero()) {
                continue;
            }

            $ledger->entry([...$base, 'entry_kind' => 'CHARGE_SHARE', 'cost_component' => 'CHARGE', 'amount' => $amount->toString(),
                'purchase_charge_id' => $share['charge'], 'purchase_charge_line_share_id' => $share['id'], 'allocation_version' => $share['version']]);
            $this->allocated($ledger, $share['charge'], $lineId, $amount);
            $total = $total->plus($amount);
        }

        GuardUpdate::bear($context->connection, 'lot_cost_balances', ['lot_id' => $lotId], [
            'remaining_qty_base' => Row::text($lot, 'acquired_qty_base'), 'remaining_cost' => $total->toString(), 'recorded_at' => $context->now(),
        ]);
        $ledger->flush();

        return new ReceiptPosted($goods, $tax);
    }

    /**
     * The basis of a lot that is no normal receipt (DATABASE §6.3; BR-INV-12): OPENING_COST from the signed opening
     * data, SURPLUS_FLAGGED_BASIS, DROPSHIP_RETURN_BASIS equal to its HPP contra, REPLACEMENT_BASIS from its return case
     * — exactly one per lot, the database matching it to the lot's acquisition.
     *
     * @param  array<string, int>  $references  `import_row_id`, `stock_restoration_id` or `correction_case_id`
     */
    public function basis(CommandContext $context, int $lotId, string $kind, Money $amount, string $businessDate, array $references = []): void
    {
        if (! in_array($kind, self::BASIS_KINDS, true) || $amount->isNegative()) {
            throw new InvalidArgumentException("{$kind} {$amount} is no lot basis.");
        }

        $lot = self::lot($lotId);
        $ledger = new CostLedger($context);
        $ledger->entry([...array_intersect_key($references, array_flip(['import_row_id', 'stock_restoration_id', 'correction_case_id'])),
            'lot_id' => $lotId, 'source_company_id' => (int) Row::text($lot, 'source_company_id'), 'entry_kind' => $kind, 'cost_component' => 'BASIS',
            'amount' => $amount->toString(), 'business_date' => $businessDate]);
        GuardUpdate::bear($context->connection, 'lot_cost_balances', ['lot_id' => $lotId], [
            'remaining_qty_base' => Row::text($lot, 'acquired_qty_base'), 'remaining_cost' => $amount->toString(), 'recorded_at' => $context->now(),
        ]);
    }

    /**
     * Units removed from an opening, surplus or drop-ship return lot as never acquired (DATABASE §5.3's MOVEMENT_REVERSAL;
     * §6.3; BR-INV-12): their share of the lot's remaining cost — all of it when they zero the lot —, the lot's guard
     * lowered by both; the cost removed is returned. An opening or surplus lot's share leaves as one BASIS_REVERSAL
     * entry. A drop-ship return lot's goes back to its confirmation line's HPP, re-establishing the restoration it came
     * from (reestablished()). A receipt lot, a replacement's among them, is reversed by reverseReceipt(). The caller has
     * recorded the removal's movement — for a drop-ship return, the reversal of its return receipt — and gives the
     * confirmation line its units back on its balance.
     *
     * @param  array<string, int>  $references  `import_row_id`, `stock_restoration_id` or `correction_case_id`
     */
    public function reverseBasis(CommandContext $context, int $lotId, string $qty, string $businessDate, array $references = []): Money
    {
        $lot = self::lot($lotId);

        if (! in_array(Row::text($lot, 'acquisition_kind'), ['OPENING', 'SURPLUS', 'DROPSHIP_RETURN'], true)) {
            throw new InvalidArgumentException("Lot {$lotId} is a receipt's lot: its units are reversed by reverseReceipt().");
        }

        $dropship = Row::nullable($lot, 'dropship_confirmation_line_id');
        $context->require($dropship === null ? (new LockSet)->add('lot_cost_balances', ['lot_id' => $lotId])
            : self::purchaseLocks(self::purchaseOf((int) Row::text(self::dropshipLine((int) $dropship), 'purchase_line_id'))));
        $ledger = new CostLedger($context);
        [$remainingQty, $remainingCost] = $this->lotBalance($ledger, $lotId);
        $removed = CostSplitter::draw($remainingCost, $qty, $remainingQty);
        $ledger->move('lot_cost_balances', ['lot_id' => $lotId], ['remaining_qty_base' => '-'.$qty, 'remaining_cost' => $removed->negated()->toString()]);
        $entry = [...array_intersect_key($references, array_flip(['import_row_id', 'stock_restoration_id', 'correction_case_id'])),
            'lot_id' => $lotId, 'source_company_id' => (int) Row::text($lot, 'source_company_id'), 'business_date' => $businessDate];

        if (! $removed->isZero()) {
            $dropship === null
                ? $ledger->entry([...$entry, 'entry_kind' => 'BASIS_REVERSAL', 'cost_component' => 'BASIS', 'amount' => $removed->negated()->toString()])
                : $this->reestablished($ledger, $lot, (int) $dropship, $removed, $entry, $businessDate);
        }

        $ledger->flush();

        return $removed;
    }

    /**
     * A drop-ship return lot's removed cost back on its confirmation line (§5.3): split over what the lot holds by its net
     * amounts — its basis as the restoration's own contra parts, then each part a later correction or charge brought to
     * it, a contra'd version's negative part among them — the last absorbing the residual; the basis parts leave as one
     * BASIS_REVERSAL and return as rows of the restoration, so the lot and its line hold between them what the
     * restoration moved; each other part leaves as a COST_CORRECTION of its own component and returns as the same part.
     *
     * @param  array<string, int|string>  $entry
     */
    private function reestablished(CostLedger $ledger, object $lot, int $dropshipLineId, Money $removed, array $entry, string $businessDate): void
    {
        $restoration = (int) Row::text($lot, 'restoration_id');
        $dropship = self::dropshipLine($dropshipLineId);
        $line = ['dropship_confirmation_line_id' => $dropshipLineId, 'cost_class' => 'HPP', 'bearer_company_id' => (int) Row::text($dropship, 'company_id'),
            'bearer_project_id' => (int) Row::text($dropship, 'project_id'), 'business_date' => $businessDate];
        $held = [];

        foreach (self::restorationGroups($restoration) as $group) {
            $held[] = [...$group, 'basis' => true];
        }

        foreach (DB::table('lot_cost_entries')->where('lot_id', (int) Row::text($lot, 'id'))->where('cost_component', '<>', 'BASIS')
            ->groupBy('cost_component', 'purchase_line_id', 'purchase_charge_id', 'purchase_charge_line_share_id', 'respread_from_purchase_line_id', 'allocation_version')
            ->selectRaw('cost_component, purchase_line_id, purchase_charge_id, purchase_charge_line_share_id, respread_from_purchase_line_id, allocation_version, sum(amount) as net')
            ->orderBy('cost_component')->get() as $row) {
            $net = Money::of(Row::text($row, 'net'));

            if (! $net->isZero()) {
                $held[] = ['component' => Row::text($row, 'cost_component'), 'net' => $net, 'basis' => false, 'references' => array_map('intval', array_filter([
                    'purchase_line_id' => Row::nullable($row, 'purchase_line_id'), 'purchase_charge_id' => Row::nullable($row, 'purchase_charge_id'),
                    'purchase_charge_line_share_id' => Row::nullable($row, 'purchase_charge_line_share_id'),
                    'respread_from_purchase_line_id' => Row::nullable($row, 'respread_from_purchase_line_id'), 'allocation_version' => Row::nullable($row, 'allocation_version'),
                ], fn (?string $value): bool => $value !== null))];
            }
        }

        $whole = Money::sum(Money::zero(), ...array_column($held, 'net'));
        $given = Money::zero();
        $basis = Money::zero();
        $last = array_key_last($held);

        foreach ($held as $index => $part) {
            // A signed split: a contra'd version's negative part leaves with its share too.
            $amount = $index === $last ? $removed->minus($given) : $removed->share($part['net']->toString(), $whole->toString());
            $given = $given->plus($amount);

            if ($amount->isZero()) {
                continue;
            }

            $direct = [...$line, ...array_diff_key($part['references'], ['purchase_line_id' => true]), 'cost_component' => $part['component'], 'amount' => $amount->toString()];

            if ($part['basis']) {
                $basis = $basis->plus($amount);
                $ledger->attribution([...$direct, 'cause' => 'RESTORATION_CONTRA', 'stock_restoration_id' => $restoration]);

                continue;
            }

            $ledger->entry([...$entry, ...$part['references'], 'entry_kind' => 'COST_CORRECTION', 'cost_component' => $part['component'], 'amount' => $amount->negated()->toString()]);
            $ledger->attribution([...$direct, 'cause' => 'COST_CORRECTION']);
        }

        if (! $basis->isZero()) {
            $ledger->entry([...$entry, 'entry_kind' => 'BASIS_REVERSAL', 'cost_component' => 'BASIS', 'amount' => $basis->negated()->toString()]);
        }
    }

    /**
     * What a drop-ship return's restoration still holds in its lot's basis, by the parts its contra took off the
     * confirmation line — every row of the restoration net, a re-established part given back —, in component order.
     *
     * @return list<array{component: string, net: Money, references: array<string, int>}>
     */
    private static function restorationGroups(int $restorationId): array
    {
        $groups = [];

        foreach (DB::table('cost_attributions')->where('stock_restoration_id', $restorationId)->where('cause', 'RESTORATION_CONTRA')->whereNotNull('dropship_confirmation_line_id')
            ->groupBy('cost_component', 'purchase_charge_id', 'purchase_charge_line_share_id', 'respread_from_purchase_line_id', 'allocation_version')
            ->selectRaw('cost_component, purchase_charge_id, purchase_charge_line_share_id, respread_from_purchase_line_id, allocation_version, -sum(amount) as net')->get() as $row) {
            $net = Money::of(Row::text($row, 'net'));

            if ($net->isPositive()) {
                $groups[] = ['component' => Row::text($row, 'cost_component'), 'net' => $net, 'references' => array_map('intval', array_filter([
                    'purchase_charge_id' => Row::nullable($row, 'purchase_charge_id'), 'purchase_charge_line_share_id' => Row::nullable($row, 'purchase_charge_line_share_id'),
                    'respread_from_purchase_line_id' => Row::nullable($row, 'respread_from_purchase_line_id'), 'allocation_version' => Row::nullable($row, 'allocation_version'),
                ], fn (?string $value): bool => $value !== null))];
            }
        }

        usort($groups, fn (array $a, array $b): int => [self::COMPONENT_ORDER[$a['component']], $a['references']['purchase_charge_id'] ?? 0, $a['references']['purchase_charge_line_share_id'] ?? 0]
            <=> [self::COMPONENT_ORDER[$b['component']], $b['references']['purchase_charge_id'] ?? 0, $b['references']['purchase_charge_line_share_id'] ?? 0]);

        return $groups;
    }

    /**
     * A drop-ship confirmation line's actual cost attributed directly (DATABASE §6.1; C-25): GOODS, NONCREDITABLE_TAX
     * and each current charge share at the line's cumulative targets, INITIAL rows on the confirmation's project. A
     * replacement confirmation carries its case's basis instead (replacementConfirmed()).
     */
    public function dropshipConfirmed(CommandContext $context, int $dropshipLineId, string $businessDate): ReceiptPosted
    {
        $dropship = self::dropshipLine($dropshipLineId);

        if (Row::text($dropship, 'is_replacement') === '1' || Row::text($dropship, 'is_replacement') === 'true') {
            throw new InvalidArgumentException("Drop-ship line {$dropshipLineId} replaces a supplier return: it carries its case's basis (replacementConfirmed()).");
        }

        $lineId = (int) Row::text($dropship, 'purchase_line_id');
        $context->require(self::purchaseLocks(self::purchaseOf($lineId)));

        return $this->direct($context, $lineId, ['dropship_confirmation_line_id' => $dropshipLineId], (int) Row::text($dropship, 'company_id'), (int) Row::text($dropship, 'project_id'), $businessDate);
    }

    /**
     * A replacement drop-ship confirmation line (DATABASE §5.7; PX-03): the basis its supplier-return case gives it, a
     * direct BASIS attribution on the confirmation's project naming the case — never the purchase line's goods, tax or
     * charges. The case's replaced value is the outcome command's (TASK-044).
     */
    public function replacementConfirmed(CommandContext $context, int $dropshipLineId, Money $basis, string $businessDate): void
    {
        $dropship = self::dropshipLine($dropshipLineId);
        $case = Row::nullable($dropship, 'correction_case_id');

        if ($case === null || ! $basis->isPositive()) {
            throw new InvalidArgumentException("Drop-ship line {$dropshipLineId} is no replacement with a basis to carry.");
        }

        $project = (int) Row::text($dropship, 'project_id');
        $company = (int) Row::text($dropship, 'company_id');
        $context->require((new LockSet)->add('projects', ['id' => $project]));
        (new CostLedger($context))->attribution([
            'dropship_confirmation_line_id' => $dropshipLineId, 'cost_class' => 'HPP', 'cause' => 'INITIAL', 'cost_component' => 'BASIS', 'amount' => $basis->toString(),
            'bearer_company_id' => $company, 'bearer_project_id' => $project, 'correction_case_id' => (int) $case, 'case_company_id' => $company, 'business_date' => $businessDate,
        ]);
    }

    /** A non-stock purchase line's actual cost attributed directly to its project, once (DATABASE §6.1; C-25; L-18). */
    public function nonStockLine(CommandContext $context, int $purchaseLineId, int $bearerProjectId, string $businessDate): ReceiptPosted
    {
        $line = DB::table('purchase_lines')->where('id', $purchaseLineId)->first(['kind', 'company_id', 'purchase_id']);

        if (! is_object($line) || Row::text($line, 'kind') !== 'NONSTOCK') {
            throw new InvalidArgumentException("Purchase line {$purchaseLineId} is no non-stock line.");
        }

        $context->require(self::purchaseLocks((int) Row::text($line, 'purchase_id'))->add('projects', ['id' => $bearerProjectId]));

        return $this->direct($context, $purchaseLineId, ['purchase_line_id' => $purchaseLineId], (int) Row::text($line, 'company_id'), $bearerProjectId, $businessDate);
    }

    /**
     * A cancelled purchase's non-stock line as never bought (AX-12; L-18): each component of its direct HPP contra'd where it sits, on the project it was attributed to; its goods and tax returned for PUR to lower the line's posted values,
     * its charge parts leaving the guards they came from and expensed, no line of a cancelled purchase being a target (DATABASE §6.1). The caller has moved the purchase to CANCELLED first.
     */
    public function nonStockCancelled(CommandContext $context, int $purchaseLineId, string $businessDate): ReceiptPosted
    {
        $context->require(self::purchaseLocks(self::purchaseOf($purchaseLineId)));
        $initial = DB::table('cost_attributions')->where('purchase_line_id', $purchaseLineId)->where('cause', 'INITIAL')->orderBy('id')->first(['id', 'bearer_company_id', 'bearer_project_id']);

        if (! is_object($initial)) {
            return ReceiptPosted::none();
        }

        $project = (int) Row::text($initial, 'bearer_project_id');
        $base = ['purchase_line_id' => $purchaseLineId, 'cost_class' => 'HPP', 'cause' => 'COST_CORRECTION', 'bearer_company_id' => (int) Row::text($initial, 'bearer_company_id'), 'bearer_project_id' => $project,
            'reverses_attribution_id' => (int) Row::text($initial, 'id'), 'business_date' => $businessDate];
        $ledger = new CostLedger($context);
        $posted = ReceiptPosted::none();
        $respreads = [];
        $total = Money::zero();

        foreach (self::directGroups('purchase_line_id', $purchaseLineId, 'HPP') as $group) {
            $part = $group['net'];
            $total = $total->plus($part);
            $ledger->attribution([...$base, ...$group['references'], 'cost_component' => $group['component'], 'amount' => $part->negated()->toString()]);
            $posted = $posted->plus(match ($group['component']) {
                'GOODS' => new ReceiptPosted($part->negated(), Money::zero()),
                'NONCREDITABLE_TAX' => new ReceiptPosted(Money::zero(), $part->negated()),
                default => ReceiptPosted::none(),
            });

            if ($group['component'] === 'CHARGE') {
                $charge = $group['references']['purchase_charge_id'];
                $own = isset($group['references']['purchase_charge_line_share_id']);
                $source = $own ? $purchaseLineId : $group['references']['respread_from_purchase_line_id'];
                $ledger->move('purchase_charge_share_balances', ['purchase_charge_id' => $charge, 'purchase_line_id' => $source], [$own ? 'posted_amount' : 'respread_out_amount' => $part->negated()->toString()]);
                $ledger->move('purchase_charge_balances', ['purchase_charge_id' => $charge], ['allocated_amount' => $part->negated()->toString()]);
                $respreads[] = [$charge, $source, $group['references']['allocation_version'], $part];
            }
        }

        foreach ($respreads as [$charge, $source, $version, $part]) {
            $this->respread($context, $ledger, $charge, $source, $version, $part, $businessDate);
        }

        $ledger->flush();
        $this->corrected($context, self::moved(self::none(), 'projects', (string) $project, $total), "purchase_line:{$purchaseLineId}:cancelled");

        return $posted;
    }

    /**
     * The system-created expense of a settlement (BR-FIN-09; BR-FIN-16; C-23; DATABASE §11.4): a fee's, or a tax's under a rule that says so, with its money-fact guard, in a category no manual
     * entry may use. The evidence's expensed amount is the settling command's, which moves it where the expense is new and leaves it where the expense only moves with its settlement.
     */
    public function settlementExpense(CommandContext $context, int $settlementId, string $kind, ?int $projectId, string $businessDate): int
    {
        $settlement = DB::table('deduction_settlements')->where('id', $settlementId)->first(['company_id', 'amount', 'evidence_document_id', 'evidence_type']);

        if (! is_object($settlement) || ! in_array($kind, ['FEE_DEDUCTION', 'TAX_TREATMENT'], true)) {
            throw new InvalidArgumentException("Settlement {$settlementId} has no {$kind} expense.");
        }

        $ledger = new CostLedger($context);
        $id = $ledger->expense([
            'company_id' => (int) Row::text($settlement, 'company_id'), 'project_id' => $projectId, 'kind' => $kind, 'category' => $kind, 'amount' => Row::text($settlement, 'amount'), 'business_date' => $businessDate,
            'deduction_settlement_id' => $settlementId, 'evidence_document_id' => (int) Row::text($settlement, 'evidence_document_id'), 'evidence_type' => Row::text($settlement, 'evidence_type'),
        ]);
        GuardUpdate::bear($context->connection, 'money_fact_balances', ['expense_id' => $id], ['company_id' => (int) Row::text($settlement, 'company_id'), 'fact_kind' => 'EXPENSE', 'amount' => Row::text($settlement, 'amount'), 'recorded_at' => $context->now()]);

        return $id;
    }

    /*
     * Consumption.
     */

    /**
     * A consumption's draw of its lot's current net cost (DATABASE §6.2; C-26): the whole remaining cost when it zeroes
     * the lot, else its share; HPP for a dispatch with the consuming project as bearer, LOSS for a loss, SUPPLIER_RETURN
     * for a purchase return, which moves its case's returned cost. The row carries the consumption's bearer and
     * allocation reason, so a cross-company draw is its allocation (DATABASE §6.5; C-28). Returns the INITIAL row, null
     * when the lot has no cost to draw.
     */
    public function draw(CommandContext $context, int $consumptionId, string $businessDate): ?int
    {
        $consumption = self::consumption($consumptionId);
        $lot = (int) Row::text($consumption, 'lot_id');
        $context->require(self::consumptionLocks($consumptionId));
        $ledger = new CostLedger($context);
        [$remainingQty, $remainingCost] = $this->lotBalance($ledger, $lot);
        $qty = Row::text($consumption, 'qty_base');
        $amount = CostSplitter::draw($remainingCost, $qty, $remainingQty);
        $class = self::CLASS_OF[Row::text($consumption, 'kind')];
        $ledger->move('lot_cost_balances', ['lot_id' => $lot], ['remaining_qty_base' => '-'.$qty, 'remaining_cost' => $amount->negated()->toString()]);
        $id = $amount->isZero() ? null : $ledger->attribution([...self::drawOf($consumption), 'cost_class' => $class, 'cause' => 'INITIAL', 'amount' => $amount->toString(), 'business_date' => $businessDate]);
        $ledger->flush();
        $this->cased($context, self::moved(self::none(), 'cases', $class === 'SUPPLIER_RETURN' ? Row::nullable($consumption, 'correction_case_id') : null, $amount));

        return $id;
    }

    /**
     * A restoration's contra (DATABASE §6.2; SF-RESTORE; L-31): the proportional share of the consumption's net cost of
     * the class the restoration reverses over its open quantity before this restoration, the event zeroing the open
     * quantity taking exactly what is left; the units re-enter their lot at exactly the contra, and the row keeps the
     * consumption's bearer and allocation, so the allocation is reversed with its consumption (L-44). A drop-ship line's
     * restoration is dropshipRestored()'s.
     */
    public function restore(CommandContext $context, int $restorationId, string $businessDate): ?int
    {
        $restoration = DB::table('stock_restorations')->where('id', $restorationId)->first(['stock_consumption_id', 'qty_base', 'kind', 'correction_case_id', 'case_company_id']);

        if (! is_object($restoration) || ! isset(self::RESTORATION[Row::text($restoration, 'kind')]) || Row::nullable($restoration, 'stock_consumption_id') === null) {
            throw new InvalidArgumentException("Restoration {$restorationId} reverses no lot consumption's cost here.");
        }

        [$class, $cause] = self::RESTORATION[Row::text($restoration, 'kind')];
        $consumption = self::consumption((int) Row::text($restoration, 'stock_consumption_id'));

        if (! in_array(Row::text($consumption, 'kind'), self::RESTORES[Row::text($restoration, 'kind')], true)) {
            throw new InvalidArgumentException("Restoration {$restorationId} cannot reverse a ".Row::text($consumption, 'kind').' consumption (L-31).');
        }

        $lot = (int) Row::text($consumption, 'lot_id');
        $context->require(self::consumptionLocks((int) Row::text($consumption, 'id')));
        $qty = Row::text($restoration, 'qty_base');
        $contra = ProportionalContra::of(self::net((int) Row::text($consumption, 'id'), $class), $qty, self::open($consumption));
        $ledger = new CostLedger($context);
        $ledger->move('lot_cost_balances', ['lot_id' => $lot], ['remaining_qty_base' => $qty, 'remaining_cost' => $contra->toString()]);
        $id = $contra->isZero() ? null : $ledger->attribution([...self::drawOf($consumption), 'cost_class' => $class, 'cause' => $cause, 'amount' => $contra->negated()->toString(),
            'stock_restoration_id' => $restorationId, 'reverses_attribution_id' => self::initialOf((int) Row::text($consumption, 'id')), 'business_date' => $businessDate,
            'correction_case_id' => Row::nullable($restoration, 'correction_case_id') ?? Row::nullable($consumption, 'correction_case_id'),
            'case_company_id' => Row::nullable($restoration, 'case_company_id') ?? Row::nullable($consumption, 'case_company_id')]);
        $ledger->flush();

        return $id;
    }

    /**
     * A drop-ship confirmation line's restoration (DATABASE §5.7; §6.3; SF-RESTORE): the proportional share of the line's
     * net HPP over its open quantity before this restoration — the event zeroing it taking what is left —, split over
     * the line's cost components by their net amounts, the last absorbing the residual, each part a row naming the
     * restoration. A return to the warehouse (CM-14) contras it into the DROPSHIP_RETURN lot the caller created, whose
     * DROPSHIP_RETURN_BASIS is exactly the contra; a return to the supplier (CM-15) moves it to SUPPLIER_RETURN under the
     * restoration's case — an HPP −c / SUPPLIER_RETURN +c pair — with the case's returned cost; a confirmation recorded in
     * error (CM-27) contras it as never confirmed: its goods and tax are returned for PUR to lower the line's posted
     * values, and its charge parts leave the guards they came from and are re-spread over the charge's remaining received
     * quantity at once, or expensed when none remains. The caller has recorded the restoration, not yet its quantity on
     * the line's balance. A DROPSHIP_ERROR_REVERSAL is CM-37's exact inverse (TASK-048).
     */
    public function dropshipRestored(CommandContext $context, int $restorationId, string $businessDate): ReceiptPosted
    {
        $restoration = DB::table('stock_restorations')->where('id', $restorationId)->first(['dropship_confirmation_line_id', 'qty_base', 'kind', 'correction_case_id', 'case_company_id']);
        $kind = is_object($restoration) ? Row::text($restoration, 'kind') : '';

        if (! is_object($restoration) || ! in_array($kind, [...self::DROPSHIP_AWAY, 'DROPSHIP_CONFIRMATION_REVERSAL'], true)) {
            throw new InvalidArgumentException("Restoration {$restorationId} is no drop-ship return or confirmation reversal.");
        }

        $lineId = (int) Row::text($restoration, 'dropship_confirmation_line_id');
        $dropship = self::dropshipLine($lineId);
        $case = Row::nullable($restoration, 'correction_case_id');

        if ($kind === 'DROPSHIP_SUPPLIER_RETURN' && $case === null) {
            throw new InvalidArgumentException("Restoration {$restorationId} returns to the supplier without its case (C-46).");
        }

        $context->require(self::purchaseLocks(self::purchaseOf((int) Row::text($dropship, 'purchase_line_id'))));
        $balance = DB::table('stock_consumption_balances as b')->where('dropship_confirmation_line_id', $lineId)->first(['qty_base', 'restored_qty_base', 'reclassified_qty_base', 'substituted_qty_base'])
            ?? throw new InvalidArgumentException("Drop-ship line {$lineId} has no balance.");
        $open = (string) BigDecimal::of(Row::text($balance, 'qty_base'))->minus(Row::text($balance, 'restored_qty_base'))
            ->minus(Row::text($balance, 'reclassified_qty_base'))->minus(Row::text($balance, 'substituted_qty_base'));
        $groups = self::directGroups('dropship_confirmation_line_id', $lineId, 'HPP');
        $contra = ProportionalContra::of(Money::sum(Money::zero(), ...array_column($groups, 'net')), Row::text($restoration, 'qty_base'), $open);
        $parts = $groups === [] || $contra->isZero() ? [] : CostSplitter::split($contra, array_map(fn (array $group): string => $group['net']->toString(), $groups));
        $initial = DB::table('cost_attributions')->where('dropship_confirmation_line_id', $lineId)->where('cause', 'INITIAL')->orderBy('id')->value('id');
        $base = ['dropship_confirmation_line_id' => $lineId, 'bearer_company_id' => (int) Row::text($dropship, 'company_id'), 'bearer_project_id' => (int) Row::text($dropship, 'project_id'),
            'stock_restoration_id' => $restorationId, 'reverses_attribution_id' => is_numeric($initial) ? (int) $initial : null, 'business_date' => $businessDate];
        $ledger = new CostLedger($context);
        $posted = ReceiptPosted::none();
        $respreads = [];

        foreach ($groups as $index => $group) {
            $part = $parts[$index] ?? Money::zero();

            if ($part->isZero()) {
                continue;
            }

            $row = [...$base, ...$group['references'], 'cost_component' => $group['component']];

            if ($kind === 'DROPSHIP_SUPPLIER_RETURN') {
                $ledger->attribution([...$row, 'cost_class' => 'HPP', 'cause' => 'RECLASSIFICATION', 'amount' => $part->negated()->toString()]);
                $ledger->attribution([...$row, 'cost_class' => 'SUPPLIER_RETURN', 'cause' => 'RECLASSIFICATION', 'amount' => $part->toString(),
                    'correction_case_id' => (int) $case, 'case_company_id' => (int) Row::text($restoration, 'case_company_id')]);

                continue;
            }

            $ledger->attribution([...$row, 'cost_class' => 'HPP', 'cause' => 'RESTORATION_CONTRA', 'amount' => $part->negated()->toString()]);

            if ($kind !== 'DROPSHIP_CONFIRMATION_REVERSAL') {
                continue;
            }

            // As never confirmed: each part leaves the guard it came from (AX-05's rule for a receipt reversal).
            $posted = $posted->plus(match ($group['component']) {
                'GOODS' => new ReceiptPosted($part->negated(), Money::zero()),
                'NONCREDITABLE_TAX' => new ReceiptPosted(Money::zero(), $part->negated()),
                default => ReceiptPosted::none(),
            });

            if ($group['component'] === 'CHARGE') {
                $charge = $group['references']['purchase_charge_id'];
                $share = $group['references']['purchase_charge_line_share_id'] ?? null;
                $source = $share === null ? $group['references']['respread_from_purchase_line_id'] : (int) Row::text(DB::table('purchase_charge_line_shares')->where('id', $share)->first(['purchase_line_id'])
                    ?? throw new InvalidArgumentException("No charge share {$share}."), 'purchase_line_id');
                $ledger->move('purchase_charge_share_balances', ['purchase_charge_id' => $charge, 'purchase_line_id' => $source], [$share === null ? 'respread_out_amount' : 'posted_amount' => $part->negated()->toString()]);
                $ledger->move('purchase_charge_balances', ['purchase_charge_id' => $charge], ['allocated_amount' => $part->negated()->toString()]);
                $respreads[] = [$charge, $source, $group['references']['allocation_version'], $part];
            }
        }

        $affected = self::moved(self::none(), 'cases', $kind === 'DROPSHIP_SUPPLIER_RETURN' ? $case : null, $contra);

        foreach ($respreads as [$charge, $source, $version, $part]) {
            $affected = self::merge($affected, $this->respread($context, $ledger, $charge, $source, $version, $part, $businessDate));
        }

        $ledger->flush();

        if ($kind === 'DROPSHIP_RETURN') {
            $lot = DB::table('inventory_lots')->where('restoration_id', $restorationId)->value('id');
            $this->basis($context, is_numeric($lot) ? (int) $lot : throw new InvalidArgumentException("Restoration {$restorationId} has no return lot yet."),
                'DROPSHIP_RETURN_BASIS', $contra, $businessDate, ['stock_restoration_id' => $restorationId]);
        }

        $this->cased($context, $affected);

        return $posted;
    }

    /**
     * A loss in transit (annex 15; DATABASE §6.1): the reclassification pair on the dispatch consumption — HPP −a and
     * LOSS +a, a the proportional share of its net HPP over its open quantity —, with no new stock-out and its
     * allocation kept; the project's cost is unchanged.
     */
    public function reclassify(CommandContext $context, int $consumptionId, string $qty, string $businessDate): void
    {
        $consumption = self::consumption($consumptionId);

        if (Row::text($consumption, 'kind') !== 'DISPATCH') {
            throw new InvalidArgumentException("Consumption {$consumptionId} is no dispatch: only a dispatch's units are lost in transit.");
        }

        $context->require(self::consumptionLocks($consumptionId));
        $amount = ProportionalContra::of(self::net($consumptionId, 'HPP'), $qty, self::open($consumption));

        if ($amount->isZero()) {
            return;
        }

        $ledger = new CostLedger($context);
        $row = [...self::drawOf($consumption), 'cause' => 'RECLASSIFICATION', 'reverses_attribution_id' => self::initialOf($consumptionId), 'business_date' => $businessDate];
        $ledger->attribution([...$row, 'cost_class' => 'HPP', 'amount' => $amount->negated()->toString()]);
        $ledger->attribution([...$row, 'cost_class' => 'LOSS', 'amount' => $amount->toString()]);
    }

    /**
     * A supplier return's unrecovered share (DATABASE §6.1; AX-11; L-44): the pair SUPPLIER_RETURN −u / LOSS +u on the
     * purchase-return consumption with cause RETURN_SETTLEMENT, borne by the case's causal project — a cost-only
     * allocation with its reason when that is another company's — and the case's unrecovered figure moved.
     */
    public function settleReturn(CommandContext $context, int $consumptionId, Money $unrecovered, int $bearerCompanyId, ?int $bearerProjectId, ?string $reason, string $businessDate): void
    {
        $consumption = self::consumption($consumptionId);
        $case = Row::nullable($consumption, 'correction_case_id');

        if (Row::text($consumption, 'kind') !== 'PURCHASE_RETURN' || $case === null || ! $unrecovered->isPositive()) {
            throw new InvalidArgumentException("Consumption {$consumptionId} settles no supplier return's unrecovered share.");
        }

        $locks = self::consumptionLocks($consumptionId);
        $context->require($bearerProjectId === null ? $locks : $locks->add('projects', ['id' => $bearerProjectId]));
        $ledger = new CostLedger($context);
        $row = [...self::drawOf($consumption), 'bearer_company_id' => $bearerCompanyId, 'bearer_project_id' => $bearerProjectId, 'allocation_reason' => $reason,
            'cause' => 'RETURN_SETTLEMENT', 'reverses_attribution_id' => self::initialOf($consumptionId), 'business_date' => $businessDate];
        $ledger->attribution([...$row, 'cost_class' => 'SUPPLIER_RETURN', 'amount' => $unrecovered->negated()->toString()]);
        $ledger->attribution([...$row, 'cost_class' => 'LOSS', 'amount' => $unrecovered->toString()]);
        $this->cases->post($context, (int) $case, ['unrecovered_value' => $unrecovered->toString()], self::REOPENED);
    }

    /*
     * Charges (SF-CHARGE; AX-29; AX-32; AX-05).
     */

    /**
     * A purchase charge recorded (AX-29; C-20; C-21): its source bill cited, refused with C-20 beyond the bill's total;
     * its guard born; a non-attributable charge an expense of the purchase's project, or of its company, and nothing
     * else; an attributable one's shares born at their current version and posted at once for what its lines already
     * received — each lot's whole share as a CHARGE_SHARE entry, the part of units already consumed drawn as LATE_CHARGE,
     * a drop-ship or non-stock line's part a direct row — and re-spread for remainders already ended.
     */
    public function chargeRecorded(CommandContext $context, int $chargeId, string $businessDate): void
    {
        $context->require(self::chargeLocks($chargeId));
        $charge = self::charge($chargeId);
        $amount = Money::of(Row::text($charge, 'amount'));
        $this->cite($context, (int) Row::text($charge, 'source_bill_evidence_id'), $amount);
        GuardUpdate::bear($context->connection, 'purchase_charge_balances', ['purchase_charge_id' => $chargeId], ['amount' => $amount->toString(), 'recorded_at' => $context->now()]);
        $ledger = new CostLedger($context);

        if (Row::text($charge, 'classification') === 'NON_ATTRIBUTABLE') {
            $ledger->expense(self::chargeExpense($charge, $amount, $businessDate));
            $ledger->move('purchase_charge_balances', ['purchase_charge_id' => $chargeId], ['expensed_amount' => $amount->toString()]);
            $ledger->flush();

            return;
        }

        $version = self::currentVersion($chargeId);

        foreach (self::versionShares($chargeId, $version) as $share) {
            GuardUpdate::bear($context->connection, 'purchase_charge_share_balances', ['purchase_charge_id' => $chargeId, 'purchase_line_id' => $share['line']], [
                'allocation_version' => $version, 'share_amount' => $share['share']->toString(), 'recorded_at' => $context->now(),
            ]);
        }

        $affected = $this->postShares($context, $ledger, $chargeId, $version, 'CHARGE_SHARE', 'LATE_CHARGE', 'INITIAL', $businessDate);
        $ledger->flush();
        $this->cased($context, $affected);
    }

    /**
     * A line's remainder ended — closed, cancelled — (AX-32; AX-12; L-47): what is pending of each of its charge
     * shares for the ended quantity is re-spread over the received quantity of the charge in the same transaction —
     * CHARGE_RESPREAD on the lots, consumed units as CHARGE_RESPREAD draws —, or, when no received quantity of the
     * charge remains, expensed as PURCHASE_CHARGE citing the line (DIR-024 D-2 items 5–6). The caller has moved the
     * line's closed quantity first.
     */
    public function remainderEnded(CommandContext $context, int $purchaseLineId, string $businessDate): void
    {
        $context->require(self::purchaseLocks(self::purchaseOf($purchaseLineId)));
        $ledger = new CostLedger($context);
        $affected = self::none();

        foreach (self::sharesOf($purchaseLineId) as $share) {
            $ended = $this->endedPending($ledger, $share, self::lineBalance($purchaseLineId));

            if ($ended->isPositive()) {
                $affected = self::merge($affected, $this->respread($context, $ledger, $share['charge'], $purchaseLineId, $share['version'], $ended, $businessDate));
            }
        }

        $ledger->flush();
        $this->cased($context, $affected);
    }

    /**
     * A receipt reversed for `qty` of the lot's unconsumed units (AX-05; DATABASE §6.1): the draw of their share of the
     * lot's remaining cost split over the lot's cost components in proportion to their net amounts — GOODS, then
     * NONCREDITABLE_TAX, then each charge's share and re-spread rows, the last absorbing the residual —, each row lowering
     * the guard it came from; the reversed charge parts re-spread at once over the charge's remaining received quantity
     * as never received, or expensed when none remains. The caller has recorded the reversal's movement.
     */
    public function reverseReceipt(CommandContext $context, int $lotId, string $qty, string $businessDate): ReceiptPosted
    {
        $lot = self::lot($lotId);
        $receiving = DB::table('receiving_lines')->where('id', Row::nullable($lot, 'receiving_line_id') ?? 0)->first(['purchase_line_id']);

        if (! is_object($receiving)) {
            throw new InvalidArgumentException("Lot {$lotId} is no receipt's lot: its units are removed by reverseBasis().");
        }

        $lineId = (int) Row::text($receiving, 'purchase_line_id');
        $context->require(self::purchaseLocks(self::purchaseOf($lineId)));
        $ledger = new CostLedger($context);
        [$remainingQty, $remainingCost] = $this->lotBalance($ledger, $lotId);
        $drawn = CostSplitter::draw($remainingCost, $qty, $remainingQty);
        $ledger->move('lot_cost_balances', ['lot_id' => $lotId], ['remaining_qty_base' => '-'.$qty, 'remaining_cost' => $drawn->negated()->toString()]);
        $components = self::components($lotId);
        $posted = ReceiptPosted::none();
        $respreads = [];

        if (! $drawn->isZero() && $components !== []) {
            $parts = CostSplitter::split($drawn, array_map(fn (array $component): string => $component['net']->toString(), $components));

            foreach ($components as $key => $component) {
                $part = $parts[$key];

                if ($part->isZero()) {
                    continue;
                }

                $ledger->entry([...$component['references'], 'lot_id' => $lotId, 'source_company_id' => (int) Row::text($lot, 'source_company_id'),
                    'entry_kind' => 'RECEIPT_REVERSAL', 'cost_component' => $component['component'], 'amount' => $part->negated()->toString(), 'business_date' => $businessDate]);
                $posted = $posted->plus(match ($component['component']) {
                    'GOODS' => new ReceiptPosted($part->negated(), Money::zero()),
                    'NONCREDITABLE_TAX' => new ReceiptPosted(Money::zero(), $part->negated()),
                    default => ReceiptPosted::none(),
                });

                if ($component['component'] === 'CHARGE') {
                    $charge = (int) $component['references']['purchase_charge_id'];
                    $source = $component['share_line'] ?? (int) $component['references']['respread_from_purchase_line_id'];
                    $column = isset($component['share_line']) ? 'posted_amount' : 'respread_out_amount';
                    $ledger->move('purchase_charge_share_balances', ['purchase_charge_id' => $charge, 'purchase_line_id' => $source], [$column => $part->negated()->toString()]);
                    $ledger->move('purchase_charge_balances', ['purchase_charge_id' => $charge], ['allocated_amount' => $part->negated()->toString()]);
                    $respreads[] = [$charge, $source, (int) $component['references']['allocation_version'], $part];
                }
            }
        }

        $affected = self::none();

        foreach ($respreads as [$charge, $source, $version, $part]) {
            $affected = self::merge($affected, $this->respread($context, $ledger, $charge, $source, $version, $part, $businessDate));
        }

        $ledger->flush();
        $this->cased($context, $affected);

        return $posted;
    }

    /*
     * Corrections (AX-34; CM-17; CM-35; DATABASE §6.3).
     */

    /**
     * A price or tax correction of a line (DATABASE §6.3 step 1; C-57): the line's cumulative target for its current
     * received quantity at the corrected value less what is posted, spread over the line's lots, non-replacement
     * drop-ship lines and — for a non-stock line — the line itself, in id order by cumulative rounding of their net
     * quantities as COST_CORRECTION rows, each holder's part passed on to its dependents (step 3), the cases' replacements
     * corrected with them, the cases reopened (step 4) and the completed projects revalidated where material (step 5).
     * The caller has moved the line's value by the adjustment's delta, or, when a lowered value could not stand beside what is still posted (C-57), names it as `$corrected` and moves both together afterwards.
     */
    public function lineCorrected(CommandContext $context, int $adjustmentId, string $businessDate, ?Money $corrected = null): ReceiptPosted
    {
        $adjustment = DB::table('purchase_line_adjustments')->where('id', $adjustmentId)->first(['purchase_line_id', 'kind'])
            ?? throw new InvalidArgumentException("No purchase line adjustment {$adjustmentId}.");
        $component = match (Row::text($adjustment, 'kind')) {
            'PRICE_CORRECTION' => 'GOODS',
            'TAX_CORRECTION' => 'NONCREDITABLE_TAX',
            default => throw new InvalidArgumentException("Adjustment {$adjustmentId} corrects no line's price or tax."),
        };
        $lineId = (int) Row::text($adjustment, 'purchase_line_id');
        $context->require(self::purchaseLocks(self::purchaseOf($lineId)));
        $line = self::lineBalance($lineId);
        [$value, $postedColumn] = $component === 'GOODS' ? ['goods_value', 'posted_goods_value'] : ['noncreditable_tax_value', 'posted_noncreditable_tax_value'];
        $delta = CostSplitter::target($corrected ?? Money::of(Row::text($line, $value)), self::received($line), Row::text($line, 'ordered_qty_base'))->minus(Money::of(Row::text($line, $postedColumn)));
        $ledger = new CostLedger($context);
        $affected = self::none();

        if (! $delta->isZero()) {
            $references = ['purchase_line_id' => $lineId, 'purchase_line_adjustment_id' => $adjustmentId];
            $affected = $this->spreadOverLine($context, $ledger, $lineId, $delta, ['entry_kind' => 'COST_CORRECTION', 'cost_component' => $component, ...$references],
                $component, [], 'COST_CORRECTION', 'COST_CORRECTION', $businessDate);
        }

        $affected = $this->replaced($context, $ledger, $affected, $businessDate);
        $ledger->flush();
        $this->corrected($context, $affected, "purchase_line_adjustment:{$adjustmentId}");

        return $component === 'GOODS' ? new ReceiptPosted($delta, Money::zero()) : new ReceiptPosted(Money::zero(), $delta);
    }

    /**
     * A charge's amount or basis corrected (DATABASE §6.3 step 2; CM-35; C-20; C-21): the amount's delta cited on the
     * source bill (C-20) and moved on the charge's guard; every CHARGE posting of the old allocation version contra'd
     * where it now sits — the lots' entries and their dependents' shares, the parts drop-ship returns carried into their
     * lots, the direct rows on the project or under a supplier-return case — and the charge re-posted under the new
     * version by AX-29's rules: received units in lots, consumed units as draws, ended remainders re-spread, open
     * remainders pending; an expensed share stays expensed. The shares' guards take the new version, which names every
     * line of the old one — a line the charge no longer covers at a zero share. PUR has recorded the adjustment, when the
     * amount changes, and the new version's shares first.
     */
    public function chargeCorrected(CommandContext $context, int $chargeId, ?int $adjustmentId, string $businessDate): void
    {
        $context->require(self::chargeLocks($chargeId));
        $charge = self::charge($chargeId);

        if (Row::text($charge, 'classification') !== 'ATTRIBUTABLE') {
            throw new InvalidArgumentException("Charge {$chargeId} is expensed, not allocated: its correction is the expense's.");
        }

        $ledger = new CostLedger($context);
        $delta = $adjustmentId === null ? Money::zero() : Money::of(Row::text(DB::table('purchase_line_adjustments')->where('id', $adjustmentId)->where('kind', 'CHARGE_CORRECTION')->where('purchase_charge_id', $chargeId)->first(['amount_delta'])
            ?? throw new InvalidArgumentException("Adjustment {$adjustmentId} corrects no amount of charge {$chargeId}."), 'amount_delta'));

        if (! $delta->isZero()) {
            $this->cite($context, (int) Row::text($charge, 'source_bill_evidence_id'), $delta);
            $ledger->move('purchase_charge_balances', ['purchase_charge_id' => $chargeId], ['amount' => $delta->toString()]);
        }

        $old = DB::table('purchase_charge_share_balances as b')->where('purchase_charge_id', $chargeId)->get()->keyBy(fn (object $row): int => (int) Row::text($row, 'purchase_line_id'));
        $version = self::currentVersion($chargeId);
        $shares = self::versionShares($chargeId, $version);
        $dropped = array_diff($old->keys()->all(), array_column($shares, 'line'));

        if ($dropped !== []) {
            throw new InvalidArgumentException("Charge {$chargeId}'s version {$version} leaves out line ".implode(', ', $dropped).': a line the charge no longer covers takes a zero share (DATABASE §6.3 step 2).');
        }

        $affected = $this->contraVersion($context, $ledger, $chargeId, $businessDate);

        foreach ($shares as $share) {
            $before = $old->get($share['line']);
            $expensed = is_object($before) ? Money::of(Row::text($before, 'expensed_out_amount')) : Money::zero();
            $key = ['purchase_charge_id' => $chargeId, 'purchase_line_id' => $share['line']];

            if (! is_object($before)) {
                GuardUpdate::bear($context->connection, 'purchase_charge_share_balances', $key, [
                    'allocation_version' => $version, 'share_amount' => $share['share']->toString(), 'recorded_at' => $context->now(),
                ]);

                continue;
            }

            // The guard takes the new version: its share, and nothing posted or re-spread under it yet — the old
            // version's postings are contra'd above, so the charge's allocated total drops by them too.
            $unposted = Money::of(Row::text($before, 'posted_amount'))->plus(Money::of(Row::text($before, 'respread_out_amount')));
            $ledger->move('purchase_charge_share_balances', $key, [
                'allocation_version' => (string) ($version - (int) Row::text($before, 'allocation_version')),
                'share_amount' => $share['share']->minus(Money::of(Row::text($before, 'share_amount')))->toString(),
                'posted_amount' => Money::of(Row::text($before, 'posted_amount'))->negated()->toString(),
                'respread_out_amount' => Money::of(Row::text($before, 'respread_out_amount'))->negated()->toString(),
            ]);
            $ledger->move('purchase_charge_balances', ['purchase_charge_id' => $chargeId], ['allocated_amount' => $unposted->negated()->toString()]);

            // An expense never shrinks (expenses are positive and insert-only): a share below what it already expensed
            // cannot be posted (C-21).
            if ($expensed->compareTo($share['share']) > 0) {
                throw Refused::rejected('C-21', ['reason' => 'expensed_above_share', 'line' => $share['line']]);
            }
        }

        // The new version posts against the reset guards, read with this posting's deltas, so each guard moves once.
        $affected = self::merge($affected, $this->postShares($context, $ledger, $chargeId, $version, 'CHARGE_SHARE', 'COST_CORRECTION', 'COST_CORRECTION', $businessDate));
        $affected = $this->replaced($context, $ledger, $affected, $businessDate);
        $ledger->flush();
        $this->corrected($context, $affected, 'purchase_charge:'.$chargeId.($adjustmentId === null ? '' : ":adjustment:{$adjustmentId}"));
    }

    /*
     * Posting rules shared by the entry points.
     */

    /**
     * Posts each line's share of the charge's version for what the line has received — its lots' entries of
     * `entryKind` with their dependents' draws of `drawCause`, its drop-ship and non-stock parts as direct rows of
     * `directCause` — and re-spreads what is pending for its ended remainder.
     *
     * @return Affected
     */
    private function postShares(CommandContext $context, CostLedger $ledger, int $chargeId, int $version, string $entryKind, string $drawCause, string $directCause, string $businessDate): array
    {
        $affected = self::none();

        foreach (self::versionShares($chargeId, $version) as $share) {
            $line = self::lineBalance($share['line']);
            $key = ['purchase_charge_id' => $chargeId, 'purchase_line_id' => $share['line']];
            $balance = DB::table('purchase_charge_share_balances as b')->where($key)->first(['posted_amount', 'respread_out_amount', 'expensed_out_amount']);
            $posted = Money::of(is_object($balance) ? Row::text($balance, 'posted_amount') : '0')->plus(Money::of((string) $ledger->moved('purchase_charge_share_balances', $key, 'posted_amount')));
            $respread = Money::of(is_object($balance) ? Row::text($balance, 'respread_out_amount') : '0')->plus(Money::of((string) $ledger->moved('purchase_charge_share_balances', $key, 'respread_out_amount')));
            $expensed = Money::of(is_object($balance) ? Row::text($balance, 'expensed_out_amount') : '0');
            $received = self::received($line);
            $amount = BigDecimal::of($received)->isZero() ? Money::zero() : ChargeAllocator::posting($share['share'], $received, Row::text($line, 'ordered_qty_base'), $posted, $respread, $expensed);

            if ($amount->isPositive()) {
                $affected = self::merge($affected, $this->spreadOverLine($context, $ledger, $share['line'], $amount, [
                    'entry_kind' => $entryKind, 'cost_component' => 'CHARGE', 'purchase_charge_id' => $chargeId, 'purchase_charge_line_share_id' => $share['id'], 'allocation_version' => $version,
                ], 'CHARGE', ['purchase_charge_id' => $chargeId, 'purchase_charge_line_share_id' => $share['id'], 'allocation_version' => $version], $drawCause, $directCause, $businessDate));
                $this->allocated($ledger, $chargeId, $share['line'], $amount);
            }

            $ended = $this->endedPending($ledger, ['charge' => $chargeId, 'line' => $share['line'], 'share' => $share['share']], $line, $expensed);

            if ($ended->isPositive()) {
                $affected = self::merge($affected, $this->respread($context, $ledger, $chargeId, $share['line'], $version, $ended, $businessDate));
            }
        }

        return $affected;
    }

    /**
     * Re-spreads `amount` of the charge's share on `sourceLine` over the received quantity of the charge — every lot of
     * its lines, drop-ship line and attributed non-stock line by net quantity, by cumulative rounding — as
     * CHARGE_RESPREAD, moving the source line's re-spread total; with nothing received, a PURCHASE_CHARGE expense citing
     * the line moves its expensed total.
     *
     * @return Affected
     */
    private function respread(CommandContext $context, CostLedger $ledger, int $chargeId, int $sourceLine, int $version, Money $amount, string $businessDate): array
    {
        $key = ['purchase_charge_id' => $chargeId, 'purchase_line_id' => $sourceLine];
        $targets = self::targets(array_map(fn (array $share): int => $share['line'], self::versionShares($chargeId, $version)));

        if ($targets === []) {
            $charge = self::charge($chargeId);
            $ledger->expense([...self::chargeExpense($charge, $amount, $businessDate), 'purchase_line_id' => $sourceLine]);
            $ledger->move('purchase_charge_share_balances', $key, ['expensed_out_amount' => $amount->toString()]);
            $ledger->move('purchase_charge_balances', ['purchase_charge_id' => $chargeId], ['expensed_amount' => $amount->toString()]);

            return self::none();
        }

        $references = ['purchase_charge_id' => $chargeId, 'respread_from_purchase_line_id' => $sourceLine, 'allocation_version' => $version];
        $affected = $this->spreadOverTargets($context, $ledger, $targets, $amount, ['entry_kind' => 'CHARGE_RESPREAD', 'cost_component' => 'CHARGE', ...$references],
            'CHARGE', $references, 'CHARGE_RESPREAD', 'CHARGE_RESPREAD', $businessDate);
        $ledger->move('purchase_charge_share_balances', $key, ['respread_out_amount' => $amount->toString()]);
        $ledger->move('purchase_charge_balances', ['purchase_charge_id' => $chargeId], ['allocated_amount' => $amount->toString()]);

        return $affected;
    }

    /**
     * Contras every CHARGE posting of the charge's current allocation version where it now sits (CM-35): each lot's net
     * entries of that version as one COST_CORRECTION row per share or re-spread source, its dependents re-attributed by
     * their units; the parts drop-ship returns carried into their lots' basis, contra'd on those lots; and each direct
     * row's net per source, class and case — a project's HPP, a supplier-return case's SUPPLIER_RETURN. The version's
     * guards are reset by the caller.
     *
     * @return Affected
     */
    private function contraVersion(CommandContext $context, CostLedger $ledger, int $chargeId, string $businessDate): array
    {
        $affected = self::none();
        $version = DB::table('purchase_charge_share_balances as b')->where('purchase_charge_id', $chargeId)->max('allocation_version');

        if (! is_numeric($version)) {
            return $affected;
        }

        $version = (int) $version;
        $groups = DB::table('lot_cost_entries')->where('purchase_charge_id', $chargeId)->where('allocation_version', $version)
            ->groupBy('lot_id', 'purchase_charge_line_share_id', 'respread_from_purchase_line_id')->orderBy('lot_id')
            ->selectRaw('lot_id, purchase_charge_line_share_id, respread_from_purchase_line_id, sum(amount) as net')->get();

        // A drop-ship return carried its units' part of the version into its lot's basis (§5.7): that part sits in the lot.
        $carried = DB::table('cost_attributions as a')->join('inventory_lots as l', 'l.restoration_id', '=', 'a.stock_restoration_id')
            ->where('a.purchase_charge_id', $chargeId)->where('a.allocation_version', $version)->where('a.cause', 'RESTORATION_CONTRA')->whereNotNull('a.dropship_confirmation_line_id')
            ->groupBy('l.id', 'a.purchase_charge_line_share_id', 'a.respread_from_purchase_line_id')->orderBy('l.id')
            ->selectRaw('l.id as lot_id, a.purchase_charge_line_share_id, a.respread_from_purchase_line_id, -sum(a.amount) as net')->get();

        foreach ([...$groups, ...$carried] as $group) {
            $net = Money::of(Row::text($group, 'net'));

            if ($net->isZero()) {
                continue;
            }

            $references = array_filter(['purchase_charge_id' => $chargeId, 'allocation_version' => $version,
                'purchase_charge_line_share_id' => Row::nullable($group, 'purchase_charge_line_share_id'), 'respread_from_purchase_line_id' => Row::nullable($group, 'respread_from_purchase_line_id')],
                fn (mixed $value): bool => $value !== null);
            $affected = self::merge($affected, $this->spreadOnLot($context, $ledger, (int) Row::text($group, 'lot_id'), $net->negated(),
                ['entry_kind' => 'COST_CORRECTION', 'cost_component' => 'CHARGE', ...$references], 'COST_CORRECTION', $businessDate));
        }

        $direct = DB::table('cost_attributions as a')->leftJoin('dropship_confirmation_lines as d', 'd.id', '=', 'a.dropship_confirmation_line_id')
            ->where('a.purchase_charge_id', $chargeId)->where('a.allocation_version', $version)->whereNull('a.stock_consumption_id')
            ->groupBy('a.dropship_confirmation_line_id', 'a.purchase_line_id', 'a.purchase_charge_line_share_id', 'a.respread_from_purchase_line_id', 'a.bearer_company_id',
                'a.bearer_project_id', 'a.cost_class', 'a.correction_case_id', 'a.case_company_id', 'd.purchase_line_id')
            ->selectRaw('a.dropship_confirmation_line_id, a.purchase_line_id, a.purchase_charge_line_share_id, a.respread_from_purchase_line_id, a.bearer_company_id,
                a.bearer_project_id, a.cost_class, a.correction_case_id, a.case_company_id, d.purchase_line_id as source_line_id, sum(a.amount) as net')->get();

        foreach ($direct as $row) {
            $net = Money::of(Row::text($row, 'net'));

            if ($net->isZero()) {
                continue;
            }

            $class = Row::text($row, 'cost_class');
            $ledger->attribution(array_filter([
                'cost_class' => $class, 'cause' => 'COST_CORRECTION', 'amount' => $net->negated()->toString(), 'cost_component' => 'CHARGE',
                'dropship_confirmation_line_id' => Row::nullable($row, 'dropship_confirmation_line_id'), 'purchase_line_id' => Row::nullable($row, 'purchase_line_id'),
                'purchase_charge_id' => $chargeId, 'allocation_version' => $version, 'purchase_charge_line_share_id' => Row::nullable($row, 'purchase_charge_line_share_id'),
                'respread_from_purchase_line_id' => Row::nullable($row, 'respread_from_purchase_line_id'), 'bearer_company_id' => Row::text($row, 'bearer_company_id'),
                'bearer_project_id' => Row::nullable($row, 'bearer_project_id'), 'correction_case_id' => Row::nullable($row, 'correction_case_id'),
                'case_company_id' => Row::nullable($row, 'case_company_id'), 'business_date' => $businessDate,
            ], fn (mixed $value): bool => $value !== null));
            $source = Row::nullable($row, 'source_line_id');
            $affected = $class === 'SUPPLIER_RETURN'
                ? self::returnedMove($affected, Row::nullable($row, 'correction_case_id'), $source === null ? null : (int) $source, $net->negated())
                : self::moved($affected, 'projects', Row::nullable($row, 'bearer_project_id'), $net->negated());
        }

        return $affected;
    }

    /**
     * Spreads an amount over a line's received quantity — its lots, its non-replacement drop-ship lines, or the line
     * itself when it is an attributed non-stock line — by net quantity in id order, each holder passing its part on.
     *
     * @param  array<string, int|string>  $entry  the lot entry's kind, component and references
     * @param  array<string, int|string>  $directReferences  the direct rows' charge references, none for goods and tax
     * @return Affected
     */
    private function spreadOverLine(CommandContext $context, CostLedger $ledger, int $lineId, Money $amount, array $entry, string $component, array $directReferences, string $drawCause, string $directCause, string $businessDate): array
    {
        $targets = self::targets([$lineId]);

        if ($targets === []) {
            throw new InvalidArgumentException("Purchase line {$lineId} has no received quantity to take {$amount}.");
        }

        return $this->spreadOverTargets($context, $ledger, $targets, $amount, $entry, $component, $directReferences, $drawCause, $directCause, $businessDate);
    }

    /**
     * @param  list<Target>  $targets
     * @param  array<string, int|string>  $entry
     * @param  array<string, int|string>  $directReferences
     * @return Affected
     */
    private function spreadOverTargets(CommandContext $context, CostLedger $ledger, array $targets, Money $amount, array $entry, string $component, array $directReferences, string $drawCause, string $directCause, string $businessDate): array
    {
        $parts = CostSplitter::spread($amount, array_map(fn (array $target): string => $target['qty'], $targets));
        $affected = self::none();

        foreach ($targets as $index => $target) {
            $part = $parts[$index];

            if ($part->isZero()) {
                continue;
            }

            $affected = self::merge($affected, $target['kind'] === 'lot'
                ? $this->spreadOnLot($context, $ledger, $target['id'], $part, $entry, $drawCause, $businessDate)
                : $this->spreadOnDirect($context, $ledger, $target, $part, $entry, $component, $directReferences, $drawCause, $directCause, $businessDate));
        }

        return $affected;
    }

    /**
     * Posts `amount` on a lot — the entry — and re-attributes it to the lot's dependents by their units still out, the
     * lot keeping its remaining units' part (DATABASE §6.3 step 3): a dispatch's part split between its open HPP and its
     * reclassified LOSS, a loss's LOSS, a purchase return's SUPPLIER_RETURN, which moves its case's returned cost. Each
     * draw keeps its consumption's bearer and allocation (L-44).
     *
     * @param  array<string, int|string>  $entry
     * @return Affected
     */
    private function spreadOnLot(CommandContext $context, CostLedger $ledger, int $lotId, Money $amount, array $entry, string $drawCause, string $businessDate): array
    {
        $context->require(self::lotLocks([$lotId]));
        $lot = self::lot($lotId);
        $ledger->entry([...$entry, 'lot_id' => $lotId, 'source_company_id' => (int) Row::text($lot, 'source_company_id'), 'amount' => $amount->toString(), 'business_date' => $businessDate]);
        [$remainingQty] = $this->lotBalance($ledger, $lotId);
        $weights = [];
        $dependents = [];

        foreach (self::consumptionsOf($lotId) as $consumption) {
            $out = self::out($consumption);

            if (BigDecimal::of($out)->isPositive()) {
                $weights['c'.Row::text($consumption, 'id')] = $out;
                $dependents['c'.Row::text($consumption, 'id')] = $consumption;
            }
        }

        if (BigDecimal::of($remainingQty)->isPositive()) {
            $weights['lot'] = $remainingQty;
        }

        if ($weights === []) {
            throw new InvalidArgumentException("Lot {$lotId} has no units, kept or consumed, to take {$amount}.");
        }

        $affected = self::none();
        $drawn = Money::zero();

        foreach (CostSplitter::spread($amount, $weights) as $weight => $part) {
            if ($weight === 'lot' || $part->isZero()) {
                continue;
            }

            $consumption = $dependents[$weight];
            $drawn = $drawn->plus($part);

            foreach (self::classesOf($consumption, $part) as $class => $classPart) {
                if ($classPart->isZero()) {
                    continue;
                }

                $ledger->attribution([...self::drawOf($consumption), 'cost_class' => $class, 'cause' => $drawCause, 'amount' => $classPart->toString(),
                    'reverses_attribution_id' => $drawCause === 'COST_CORRECTION' ? self::initialOf((int) Row::text($consumption, 'id')) : null, 'business_date' => $businessDate]);
                $line = Row::nullable($lot, 'purchase_line_id');
                $affected = $class === 'SUPPLIER_RETURN'
                    ? self::returnedMove($affected, Row::nullable($consumption, 'correction_case_id'), $line === null ? null : (int) $line, $classPart)
                    : self::moved($affected, 'projects', Row::nullable($consumption, 'bearer_project_id'), $classPart);
            }
        }

        $ledger->move('lot_cost_balances', ['lot_id' => $lotId], ['remaining_cost' => $amount->minus($drawn)->toString()]);

        return $affected;
    }

    /**
     * Posts `amount` on a direct source: a non-stock line's part on the project it was attributed to; a drop-ship line's
     * part split by units over what holds them now (§5.7; §6.3 step 3) — the units still on the confirmation's project, a
     * return lot's units through that lot, a supplier return's units as SUPPLIER_RETURN under its case, which moves.
     *
     * @param  Target  $target
     * @param  array<string, int|string>  $entry
     * @param  array<string, int|string>  $directReferences
     * @return Affected
     */
    private function spreadOnDirect(CommandContext $context, CostLedger $ledger, array $target, Money $amount, array $entry, string $component, array $directReferences, string $drawCause, string $directCause, string $businessDate): array
    {
        $source = $target['kind'] === 'nonstock' ? ['purchase_line_id' => $target['id']] : ['dropship_confirmation_line_id' => $target['id']];
        $row = [...$directReferences, ...$source, 'cost_class' => 'HPP', 'cause' => $directCause, 'cost_component' => $component,
            'bearer_company_id' => $target['company'] ?? 0, 'bearer_project_id' => $target['project'] ?? null, 'business_date' => $businessDate];
        $away = $target['kind'] === 'dropship' ? self::dropshipAway($target['id']) : [];
        $weights = [];
        $kept = BigDecimal::of($target['qty']);

        foreach ($away as $key => $holder) {
            $weights[$key] = $holder['qty'];
            $kept = $kept->minus($holder['qty']);
        }

        if ($kept->isPositive()) {
            $weights['kept'] = (string) $kept;
        }

        $affected = self::none();

        foreach (CostSplitter::spread($amount, $weights) as $key => $part) {
            if ($part->isZero()) {
                continue;
            }

            if ($key === 'kept') {
                $ledger->attribution([...$row, 'amount' => $part->toString()]);
                $affected = self::moved($affected, 'projects', isset($target['project']) ? (string) $target['project'] : null, $part);

                continue;
            }

            $holder = $away[$key];

            if ($holder['lot'] !== null) {
                $affected = self::merge($affected, $this->spreadOnLot($context, $ledger, $holder['lot'], $part, $entry, $drawCause, $businessDate));

                continue;
            }

            // The returned units' part is a draw of their supplier return, as a lot's purchase return takes it (one
            // INITIAL per source and charge stays the confirmation's own).
            $ledger->attribution([...$row, 'cost_class' => 'SUPPLIER_RETURN', 'cause' => $drawCause, 'amount' => $part->toString(), 'stock_restoration_id' => $holder['restoration'],
                'correction_case_id' => $holder['case'], 'case_company_id' => $holder['case_company']]);
            $affected = self::returnedMove($affected, $holder['case'] === null ? null : (string) $holder['case'], $target['line'] ?? null, $part);
        }

        return $affected;
    }

    /**
     * Step 3's replacements (DATABASE §6.3): each case whose returned cost this posting moves for one purchase line's
     * units passes the replaced units' share of that move — over the units the case returned from that line — to the
     * replacement lots and confirmations created for them, a COST_CORRECTION of their basis passed on to their own
     * holders, and moves its replaced value by the same, so a replaced return stays balanced; a case a replacement's
     * holders reach in turn is passed on once.
     *
     * @param  Affected  $affected
     * @return Affected
     */
    private function replaced(CommandContext $context, CostLedger $ledger, array $affected, string $businessDate): array
    {
        $done = [];

        while (($pending = array_diff_key(array_filter($affected['returns'], fn (Money $moved): bool => ! $moved->isZero()), $done)) !== []) {
            foreach ($pending as $key => $moved) {
                $done[$key] = true;
                [$case, $line] = array_map('intval', explode(':', $key));
                $replacements = self::replacementsOf($case, $line);

                if ($replacements === []) {
                    continue;
                }

                $weights = array_map(fn (array $replacement): string => $replacement['qty'], $replacements);
                $rest = BigDecimal::of(self::returnedUnits($case, $line))->minus(array_reduce($weights, fn (BigDecimal $sum, string $qty): BigDecimal => $sum->plus($qty), BigDecimal::zero()));

                if ($rest->isPositive()) {
                    $weights['rest'] = (string) $rest;
                }

                foreach (CostSplitter::spread($moved, $weights) as $key => $part) {
                    if ($key === 'rest' || $part->isZero()) {
                        continue;
                    }

                    $replacement = $replacements[$key];
                    $entry = ['entry_kind' => 'COST_CORRECTION', 'cost_component' => 'BASIS', 'correction_case_id' => $case];
                    $affected = self::merge($affected, $replacement['kind'] === 'lot'
                        ? $this->spreadOnLot($context, $ledger, $replacement['id'], $part, $entry, 'COST_CORRECTION', $businessDate)
                        : $this->spreadOnDirect($context, $ledger, $replacement, $part, $entry, 'BASIS', ['correction_case_id' => $case, 'case_company_id' => $replacement['company'] ?? 0],
                            'COST_CORRECTION', 'COST_CORRECTION', $businessDate));
                    $affected = self::moved($affected, 'replaced', (string) $case, $part);
                }
            }
        }

        return $affected;
    }

    /**
     * A line's direct cost on its drop-ship line or non-stock line (§6.1): GOODS, NONCREDITABLE_TAX and each current
     * charge share at the line's cumulative targets, INITIAL rows.
     *
     * @param  array<string, int>  $source
     */
    private function direct(CommandContext $context, int $lineId, array $source, int $companyId, int $projectId, string $businessDate): ReceiptPosted
    {
        $line = self::lineBalance($lineId);
        $received = isset($source['purchase_line_id']) ? Row::text($line, 'ordered_qty_base') : self::received($line);
        $ordered = Row::text($line, 'ordered_qty_base');
        $goods = CostSplitter::target(Money::of(Row::text($line, 'goods_value')), $received, $ordered)->minus(Money::of(Row::text($line, 'posted_goods_value')));
        $tax = CostSplitter::target(Money::of(Row::text($line, 'noncreditable_tax_value')), $received, $ordered)->minus(Money::of(Row::text($line, 'posted_noncreditable_tax_value')));
        $ledger = new CostLedger($context);
        $base = [...$source, 'cost_class' => 'HPP', 'cause' => 'INITIAL', 'bearer_company_id' => $companyId, 'bearer_project_id' => $projectId, 'business_date' => $businessDate];

        foreach (['GOODS' => $goods, 'NONCREDITABLE_TAX' => $tax] as $component => $amount) {
            if (! $amount->isZero()) {
                $ledger->attribution([...$base, 'cost_component' => $component, 'amount' => $amount->toString()]);
            }
        }

        foreach (self::sharesOf($lineId) as $share) {
            $amount = ChargeAllocator::posting($share['share'], $received, $ordered, $share['posted'], $share['respread_out'], $share['expensed_out']);

            if ($amount->isPositive()) {
                $ledger->attribution([...$base, 'cost_component' => 'CHARGE', 'amount' => $amount->toString(), 'purchase_charge_id' => $share['charge'],
                    'purchase_charge_line_share_id' => $share['id'], 'allocation_version' => $share['version']]);
                $this->allocated($ledger, $share['charge'], $lineId, $amount);
            }
        }

        $ledger->flush();

        return new ReceiptPosted($goods, $tax);
    }

    /**
     * What is pending of a line's share for its ended quantity — closed, or the whole remainder of a cancelled purchase
     * —: the share's cumulative target for received and ended units less what is posted, re-spread and expensed.
     *
     * @param  array{charge: int, line: int, share: Money}  $share
     */
    private function endedPending(CostLedger $ledger, array $share, object $line, ?Money $expensedCarried = null): Money
    {
        $key = ['purchase_charge_id' => $share['charge'], 'purchase_line_id' => $share['line']];
        $balance = DB::table('purchase_charge_share_balances as b')->where($key)->first(['posted_amount', 'respread_out_amount', 'expensed_out_amount']);
        $taken = Money::sum(
            Money::of(is_object($balance) ? Row::text($balance, 'posted_amount') : '0'),
            Money::of(is_object($balance) ? Row::text($balance, 'respread_out_amount') : '0'),
            $expensedCarried ?? Money::of(is_object($balance) ? Row::text($balance, 'expensed_out_amount') : '0'),
            Money::of((string) $ledger->moved('purchase_charge_share_balances', $key, 'posted_amount')),
            Money::of((string) $ledger->moved('purchase_charge_share_balances', $key, 'respread_out_amount')),
            Money::of((string) $ledger->moved('purchase_charge_share_balances', $key, 'expensed_out_amount')),
        );
        $ordered = Row::text($line, 'ordered_qty_base');
        $cancelled = DB::table('purchase_lines as l')->join('purchases as p', 'p.id', '=', 'l.purchase_id')->where('l.id', $share['line'])->value('p.state') === 'CANCELLED';
        $ended = $cancelled ? $ordered : (string) BigDecimal::min(BigDecimal::of(self::received($line))->plus(Row::text($line, 'closed_qty_base')), BigDecimal::of($ordered));

        return Money::max(Money::zero(), CostSplitter::target($share['share'], $ended, $ordered)->minus($taken));
    }

    private function allocated(CostLedger $ledger, int $chargeId, int $lineId, Money $amount): void
    {
        $ledger->move('purchase_charge_share_balances', ['purchase_charge_id' => $chargeId, 'purchase_line_id' => $lineId], ['posted_amount' => $amount->toString()]);
        $ledger->move('purchase_charge_balances', ['purchase_charge_id' => $chargeId], ['allocated_amount' => $amount->toString()]);
    }

    /** The source bill's cap (C-20; L-43): Σ charges and expenses citing it never above its total. */
    private function cite(CommandContext $context, int $billId, Money $amount): void
    {
        if ($amount->compareTo(Money::of($this->evidence->uncited($context, $billId))) > 0) {
            throw Refused::rejected('C-20', ['bill' => $billId, 'amount' => $amount->toString()]);
        }

        $this->evidence->cite($context, $billId, 'CHARGE_SOURCE_BILL', $amount->toString());
    }

    /**
     * Moves each case by the returned cost and replaced value this posting changed: a closed case whose figures change
     * reopens (AX-29's rule, TASK-031's `CorrectionCases`), except, for a correction's cascade (`keepBalanced`), a
     * purchase return the moves leave balanced (DATABASE §6.3 step 4).
     *
     * @param  Affected  $affected
     */
    private function cased(CommandContext $context, array $affected, bool $keepBalanced = false): void
    {
        foreach (array_unique([...array_keys($affected['cases']), ...array_keys($affected['replaced'])]) as $case) {
            $deltas = array_filter([
                'returned_cost' => ($affected['cases'][$case] ?? Money::zero())->toString(),
                'replaced_value' => ($affected['replaced'][$case] ?? Money::zero())->toString(),
            ], fn (string $delta): bool => ! Money::of($delta)->isZero());

            if ($deltas !== []) {
                $this->cases->post($context, $case, $deltas, self::REOPENED, $keepBalanced);
            }
        }
    }

    /**
     * A correction's cases — a balanced one kept closed (step 4) — and its completed bearer projects revalidated where
     * material, whose cost it moved by a non-zero net (step 5).
     *
     * @param  Affected  $affected
     */
    private function corrected(CommandContext $context, array $affected, string $reference): void
    {
        $this->cased($context, $affected, true);

        foreach ($affected['projects'] as $project => $net) {
            if (! $net->isZero()) {
                $this->revalidation->revalidate($context, $project, $reference);
            }
        }
    }

    /** @return Affected */
    private static function none(): array
    {
        return ['cases' => [], 'replaced' => [], 'projects' => [], 'returns' => []];
    }

    /**
     * @param  Affected  $a
     * @param  Affected  $b
     * @return Affected
     */
    private static function merge(array $a, array $b): array
    {
        foreach (['cases', 'replaced', 'projects'] as $map) {
            foreach ($b[$map] as $id => $amount) {
                $a[$map][$id] = ($a[$map][$id] ?? Money::zero())->plus($amount);
            }
        }

        foreach ($b['returns'] as $key => $amount) {
            $a['returns'][$key] = ($a['returns'][$key] ?? Money::zero())->plus($amount);
        }

        return $a;
    }

    /**
     * The affected sums with a case's returned cost moved by `amount` for the units of one purchase line — the line whose
     * replacements take their share of it (§6.3 step 3); a null case moves nothing.
     *
     * @param  Affected  $affected
     * @return Affected
     */
    private static function returnedMove(array $affected, ?string $case, ?int $line, Money $amount): array
    {
        if ($case === null || $amount->isZero()) {
            return $affected;
        }

        $key = (int) $case.':'.($line ?? 0);
        $affected['returns'][$key] = ($affected['returns'][$key] ?? Money::zero())->plus($amount);

        return self::moved($affected, 'cases', $case, $amount);
    }

    /**
     * The affected sums with `amount` added to one case's or project's entry; a null id adds nothing.
     *
     * @param  Affected  $affected
     * @param  'cases'|'replaced'|'projects'  $map
     * @return Affected
     */
    private static function moved(array $affected, string $map, int|string|null $id, Money $amount): array
    {
        if ($id !== null && ! $amount->isZero()) {
            $affected[$map][(int) $id] = ($affected[$map][(int) $id] ?? Money::zero())->plus($amount);
        }

        return $affected;
    }

    /*
     * Lock-set derivation.
     */

    /**
     * The cascade over holders of cost (§5; §6.3 step 3): each lot's guard, its consumptions' bearer projects and cases;
     * each drop-ship line's project, its return lots and its supplier-return cases; each case with its projects, its
     * replacement lots and its replacement confirmations — every holder read once, in one statement per kind and round,
     * until a round finds nothing new.
     *
     * @param  list<int>  $lots
     * @param  list<int>  $cases
     * @param  list<int>  $dropshipLines
     */
    private static function cascadeLocks(array $lots, array $cases, array $dropshipLines): LockSet
    {
        $set = new LockSet;
        $seen = ['lots' => [], 'cases' => [], 'lines' => []];
        $ints = fn (iterable $ids): array => array_values(array_unique(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, [...$ids])));

        while ($lots !== [] || $cases !== [] || $dropshipLines !== []) {
            $lots = array_values(array_diff($ints($lots), $seen['lots']));
            $dropshipLines = array_values(array_diff($ints($dropshipLines), $seen['lines']));
            $seen['lots'] = [...$seen['lots'], ...$lots];
            $seen['lines'] = [...$seen['lines'], ...$dropshipLines];
            $found = ['lots' => [], 'cases' => $cases, 'lines' => []];

            if ($lots !== []) {
                foreach (DB::table('lot_cost_balances as b')->whereIn('b.lot_id', $lots)->pluck('b.lot_id') as $lot) {
                    $set->add('lot_cost_balances', ['lot_id' => is_numeric($lot) ? (int) $lot : 0]);
                }

                foreach (DB::table('stock_consumptions')->whereIn('lot_id', $lots)->get(['bearer_project_id', 'correction_case_id']) as $consumption) {
                    if (is_numeric($consumption->bearer_project_id ?? null)) {
                        $set->add('projects', ['id' => (int) $consumption->bearer_project_id]);
                    }

                    $found['cases'][] = $consumption->correction_case_id ?? null;
                }
            }

            if ($dropshipLines !== []) {
                foreach (DB::table('dropship_confirmation_lines as l')->join('dropship_confirmations as c', 'c.id', '=', 'l.dropship_confirmation_id')->whereIn('l.id', $dropshipLines)->pluck('c.project_id') as $project) {
                    $set->add('projects', ['id' => is_numeric($project) ? (int) $project : 0]);
                }

                foreach (DB::table('stock_restorations as r')->leftJoin('inventory_lots as l', 'l.restoration_id', '=', 'r.id')->whereIn('r.dropship_confirmation_line_id', $dropshipLines)
                    ->whereIn('r.kind', self::DROPSHIP_AWAY)->get(['l.id as lot_id', 'r.correction_case_id']) as $restoration) {
                    $found['lots'][] = $restoration->lot_id ?? null;
                    $found['cases'][] = $restoration->correction_case_id ?? null;
                }
            }

            $cases = array_values(array_diff($ints(array_filter($found['cases'], fn (mixed $id): bool => is_numeric($id))), $seen['cases']));
            $seen['cases'] = [...$seen['cases'], ...$cases];

            foreach ($cases as $case) {
                $set = $set->union(CorrectionCases::locks($case));
            }

            if ($cases !== []) {
                $found['lots'] = [...$found['lots'], ...DB::table('lot_cost_entries')->whereIn('correction_case_id', $cases)->where('entry_kind', 'REPLACEMENT_BASIS')->pluck('lot_id')->all()];
                $found['lines'] = DB::table('dropship_confirmation_lines')->whereIn('correction_case_id', $cases)->where('is_replacement', true)->pluck('id')->all();
            }

            $lots = $ints(array_filter($found['lots'], fn (mixed $id): bool => is_numeric($id)));
            $dropshipLines = $ints($found['lines']);
            $cases = [];
        }

        return $set;
    }

    /*
     * Reads.
     */

    /** @return array{0: string, 1: Money} the lot's remaining quantity and cost, with what this posting moved so far */
    private function lotBalance(CostLedger $ledger, int $lotId): array
    {
        $balance = DB::table('lot_cost_balances as b')->where('lot_id', $lotId)->first(['remaining_qty_base', 'remaining_cost']);

        if (! is_object($balance)) {
            throw new InvalidArgumentException("Lot {$lotId} has no cost guard yet.");
        }

        $key = ['lot_id' => $lotId];

        return [
            (string) BigDecimal::of(Row::text($balance, 'remaining_qty_base'))->plus($ledger->moved('lot_cost_balances', $key, 'remaining_qty_base')),
            Money::of(Row::text($balance, 'remaining_cost'))->plus(Money::of((string) $ledger->moved('lot_cost_balances', $key, 'remaining_cost'))),
        ];
    }

    /** The lot with the purchase line its units came from — its receipt's, or its drop-ship return's confirmation's — when one. */
    private static function lot(int $lotId): object
    {
        return DB::table('inventory_lots as l')->leftJoin('receiving_lines as r', 'r.id', '=', 'l.receiving_line_id')
            ->leftJoin('stock_restorations as s', 's.id', '=', 'l.restoration_id')->leftJoin('dropship_confirmation_lines as d', 'd.id', '=', 's.dropship_confirmation_line_id')
            ->where('l.id', $lotId)
            ->first(['l.id', 'l.source_company_id', 'l.acquisition_kind', 'l.receiving_line_id', 'l.restoration_id', 'l.acquired_qty_base', 's.dropship_confirmation_line_id',
                DB::raw('coalesce(r.purchase_line_id, d.purchase_line_id) as purchase_line_id')])
            ?? throw new InvalidArgumentException("No lot {$lotId}.");
    }

    private static function consumption(int $consumptionId): object
    {
        return DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->where('c.id', $consumptionId)
            ->first(['c.id', 'c.lot_id', 'c.source_company_id', 'c.qty_base', 'c.kind', 'c.bearer_company_id', 'c.bearer_project_id', 'c.allocation_reason',
                'c.correction_case_id', 'c.case_company_id', 'b.restored_qty_base', 'b.reclassified_qty_base', 'b.substituted_qty_base'])
            ?? throw new InvalidArgumentException("No consumption {$consumptionId} with its balance.");
    }

    /** @return list<object> the lot's consumptions with their balances, in id order */
    private static function consumptionsOf(int $lotId): array
    {
        return array_values(DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->where('c.lot_id', $lotId)->orderBy('c.id')
            ->get(['c.id', 'c.lot_id', 'c.source_company_id', 'c.qty_base', 'c.kind', 'c.bearer_company_id', 'c.bearer_project_id', 'c.allocation_reason',
                'c.correction_case_id', 'c.case_company_id', 'b.restored_qty_base', 'b.reclassified_qty_base', 'b.substituted_qty_base'])->all());
    }

    /** The consumption's units still out of its lot: its quantity less what was restored or substituted back. */
    private static function out(object $consumption): string
    {
        return (string) BigDecimal::of(Row::text($consumption, 'qty_base'))->minus(Row::text($consumption, 'restored_qty_base'))->minus(Row::text($consumption, 'substituted_qty_base'));
    }

    /** The consumption's open quantity: its quantity less restored, reclassified and substituted (§6.2). */
    private static function open(object $consumption): string
    {
        return (string) BigDecimal::of(self::out($consumption))->minus(Row::text($consumption, 'reclassified_qty_base'));
    }

    /** The consumption's net cost of a class: every row of it in that class (§6.2). */
    private static function net(int $consumptionId, string $class): Money
    {
        $net = (string) DB::table('cost_attributions')->where('stock_consumption_id', $consumptionId)->where('cost_class', $class)->sum('amount');

        return Money::of((string) BigDecimal::of($net)->toScale(2));
    }

    /**
     * A dependent's part split over its classes: a dispatch's open units HPP and reclassified units LOSS.
     *
     * @return array<string, Money>
     */
    private static function classesOf(object $consumption, Money $part): array
    {
        $class = self::CLASS_OF[Row::text($consumption, 'kind')];
        $reclassified = Row::text($consumption, 'reclassified_qty_base');

        if ($class !== 'HPP' || ! BigDecimal::of($reclassified)->isPositive()) {
            return [$class => $part];
        }

        return CostSplitter::spread($part, ['HPP' => self::open($consumption), 'LOSS' => $reclassified]);
    }

    /** @return array<string, int|string|null> a lot draw's source, lot, bearer and allocation, from its consumption */
    private static function drawOf(object $consumption): array
    {
        return [
            'stock_consumption_id' => (int) Row::text($consumption, 'id'),
            'lot_id' => (int) Row::text($consumption, 'lot_id'),
            'source_company_id' => (int) Row::text($consumption, 'source_company_id'),
            'bearer_company_id' => (int) Row::text($consumption, 'bearer_company_id'),
            'bearer_project_id' => Row::nullable($consumption, 'bearer_project_id'),
            'allocation_reason' => Row::nullable($consumption, 'allocation_reason'),
            'correction_case_id' => Row::nullable($consumption, 'correction_case_id'),
            'case_company_id' => Row::nullable($consumption, 'case_company_id'),
        ];
    }

    private static function initialOf(int $consumptionId): ?int
    {
        $id = DB::table('cost_attributions')->where('stock_consumption_id', $consumptionId)->where('cause', 'INITIAL')->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private static function purchaseOf(int $lineId): int
    {
        $purchase = DB::table('purchase_lines')->where('id', $lineId)->value('purchase_id');

        return is_numeric($purchase) ? (int) $purchase : throw new InvalidArgumentException("No purchase line {$lineId}.");
    }

    /** The line's guard with its kind. */
    private static function lineBalance(int $lineId): object
    {
        return DB::table('purchase_line_balances as b')->join('purchase_lines as l', 'l.id', '=', 'b.purchase_line_id')->where('b.purchase_line_id', $lineId)
            ->first(['b.purchase_line_id', 'l.kind', 'b.ordered_qty_base', 'b.normal_received_net_qty_base', 'b.dropship_confirmed_net_qty_base', 'b.closed_qty_base',
                'b.goods_value', 'b.noncreditable_tax_value', 'b.posted_goods_value', 'b.posted_noncreditable_tax_value'])
            ?? throw new InvalidArgumentException("Purchase line {$lineId} has no balance.");
    }

    /**
     * The line's received quantity (§6.1): its normal received net plus its drop-ship confirmed net; a non-stock line's
     * whole ordered quantity once its cost is attributed at the purchase (L-18), none before.
     */
    private static function received(object $line): string
    {
        if (Row::text($line, 'kind') === 'NONSTOCK') {
            return self::nonStock([(int) Row::text($line, 'purchase_line_id')]) === [] ? '0' : Row::text($line, 'ordered_qty_base');
        }

        return (string) BigDecimal::of(Row::text($line, 'normal_received_net_qty_base'))->plus(Row::text($line, 'dropship_confirmed_net_qty_base'));
    }

    /**
     * The attributed non-stock lines among the lines, of a purchase not cancelled, each with its quantity and the bearer
     * its INITIAL attribution names (L-18; AX-12 contras a cancelled one).
     *
     * @param  list<int>  $lines
     * @return list<Target>
     */
    private static function nonStock(array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        $rows = DB::table('purchase_lines as l')->join('purchases as p', 'p.id', '=', 'l.purchase_id')
            ->joinSub(DB::table('cost_attributions')->whereNotNull('purchase_line_id')->where('cause', 'INITIAL')
                ->groupBy('purchase_line_id', 'bearer_company_id', 'bearer_project_id')->select(['purchase_line_id', 'bearer_company_id', 'bearer_project_id']),
                'a', 'a.purchase_line_id', '=', 'l.id')
            ->whereIn('l.id', $lines)->where('l.kind', 'NONSTOCK')->where('p.state', '<>', 'CANCELLED')->orderBy('l.id')
            ->get(['l.id', 'l.qty_base', 'a.bearer_company_id', 'a.bearer_project_id']);
        $targets = [];

        foreach ($rows as $row) {
            $targets[] = ['kind' => 'nonstock', 'id' => (int) Row::text($row, 'id'), 'qty' => Row::text($row, 'qty_base'), 'company' => (int) Row::text($row, 'bearer_company_id'),
                'project' => (int) Row::text($row, 'bearer_project_id')];
        }

        return $targets;
    }

    private static function charge(int $chargeId): object
    {
        return DB::table('purchase_charges as c')->join('purchases as p', 'p.id', '=', 'c.purchase_id')->where('c.id', $chargeId)
            ->first(['c.id', 'c.company_id', 'c.purchase_id', 'c.source_bill_evidence_id', 'c.classification', 'c.amount', 'p.project_id'])
            ?? throw new InvalidArgumentException("No purchase charge {$chargeId}.");
    }

    /** @return array<string, mixed> a PURCHASE_CHARGE expense of the charge's purchase project, or its company */
    private static function chargeExpense(object $charge, Money $amount, string $businessDate): array
    {
        return [
            'company_id' => (int) Row::text($charge, 'company_id'),
            'project_id' => Row::nullable($charge, 'project_id'),
            'kind' => 'PURCHASE_CHARGE',
            'category' => 'PURCHASE_CHARGE',
            'amount' => $amount->toString(),
            'business_date' => $businessDate,
            'purchase_charge_id' => (int) Row::text($charge, 'id'),
        ];
    }

    private static function currentVersion(int $chargeId): int
    {
        $version = DB::table('purchase_charge_line_shares')->where('purchase_charge_id', $chargeId)->max('allocation_version');

        return is_numeric($version) ? (int) $version : throw new InvalidArgumentException("Charge {$chargeId} has no shares.");
    }

    /** @return list<array{id: int, line: int, share: Money}> the version's shares in line order */
    private static function versionShares(int $chargeId, int $version): array
    {
        return array_values(DB::table('purchase_charge_line_shares as s')->join('purchase_lines as l', 'l.id', '=', 's.purchase_line_id')
            ->where('s.purchase_charge_id', $chargeId)->where('s.allocation_version', $version)->orderBy('l.line_no')->orderBy('s.id')
            ->get(['s.id', 's.purchase_line_id', 's.share_amount'])
            ->map(fn (object $row): array => ['id' => (int) Row::text($row, 'id'), 'line' => (int) Row::text($row, 'purchase_line_id'), 'share' => Money::of(Row::text($row, 'share_amount'))])
            ->all());
    }

    /** @return list<array{charge: int, line: int, id: int, version: int, share: Money, posted: Money, respread_out: Money, expensed_out: Money}> */
    private static function sharesOf(int $lineId): array
    {
        return array_values(DB::table('purchase_charge_share_balances as b')
            ->join('purchase_charge_line_shares as s', fn (JoinClause $join) => $join->on('s.purchase_charge_id', '=', 'b.purchase_charge_id')
                ->on('s.purchase_line_id', '=', 'b.purchase_line_id')->on('s.allocation_version', '=', 'b.allocation_version'))
            ->where('b.purchase_line_id', $lineId)->orderBy('b.purchase_charge_id')
            ->get(['b.purchase_charge_id', 'b.purchase_line_id', 's.id', 'b.allocation_version', 'b.share_amount', 'b.posted_amount', 'b.respread_out_amount', 'b.expensed_out_amount'])
            ->map(fn (object $row): array => [
                'charge' => (int) Row::text($row, 'purchase_charge_id'), 'line' => (int) Row::text($row, 'purchase_line_id'), 'id' => (int) Row::text($row, 'id'),
                'version' => (int) Row::text($row, 'allocation_version'), 'share' => Money::of(Row::text($row, 'share_amount')), 'posted' => Money::of(Row::text($row, 'posted_amount')),
                'respread_out' => Money::of(Row::text($row, 'respread_out_amount')), 'expensed_out' => Money::of(Row::text($row, 'expensed_out_amount')),
            ])->all());
    }

    /**
     * The received quantity of lines that can take a cost (§6.1; §6.3 step 1): each non-replacement receipt lot by its
     * net units — acquired less receipt reversals —, each non-replacement drop-ship line by its quantity less its
     * confirmation reversals — its returns still received, the units held by their return lot or case —, and each
     * attributed non-stock line by its quantity; lots first, then drop-ship lines, then non-stock lines, each in id
     * order; holders with no units left are not targets.
     *
     * @param  list<int>  $lines
     * @return list<Target>
     */
    private static function targets(array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        $targets = [];
        $lots = DB::table('inventory_lots as l')->join('receiving_lines as r', 'r.id', '=', 'l.receiving_line_id')
            ->whereIn('r.purchase_line_id', $lines)->where('r.receipt_kind', '<>', 'REPLACEMENT')->orderBy('l.id')
            ->selectRaw(<<<'SQL'
                l.id, l.acquired_qty_base + coalesce((select sum(e.qty_delta_base) from stock_movement_entries e join stock_movements m on m.id = e.stock_movement_id
                    where e.lot_id = l.id and m.movement_type = 'RECEIPT_REVERSAL'), 0) as net
                SQL)->get();

        foreach ($lots as $lot) {
            if (BigDecimal::of(Row::text($lot, 'net'))->isPositive()) {
                $targets[] = ['kind' => 'lot', 'id' => (int) Row::text($lot, 'id'), 'qty' => Row::text($lot, 'net')];
            }
        }

        $dropship = DB::table('dropship_confirmation_lines as l')->join('dropship_confirmations as c', 'c.id', '=', 'l.dropship_confirmation_id')
            ->whereIn('l.purchase_line_id', $lines)->where('l.is_replacement', false)->orderBy('l.id')
            ->selectRaw(<<<'SQL'
                l.id, l.company_id, c.project_id, l.purchase_line_id, l.qty_base - coalesce((select sum(r.qty_base) from stock_restorations r
                    where r.dropship_confirmation_line_id = l.id and r.kind = 'DROPSHIP_CONFIRMATION_REVERSAL'
                    and not exists (select 1 from stock_restorations e where e.reverses_restoration_id = r.id)), 0) as net
                SQL)->get();

        foreach ($dropship as $line) {
            if (BigDecimal::of(Row::text($line, 'net'))->isPositive()) {
                $targets[] = ['kind' => 'dropship', 'id' => (int) Row::text($line, 'id'), 'qty' => Row::text($line, 'net'), 'company' => (int) Row::text($line, 'company_id'),
                    'project' => (int) Row::text($line, 'project_id'), 'line' => (int) Row::text($line, 'purchase_line_id')];
            }
        }

        return [...$targets, ...self::nonStock($lines)];
    }

    /**
     * A drop-ship line's units moved away and not undone (§5.7; §5.3): each return to the warehouse with its lot, less
     * what a reversal of its return receipt brought back to the line, each return to the supplier with its case, not
     * reversed by a DROPSHIP_ERROR_REVERSAL, in id order.
     *
     * @return array<string, array{restoration: int, qty: string, lot: ?int, case: ?int, case_company: ?int}>
     */
    private static function dropshipAway(int $dropshipLineId): array
    {
        $rows = DB::table('stock_restorations as r')->leftJoin('inventory_lots as l', 'l.restoration_id', '=', 'r.id')
            ->where('r.dropship_confirmation_line_id', $dropshipLineId)->whereIn('r.kind', self::DROPSHIP_AWAY)
            ->whereNotExists(fn (Builder $reversal) => $reversal->selectRaw('1')->from('stock_restorations as e')->whereColumn('e.reverses_restoration_id', 'r.id'))
            ->orderBy('r.id')
            ->selectRaw(<<<'SQL'
                r.id, r.kind, r.correction_case_id, r.case_company_id, l.id as lot_id,
                r.qty_base + coalesce((select sum(e.qty_delta_base) from stock_movement_entries e join stock_movements m on m.id = e.stock_movement_id
                    where e.lot_id = l.id and m.movement_type = 'MOVEMENT_REVERSAL' and m.reverses_movement_id = r.stock_movement_id), 0) as qty
                SQL)->get();
        $away = [];

        foreach ($rows as $row) {
            $lot = Row::nullable($row, 'lot_id');

            if (Row::text($row, 'kind') === 'DROPSHIP_RETURN' && $lot === null) {
                throw new InvalidArgumentException('Drop-ship return '.Row::text($row, 'id').' has no return lot.');
            }

            if (! BigDecimal::of(Row::text($row, 'qty'))->isPositive()) {
                continue;
            }

            $case = Row::nullable($row, 'correction_case_id');
            $caseCompany = Row::nullable($row, 'case_company_id');
            $away['r'.Row::text($row, 'id')] = ['restoration' => (int) Row::text($row, 'id'), 'qty' => Row::text($row, 'qty'), 'lot' => $lot === null ? null : (int) $lot,
                'case' => $case === null ? null : (int) $case, 'case_company' => $caseCompany === null ? null : (int) $caseCompany];
        }

        return $away;
    }

    /**
     * The replacements a case created for one purchase line's returned units (§6.3 step 3; PX-03): the replacement lots
     * received on the line whose REPLACEMENT_BASIS names the case, by their units net of receipt reversals, and the
     * line's replacement drop-ship lines for the case, by quantity less confirmation reversals, keyed in id order.
     *
     * @return array<string, Target>
     */
    private static function replacementsOf(int $caseId, int $lineId): array
    {
        $replacements = [];
        $lots = DB::table('lot_cost_entries as e')->join('inventory_lots as l', 'l.id', '=', 'e.lot_id')->join('receiving_lines as r', 'r.id', '=', 'l.receiving_line_id')
            ->where('e.correction_case_id', $caseId)->where('e.entry_kind', 'REPLACEMENT_BASIS')->where('r.purchase_line_id', $lineId)->orderBy('l.id')
            ->selectRaw(<<<'SQL'
                l.id, l.acquired_qty_base + coalesce((select sum(m.qty_delta_base) from stock_movement_entries m join stock_movements v on v.id = m.stock_movement_id
                    where m.lot_id = l.id and v.movement_type = 'RECEIPT_REVERSAL'), 0) as net
                SQL)->get();

        foreach ($lots as $lot) {
            if (BigDecimal::of(Row::text($lot, 'net'))->isPositive()) {
                $replacements['l'.Row::text($lot, 'id')] = ['kind' => 'lot', 'id' => (int) Row::text($lot, 'id'), 'qty' => Row::text($lot, 'net')];
            }
        }

        foreach (DB::table('dropship_confirmation_lines as l')->join('dropship_confirmations as c', 'c.id', '=', 'l.dropship_confirmation_id')->where('l.correction_case_id', $caseId)
            ->where('l.purchase_line_id', $lineId)->where('l.is_replacement', true)->orderBy('l.id')
            ->selectRaw(<<<'SQL'
                l.id, l.company_id, c.project_id, l.purchase_line_id, l.qty_base - coalesce((select sum(r.qty_base) from stock_restorations r
                    where r.dropship_confirmation_line_id = l.id and r.kind = 'DROPSHIP_CONFIRMATION_REVERSAL'
                    and not exists (select 1 from stock_restorations e where e.reverses_restoration_id = r.id)), 0) as net
                SQL)->get() as $line) {
            if (BigDecimal::of(Row::text($line, 'net'))->isPositive()) {
                $replacements['d'.Row::text($line, 'id')] = ['kind' => 'dropship', 'id' => (int) Row::text($line, 'id'), 'qty' => Row::text($line, 'net'),
                    'company' => (int) Row::text($line, 'company_id'), 'project' => (int) Row::text($line, 'project_id'), 'line' => (int) Row::text($line, 'purchase_line_id')];
            }
        }

        return $replacements;
    }

    /**
     * The units a case returned to the supplier from one purchase line: its purchase returns' units still out of lots that
     * line received — or of return lots of its drop-ship lines — and its drop-ship supplier returns on that line not undone.
     */
    private static function returnedUnits(int $caseId, int $lineId): string
    {
        $row = DB::selectOne(<<<'SQL'
            select coalesce((select sum(b.qty_base - b.restored_qty_base - b.substituted_qty_base) from stock_consumptions c
                    join stock_consumption_balances b on b.stock_consumption_id = c.id
                    join inventory_lots l on l.id = c.lot_id
                    left join receiving_lines r on r.id = l.receiving_line_id
                    left join stock_restorations s on s.id = l.restoration_id
                    left join dropship_confirmation_lines d on d.id = s.dropship_confirmation_line_id
                    where c.correction_case_id = ? and c.kind = 'PURCHASE_RETURN' and coalesce(r.purchase_line_id, d.purchase_line_id) = ?), 0)
                + coalesce((select sum(r.qty_base) from stock_restorations r join dropship_confirmation_lines d on d.id = r.dropship_confirmation_line_id
                    where r.correction_case_id = ? and r.kind = 'DROPSHIP_SUPPLIER_RETURN' and d.purchase_line_id = ?
                    and not exists (select 1 from stock_restorations e where e.reverses_restoration_id = r.id)), 0) as units
            SQL, [$caseId, $lineId, $caseId, $lineId]);

        return is_object($row) ? Row::text($row, 'units') : '0';
    }

    /**
     * @param  list<int>  $lines
     * @return list<int> the non-replacement receipt lots of the lines, in id order
     */
    private static function lotsOfLines(array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        return array_values(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, DB::table('inventory_lots as l')->join('receiving_lines as r', 'r.id', '=', 'l.receiving_line_id')
            ->whereIn('r.purchase_line_id', $lines)->where('r.receipt_kind', '<>', 'REPLACEMENT')->orderBy('l.id')->pluck('l.id')->all()));
    }

    /**
     * The lot's net cost by component — GOODS, NONCREDITABLE_TAX, then each charge's share and re-spread rows of its
     * current postings —, the receipt reversal's split order; components with nothing left are not listed.
     *
     * @return list<array{component: string, net: Money, references: array<string, int>, share_line?: int}>
     */
    private static function components(int $lotId): array
    {
        // A charge row is keyed by its charge, share or re-spread source and version, whatever line it also cites.
        $rows = DB::table('lot_cost_entries as e')->leftJoin('purchase_charge_line_shares as s', 's.id', '=', 'e.purchase_charge_line_share_id')->where('e.lot_id', $lotId)
            ->groupByRaw("e.cost_component, case when e.cost_component in ('GOODS', 'NONCREDITABLE_TAX') then e.purchase_line_id end, e.purchase_charge_id,
                e.purchase_charge_line_share_id, e.respread_from_purchase_line_id, e.allocation_version, s.purchase_line_id")
            ->selectRaw("e.cost_component, case when e.cost_component in ('GOODS', 'NONCREDITABLE_TAX') then e.purchase_line_id end as purchase_line_id, e.purchase_charge_id,
                e.purchase_charge_line_share_id, e.respread_from_purchase_line_id, e.allocation_version, s.purchase_line_id as share_line, sum(e.amount) as net")
            ->get();
        $components = [];

        foreach ($rows as $row) {
            $net = Money::of(Row::text($row, 'net'));

            if (! $net->isPositive()) {
                continue;
            }

            $component = Row::text($row, 'cost_component');
            $references = array_map('intval', array_filter([
                'purchase_line_id' => in_array($component, ['GOODS', 'NONCREDITABLE_TAX'], true) ? Row::nullable($row, 'purchase_line_id') : null,
                'purchase_charge_id' => Row::nullable($row, 'purchase_charge_id'),
                'purchase_charge_line_share_id' => Row::nullable($row, 'purchase_charge_line_share_id'),
                'respread_from_purchase_line_id' => Row::nullable($row, 'respread_from_purchase_line_id'),
                'allocation_version' => Row::nullable($row, 'allocation_version'),
            ], fn (?string $value): bool => $value !== null));
            $entry = ['component' => $component, 'net' => $net, 'references' => $references];

            if (Row::nullable($row, 'share_line') !== null) {
                $entry['share_line'] = (int) Row::text($row, 'share_line');
            }

            $components[] = $entry;
        }

        usort($components, fn (array $a, array $b): int => [self::COMPONENT_ORDER[$a['component']], $a['references']['purchase_charge_id'] ?? 0, $a['references']['purchase_charge_line_share_id'] ?? 0, $a['references']['respread_from_purchase_line_id'] ?? 0]
            <=> [self::COMPONENT_ORDER[$b['component']], $b['references']['purchase_charge_id'] ?? 0, $b['references']['purchase_charge_line_share_id'] ?? 0, $b['references']['respread_from_purchase_line_id'] ?? 0]);

        return $components;
    }

    /**
     * A direct source's net cost of a class by component — GOODS, NONCREDITABLE_TAX, BASIS, then each charge's share and
     * re-spread parts by charge —, the order a split over them takes; parts with nothing left are not listed.
     *
     * @return list<array{component: string, net: Money, references: array<string, int>}>
     */
    private static function directGroups(string $source, int $id, string $class): array
    {
        $rows = DB::table('cost_attributions')->where($source, $id)->where('cost_class', $class)->whereNull('stock_consumption_id')
            ->groupBy('cost_component', 'purchase_charge_id', 'purchase_charge_line_share_id', 'respread_from_purchase_line_id', 'allocation_version')
            ->selectRaw('cost_component, purchase_charge_id, purchase_charge_line_share_id, respread_from_purchase_line_id, allocation_version, sum(amount) as net')->get();
        $groups = [];

        foreach ($rows as $row) {
            $net = Money::of(Row::text($row, 'net'));

            if ($net->isPositive()) {
                $groups[] = ['component' => Row::text($row, 'cost_component'), 'net' => $net, 'references' => array_map('intval', array_filter([
                    'purchase_charge_id' => Row::nullable($row, 'purchase_charge_id'),
                    'purchase_charge_line_share_id' => Row::nullable($row, 'purchase_charge_line_share_id'),
                    'respread_from_purchase_line_id' => Row::nullable($row, 'respread_from_purchase_line_id'),
                    'allocation_version' => Row::nullable($row, 'allocation_version'),
                ], fn (?string $value): bool => $value !== null))];
            }
        }

        usort($groups, fn (array $a, array $b): int => [self::COMPONENT_ORDER[$a['component']], $a['references']['purchase_charge_id'] ?? 0, $a['references']['purchase_charge_line_share_id'] ?? 0, $a['references']['respread_from_purchase_line_id'] ?? 0]
            <=> [self::COMPONENT_ORDER[$b['component']], $b['references']['purchase_charge_id'] ?? 0, $b['references']['purchase_charge_line_share_id'] ?? 0, $b['references']['respread_from_purchase_line_id'] ?? 0]);

        return $groups;
    }

    private static function dropshipLine(int $dropshipLineId): object
    {
        return DB::table('dropship_confirmation_lines as l')->join('dropship_confirmations as c', 'c.id', '=', 'l.dropship_confirmation_id')->where('l.id', $dropshipLineId)
            ->first(['l.id', 'l.purchase_line_id', 'l.company_id', 'c.project_id', 'l.is_replacement', 'l.correction_case_id'])
            ?? throw new InvalidArgumentException("No drop-ship confirmation line {$dropshipLineId}.");
    }
}
