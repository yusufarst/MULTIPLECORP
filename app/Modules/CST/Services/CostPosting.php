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
 * Callers record their own facts first: the receiving lot and its quantity on the line, the consumption and its
 * movement, the restoration (before its quantity moves the consumption's balance), the reversal movement, the closed
 * quantity, the adjustment and the corrected value, the charge and its shares.
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

    /** The basis kinds a lot other than a normal receipt carries (DATABASE §6.3), with the acquisition each needs. */
    private const BASIS_KINDS = ['OPENING_COST', 'SURPLUS_FLAGGED_BASIS', 'DROPSHIP_RETURN_BASIS', 'REPLACEMENT_BASIS'];

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
     * LK-23 for the lots, with LK-01 for the bearer projects and LK-10 for the cases of the consumptions drawn from them.
     *
     * @param  list<int>  $lotIds
     */
    public static function lotLocks(array $lotIds): LockSet
    {
        $set = new LockSet;

        foreach (array_values(array_unique($lotIds)) as $lot) {
            if (DB::table('lot_cost_balances')->where('lot_id', $lot)->exists()) {
                $set->add('lot_cost_balances', ['lot_id' => $lot]);
            }
        }

        $consumptions = $lotIds === [] ? [] : DB::table('stock_consumptions')->whereIn('lot_id', $lotIds)->get(['bearer_project_id', 'correction_case_id'])->all();

        foreach ($consumptions as $consumption) {
            if (is_numeric($consumption->bearer_project_id ?? null)) {
                $set->add('projects', ['id' => (int) $consumption->bearer_project_id]);
            }

            if (is_numeric($consumption->correction_case_id ?? null)) {
                $set->add('correction_cases', ['id' => (int) $consumption->correction_case_id]);
            }
        }

        return $set;
    }

    /**
     * LK-21 for the purchase, its lines and their guards, LK-22 for its charges and their shares, LK-23 and the
     * dependents of the lots of its lines, the purchase's project and the projects of its drop-ship confirmations.
     */
    public static function purchaseLocks(int $purchaseId): LockSet
    {
        $lines = array_values(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, DB::table('purchase_lines')->where('purchase_id', $purchaseId)->orderBy('id')->pluck('id')->all()));
        $set = self::lotLocks(self::lotsOfLines($lines));
        $set->add('purchases', ['id' => $purchaseId]);
        $project = DB::table('purchases')->where('id', $purchaseId)->value('project_id');

        if (is_numeric($project)) {
            $set->add('projects', ['id' => (int) $project]);
        }

        foreach ($lines as $line) {
            $set->add('purchase_lines', ['id' => $line]);

            if (DB::table('purchase_line_balances')->where('purchase_line_id', $line)->exists()) {
                $set->add('purchase_line_balances', ['purchase_line_id' => $line]);
            }
        }

        foreach (DB::table('purchase_charges')->where('purchase_id', $purchaseId)->orderBy('id')->pluck('id') as $charge) {
            if (DB::table('purchase_charge_balances')->where('purchase_charge_id', $charge)->exists()) {
                $set->add('purchase_charge_balances', ['purchase_charge_id' => is_numeric($charge) ? (int) $charge : 0]);
            }
        }

        foreach (DB::table('purchase_charge_share_balances')->whereIn('purchase_line_id', $lines)->get(['purchase_charge_id', 'purchase_line_id']) as $share) {
            $set->add('purchase_charge_share_balances', ['purchase_charge_id' => (int) Row::text($share, 'purchase_charge_id'), 'purchase_line_id' => (int) Row::text($share, 'purchase_line_id')]);
        }

        foreach (DB::table('dropship_confirmation_lines as l')->join('dropship_confirmations as c', 'c.id', '=', 'l.dropship_confirmation_id')->whereIn('l.purchase_line_id', $lines)->pluck('c.project_id') as $dropshipProject) {
            $set->add('projects', ['id' => is_numeric($dropshipProject) ? (int) $dropshipProject : 0]);
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
     * A drop-ship confirmation line's actual cost attributed directly (DATABASE §6.1; C-25): GOODS, NONCREDITABLE_TAX
     * and each current charge share at the line's cumulative targets, INITIAL rows on the confirmation's project.
     */
    public function dropshipConfirmed(CommandContext $context, int $dropshipLineId, string $businessDate): ReceiptPosted
    {
        $dropship = self::dropshipLine($dropshipLineId);
        $lineId = (int) Row::text($dropship, 'purchase_line_id');
        $context->require(self::purchaseLocks(self::purchaseOf($lineId)));

        return $this->direct($context, $lineId, ['dropship_confirmation_line_id' => $dropshipLineId], (int) Row::text($dropship, 'company_id'), (int) Row::text($dropship, 'project_id'), $businessDate);
    }

    /** A non-stock purchase line's actual cost attributed directly to its project, once (DATABASE §6.1; C-25). */
    public function nonStockLine(CommandContext $context, int $purchaseLineId, int $bearerProjectId, string $businessDate): ReceiptPosted
    {
        $line = DB::table('purchase_lines')->where('id', $purchaseLineId)->first(['kind', 'company_id', 'purchase_id']);

        if (! is_object($line) || Row::text($line, 'kind') !== 'NONSTOCK') {
            throw new InvalidArgumentException("Purchase line {$purchaseLineId} is no non-stock line.");
        }

        $context->require(self::purchaseLocks((int) Row::text($line, 'purchase_id')));

        return $this->direct($context, $purchaseLineId, ['purchase_line_id' => $purchaseLineId], (int) Row::text($line, 'company_id'), $bearerProjectId, $businessDate);
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
        $context->require(self::lotLocks([$lot]));
        $ledger = new CostLedger($context);
        [$remainingQty, $remainingCost] = $this->lotBalance($ledger, $lot);
        $qty = Row::text($consumption, 'qty_base');
        $amount = CostSplitter::draw($remainingCost, $qty, $remainingQty);
        $class = self::CLASS_OF[Row::text($consumption, 'kind')];
        $ledger->move('lot_cost_balances', ['lot_id' => $lot], ['remaining_qty_base' => '-'.$qty, 'remaining_cost' => $amount->negated()->toString()]);
        $id = $amount->isZero() ? null : $ledger->attribution([...self::drawOf($consumption), 'cost_class' => $class, 'cause' => 'INITIAL', 'amount' => $amount->toString(), 'business_date' => $businessDate]);
        $ledger->flush();
        $this->returned($context, [$consumption], [$class === 'SUPPLIER_RETURN' ? $amount : Money::zero()]);

        return $id;
    }

    /**
     * A restoration's contra (DATABASE §6.2; SF-RESTORE; L-31): the proportional share of the consumption's net cost of
     * the class the restoration reverses over its open quantity before this restoration, the event zeroing the open
     * quantity taking exactly what is left; the units re-enter their lot at exactly the contra, and the row keeps the
     * consumption's bearer and allocation, so the allocation is reversed with its consumption (L-44).
     */
    public function restore(CommandContext $context, int $restorationId, string $businessDate): ?int
    {
        $restoration = DB::table('stock_restorations')->where('id', $restorationId)->first(['stock_consumption_id', 'qty_base', 'kind', 'correction_case_id', 'case_company_id']);

        if (! is_object($restoration) || ! isset(self::RESTORATION[Row::text($restoration, 'kind')]) || Row::nullable($restoration, 'stock_consumption_id') === null) {
            throw new InvalidArgumentException("Restoration {$restorationId} reverses no lot consumption's cost here.");
        }

        [$class, $cause] = self::RESTORATION[Row::text($restoration, 'kind')];
        $consumption = self::consumption((int) Row::text($restoration, 'stock_consumption_id'));
        $lot = (int) Row::text($consumption, 'lot_id');
        $context->require(self::lotLocks([$lot]));
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

        $context->require(self::lotLocks([(int) Row::text($consumption, 'lot_id')]));
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

        $context->require(self::lotLocks([(int) Row::text($consumption, 'lot_id')]));
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
     * received — each lot's whole share as a CHARGE_SHARE entry, the part of units already consumed drawn as LATE_CHARGE
     * — and re-spread for remainders already ended.
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

        $affected = $this->postShares($context, $ledger, $chargeId, $version, 'CHARGE_SHARE', 'LATE_CHARGE', $businessDate);
        $ledger->flush();
        $this->returned($context, $affected['consumptions'], $affected['returned']);
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
        $affected = ['consumptions' => [], 'returned' => []];

        foreach (self::sharesOf($purchaseLineId) as $share) {
            $ended = $this->endedPending($ledger, $share, self::lineBalance($purchaseLineId));

            if ($ended->isPositive()) {
                $affected = self::merge($affected, $this->respread($context, $ledger, $share['charge'], $purchaseLineId, $share['version'], $ended, $businessDate));
            }
        }

        $ledger->flush();
        $this->returned($context, $affected['consumptions'], $affected['returned']);
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
            throw new InvalidArgumentException("Lot {$lotId} is no receipt's lot.");
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

        $affected = ['consumptions' => [], 'returned' => []];

        foreach ($respreads as [$charge, $source, $version, $part]) {
            $affected = self::merge($affected, $this->respread($context, $ledger, $charge, $source, $version, $part, $businessDate));
        }

        $ledger->flush();
        $this->returned($context, $affected['consumptions'], $affected['returned']);

        return $posted;
    }

    /*
     * Corrections (AX-34; CM-17; CM-35; DATABASE §6.3).
     */

    /**
     * A price or tax correction of a line (DATABASE §6.3 step 1; C-57): the line's cumulative target for its current
     * received quantity at the corrected value less what is posted, spread over the line's lots and non-replacement
     * drop-ship lines in id order by cumulative rounding of their net quantities as COST_CORRECTION rows, each lot's part
     * re-attributed to its dependents by their net quantities (step 3), the cases reopened (step 4) and the completed
     * projects revalidated (step 5). The caller has moved the line's value by the adjustment's delta.
     */
    public function lineCorrected(CommandContext $context, int $adjustmentId, string $businessDate): ReceiptPosted
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
        $delta = CostSplitter::target(Money::of(Row::text($line, $value)), self::received($line), Row::text($line, 'ordered_qty_base'))->minus(Money::of(Row::text($line, $postedColumn)));
        $ledger = new CostLedger($context);
        $affected = ['consumptions' => [], 'returned' => []];

        if (! $delta->isZero()) {
            $references = ['purchase_line_id' => $lineId, 'purchase_line_adjustment_id' => $adjustmentId];
            $affected = $this->spreadOverLine($context, $ledger, $lineId, $delta, ['entry_kind' => 'COST_CORRECTION', 'cost_component' => $component, ...$references],
                $component, [], 'COST_CORRECTION', $businessDate);
        }

        $ledger->flush();
        $this->corrected($context, $affected, "purchase_line_adjustment:{$adjustmentId}");

        return $component === 'GOODS' ? new ReceiptPosted($delta, Money::zero()) : new ReceiptPosted(Money::zero(), $delta);
    }

    /**
     * A charge's amount or basis corrected (DATABASE §6.3 step 2; CM-35; C-20; C-21): the amount's delta cited on the
     * source bill (C-20) and moved on the charge's guard; every CHARGE posting of the old allocation version contra'd —
     * the lots' entries and their dependents' shares, the direct rows — and the charge re-posted under the new version by
     * AX-29's rules: received units in lots, consumed units as draws, ended remainders re-spread, open remainders
     * pending; an expensed share stays expensed. The shares' guards take the new version. PUR has recorded the
     * adjustment, when the amount changes, and the new version's shares first.
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

        $old = DB::table('purchase_charge_share_balances')->where('purchase_charge_id', $chargeId)->get()->keyBy(fn (object $row): int => (int) Row::text($row, 'purchase_line_id'));
        $version = self::currentVersion($chargeId);
        $affected = $this->contraVersion($context, $ledger, $chargeId, $businessDate);

        foreach (self::versionShares($chargeId, $version) as $share) {
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

            if ($expensed->compareTo($share['share']) > 0) {
                throw Refused::rejected('C-21', ['reason' => 'expensed_above_share', 'line' => $share['line']]);
            }
        }

        // The new version posts against the reset guards, read with this posting's deltas, so each guard moves once.
        $affected = self::merge($affected, $this->postShares($context, $ledger, $chargeId, $version, 'CHARGE_SHARE', 'COST_CORRECTION', $businessDate));
        $ledger->flush();
        $this->corrected($context, $affected, 'purchase_charge:'.$chargeId.($adjustmentId === null ? '' : ":adjustment:{$adjustmentId}"));
    }

    /*
     * Posting rules shared by the entry points.
     */

    /**
     * Posts each line's share of the charge's version for what the line has received — its lots' CHARGE_SHARE entries
     * with their dependents' draws of `drawCause`, its drop-ship lines' direct rows — and re-spreads what is pending for
     * its ended remainder.
     *
     * @return array{consumptions: list<object>, returned: list<Money>, projects?: array<int, true>}
     */
    private function postShares(CommandContext $context, CostLedger $ledger, int $chargeId, int $version, string $entryKind, string $drawCause, string $businessDate): array
    {
        $affected = ['consumptions' => [], 'returned' => []];

        foreach (self::versionShares($chargeId, $version) as $share) {
            $line = self::lineBalance($share['line']);
            $key = ['purchase_charge_id' => $chargeId, 'purchase_line_id' => $share['line']];
            $balance = DB::table('purchase_charge_share_balances')->where($key)->first(['posted_amount', 'respread_out_amount', 'expensed_out_amount']);
            $posted = Money::of(is_object($balance) ? Row::text($balance, 'posted_amount') : '0')->plus(Money::of((string) $ledger->moved('purchase_charge_share_balances', $key, 'posted_amount')));
            $respread = Money::of(is_object($balance) ? Row::text($balance, 'respread_out_amount') : '0')->plus(Money::of((string) $ledger->moved('purchase_charge_share_balances', $key, 'respread_out_amount')));
            $expensed = Money::of(is_object($balance) ? Row::text($balance, 'expensed_out_amount') : '0');
            $received = self::received($line);
            $amount = BigDecimal::of($received)->isZero() ? Money::zero() : ChargeAllocator::posting($share['share'], $received, Row::text($line, 'ordered_qty_base'), $posted, $respread, $expensed);

            if ($amount->isPositive()) {
                $affected = self::merge($affected, $this->spreadOverLine($context, $ledger, $share['line'], $amount, [
                    'entry_kind' => $entryKind, 'cost_component' => 'CHARGE', 'purchase_charge_id' => $chargeId, 'purchase_charge_line_share_id' => $share['id'], 'allocation_version' => $version,
                ], 'CHARGE', ['purchase_charge_id' => $chargeId, 'purchase_charge_line_share_id' => $share['id'], 'allocation_version' => $version], $drawCause, $businessDate));
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
     * Re-spreads `amount` of the charge's share on `sourceLine` over the received quantity of the charge — every lot
     * of its lines and drop-ship line by net quantity, by cumulative rounding — as CHARGE_RESPREAD, moving the source
     * line's re-spread total; with nothing received, a PURCHASE_CHARGE expense citing the line moves its expensed total.
     *
     * @return array{consumptions: list<object>, returned: list<Money>, projects?: array<int, true>}
     */
    private function respread(CommandContext $context, CostLedger $ledger, int $chargeId, int $sourceLine, int $version, Money $amount, string $businessDate): array
    {
        $key = ['purchase_charge_id' => $chargeId, 'purchase_line_id' => $sourceLine];
        $lines = array_map(fn (array $share): int => $share['line'], self::versionShares($chargeId, $version));
        $targets = self::targets($ledger, $lines);

        if ($targets === []) {
            $charge = self::charge($chargeId);
            $ledger->expense([...self::chargeExpense($charge, $amount, $businessDate), 'purchase_line_id' => $sourceLine]);
            $ledger->move('purchase_charge_share_balances', $key, ['expensed_out_amount' => $amount->toString()]);
            $ledger->move('purchase_charge_balances', ['purchase_charge_id' => $chargeId], ['expensed_amount' => $amount->toString()]);

            return ['consumptions' => [], 'returned' => []];
        }

        $references = ['purchase_charge_id' => $chargeId, 'respread_from_purchase_line_id' => $sourceLine, 'allocation_version' => $version];
        $affected = $this->spreadOverTargets($context, $ledger, $targets, $amount, ['entry_kind' => 'CHARGE_RESPREAD', 'cost_component' => 'CHARGE', ...$references],
            'CHARGE', $references, 'CHARGE_RESPREAD', 'CHARGE_RESPREAD', $businessDate);
        $ledger->move('purchase_charge_share_balances', $key, ['respread_out_amount' => $amount->toString()]);
        $ledger->move('purchase_charge_balances', ['purchase_charge_id' => $chargeId], ['allocated_amount' => $amount->toString()]);

        return $affected;
    }

    /**
     * Contras every CHARGE posting of the charge's current allocation version (CM-35): each lot's net entries of that
     * version as one COST_CORRECTION row per share or re-spread source, its dependents re-attributed by their net
     * quantities, and each direct row's net per component and source. The version's guards are reset by the caller.
     *
     * @return array{consumptions: list<object>, returned: list<Money>, projects?: array<int, true>}
     */
    private function contraVersion(CommandContext $context, CostLedger $ledger, int $chargeId, string $businessDate): array
    {
        $affected = ['consumptions' => [], 'returned' => []];
        $version = DB::table('purchase_charge_share_balances')->where('purchase_charge_id', $chargeId)->max('allocation_version');

        if (! is_numeric($version)) {
            return $affected;
        }

        $groups = DB::table('lot_cost_entries')->where('purchase_charge_id', $chargeId)->where('allocation_version', (int) $version)
            ->groupBy('lot_id', 'purchase_charge_line_share_id', 'respread_from_purchase_line_id')->orderBy('lot_id')
            ->selectRaw('lot_id, purchase_charge_line_share_id, respread_from_purchase_line_id, sum(amount) as net')->get();

        foreach ($groups as $group) {
            $net = Money::of(Row::text($group, 'net'));

            if ($net->isZero()) {
                continue;
            }

            $references = array_filter(['purchase_charge_id' => $chargeId, 'allocation_version' => (int) $version,
                'purchase_charge_line_share_id' => Row::nullable($group, 'purchase_charge_line_share_id'), 'respread_from_purchase_line_id' => Row::nullable($group, 'respread_from_purchase_line_id')],
                fn (mixed $value): bool => $value !== null);
            $affected = self::merge($affected, $this->spreadOnLot($context, $ledger, (int) Row::text($group, 'lot_id'), $net->negated(),
                ['entry_kind' => 'COST_CORRECTION', 'cost_component' => 'CHARGE', ...$references], 'COST_CORRECTION', $businessDate));
        }

        $direct = DB::table('cost_attributions')->where('purchase_charge_id', $chargeId)->where('allocation_version', (int) $version)->whereNull('stock_consumption_id')
            ->groupBy('dropship_confirmation_line_id', 'purchase_line_id', 'purchase_charge_line_share_id', 'respread_from_purchase_line_id', 'bearer_company_id', 'bearer_project_id')
            ->selectRaw('dropship_confirmation_line_id, purchase_line_id, purchase_charge_line_share_id, respread_from_purchase_line_id, bearer_company_id, bearer_project_id, sum(amount) as net')->get();

        foreach ($direct as $row) {
            $net = Money::of(Row::text($row, 'net'));

            if ($net->isZero()) {
                continue;
            }

            $ledger->attribution(array_filter([
                'cost_class' => 'HPP', 'cause' => 'COST_CORRECTION', 'amount' => $net->negated()->toString(), 'cost_component' => 'CHARGE',
                'dropship_confirmation_line_id' => Row::nullable($row, 'dropship_confirmation_line_id'), 'purchase_line_id' => Row::nullable($row, 'purchase_line_id'),
                'purchase_charge_id' => $chargeId, 'allocation_version' => (int) $version, 'purchase_charge_line_share_id' => Row::nullable($row, 'purchase_charge_line_share_id'),
                'respread_from_purchase_line_id' => Row::nullable($row, 'respread_from_purchase_line_id'), 'bearer_company_id' => Row::text($row, 'bearer_company_id'),
                'bearer_project_id' => Row::nullable($row, 'bearer_project_id'), 'business_date' => $businessDate,
            ], fn (mixed $value): bool => $value !== null));
            $affected = self::project($affected, Row::nullable($row, 'bearer_project_id'));
        }

        return $affected;
    }

    /**
     * Spreads an amount over a line's received quantity — its lots and non-replacement drop-ship lines by net quantity
     * in id order — with each lot's part re-attributed to its dependents.
     *
     * @param  array<string, int|string>  $entry  the lot entry's kind, component and references
     * @param  array<string, int|string>  $directReferences  the direct rows' charge references, none for goods and tax
     * @return array{consumptions: list<object>, returned: list<Money>, projects?: array<int, true>}
     */
    private function spreadOverLine(CommandContext $context, CostLedger $ledger, int $lineId, Money $amount, array $entry, string $component, array $directReferences, string $drawCause, string $businessDate): array
    {
        $targets = self::targets($ledger, [$lineId]);

        if ($targets === []) {
            throw new InvalidArgumentException("Purchase line {$lineId} has no received quantity to take {$amount}.");
        }

        return $this->spreadOverTargets($context, $ledger, $targets, $amount, $entry, $component, $directReferences, $drawCause, $drawCause, $businessDate);
    }

    /**
     * @param  list<array{kind: string, id: int, qty: string, company?: int, project?: int, line?: int}>  $targets
     * @param  array<string, int|string>  $entry
     * @param  array<string, int|string>  $directReferences
     * @return array{consumptions: list<object>, returned: list<Money>, projects?: array<int, true>}
     */
    private function spreadOverTargets(CommandContext $context, CostLedger $ledger, array $targets, Money $amount, array $entry, string $component, array $directReferences, string $drawCause, string $directCause, string $businessDate): array
    {
        $parts = CostSplitter::spread($amount, array_map(fn (array $target): string => $target['qty'], $targets));
        $affected = ['consumptions' => [], 'returned' => []];

        foreach ($targets as $index => $target) {
            $part = $parts[$index];

            if ($part->isZero()) {
                continue;
            }

            if ($target['kind'] === 'lot') {
                $affected = self::merge($affected, $this->spreadOnLot($context, $ledger, $target['id'], $part, $entry, $drawCause, $businessDate));

                continue;
            }

            // A drop-ship line's units went to its project directly: its part is a direct row on that project (§6.1).
            $ledger->attribution([...$directReferences, 'cost_class' => 'HPP', 'cause' => $directCause, 'amount' => $part->toString(), 'cost_component' => $component,
                'dropship_confirmation_line_id' => $target['id'], 'bearer_company_id' => $target['company'] ?? 0, 'bearer_project_id' => $target['project'] ?? null,
                'business_date' => $businessDate]);
            $affected = self::project($affected, isset($target['project']) ? (string) $target['project'] : null);
        }

        return $affected;
    }

    /**
     * Posts `amount` on a lot — the entry — and re-attributes it to the lot's dependents by their net quantities, the
     * lot keeping its remaining units' part (DATABASE §6.3 step 3): a dispatch's part split between its open HPP and its
     * reclassified LOSS, a loss's LOSS, a purchase return's SUPPLIER_RETURN, which moves its case's returned cost. Each
     * draw keeps its consumption's bearer and allocation (L-44).
     *
     * @param  array<string, int|string>  $entry
     * @return array{consumptions: list<object>, returned: list<Money>, projects?: array<int, true>}
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

        $affected = ['consumptions' => [], 'returned' => []];
        $drawn = Money::zero();

        if ($weights === []) {
            throw new InvalidArgumentException("Lot {$lotId} has no units, kept or consumed, to take {$amount}.");
        }

        foreach (CostSplitter::spread($amount, $weights) as $weight => $part) {
            if ($weight === 'lot' || $part->isZero()) {
                continue;
            }

            $consumption = $dependents[$weight];
            $drawn = $drawn->plus($part);
            $returned = Money::zero();

            foreach (self::classesOf($consumption, $part) as $class => $classPart) {
                if ($classPart->isZero()) {
                    continue;
                }

                $ledger->attribution([...self::drawOf($consumption), 'cost_class' => $class, 'cause' => $drawCause, 'amount' => $classPart->toString(),
                    'reverses_attribution_id' => $drawCause === 'COST_CORRECTION' ? self::initialOf((int) Row::text($consumption, 'id')) : null, 'business_date' => $businessDate]);
                $returned = $class === 'SUPPLIER_RETURN' ? $returned->plus($classPart) : $returned;
            }

            $affected['consumptions'][] = $consumption;
            $affected['returned'][] = $returned;
            $affected = self::project($affected, Row::nullable($consumption, 'bearer_project_id'));
        }

        $ledger->move('lot_cost_balances', ['lot_id' => $lotId], ['remaining_cost' => $amount->minus($drawn)->toString()]);

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
        $balance = DB::table('purchase_charge_share_balances')->where($key)->first(['posted_amount', 'respread_out_amount', 'expensed_out_amount']);
        $taken = Money::sum(
            Money::of(is_object($balance) ? Row::text($balance, 'posted_amount') : '0'),
            Money::of(is_object($balance) ? Row::text($balance, 'respread_out_amount') : '0'),
            $expensedCarried ?? Money::of(is_object($balance) ? Row::text($balance, 'expensed_out_amount') : '0'),
            Money::of((string) $ledger->moved('purchase_charge_share_balances', $key, 'posted_amount')),
            Money::of((string) $ledger->moved('purchase_charge_share_balances', $key, 'respread_out_amount')),
            Money::of((string) $ledger->moved('purchase_charge_share_balances', $key, 'expensed_out_amount')),
        );
        $ordered = Row::text($line, 'ordered_qty_base');
        $cancelled = DB::table('purchases')->where('id', self::purchaseOf($share['line']))->value('state') === 'CANCELLED';
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
        $context->require(EvidenceGuard::locks($billId));
        $balance = DB::table('evidence_balances')->where('evidence_document_id', $billId)->first(['proven_amount', 'cited_amount']);

        if (! is_object($balance) || Money::of(Row::text($balance, 'cited_amount'))->plus($amount)->compareTo(Money::of(Row::text($balance, 'proven_amount'))) > 0) {
            throw Refused::rejected('C-20', ['bill' => $billId, 'amount' => $amount->toString()]);
        }

        $this->evidence->cite($context, $billId, 'CHARGE_SOURCE_BILL', $amount->toString());
    }

    /**
     * Moves each purchase return's case by its returned cost, reopening a closed case (step 4).
     *
     * @param  list<object>  $consumptions
     * @param  list<Money>  $returned
     */
    private function returned(CommandContext $context, array $consumptions, array $returned): void
    {
        $byCase = [];

        foreach ($consumptions as $index => $consumption) {
            $case = Row::nullable($consumption, 'correction_case_id');

            if ($case !== null && Row::text($consumption, 'kind') === 'PURCHASE_RETURN' && ! $returned[$index]->isZero()) {
                $byCase[(int) $case] = ($byCase[(int) $case] ?? Money::zero())->plus($returned[$index]);
            }
        }

        foreach ($byCase as $case => $amount) {
            $this->cases->post($context, $case, ['returned_cost' => $amount->toString()], self::REOPENED);
        }
    }

    /**
     * A correction's cases (step 4) and its completed bearer projects revalidated where material (step 5).
     *
     * @param  array{consumptions: list<object>, returned: list<Money>, projects?: array<int, true>}  $affected
     */
    private function corrected(CommandContext $context, array $affected, string $reference): void
    {
        $this->returned($context, $affected['consumptions'], $affected['returned']);

        foreach (array_keys($affected['projects'] ?? []) as $project) {
            $this->revalidation->revalidate($context, $project, $reference);
        }
    }

    /*
     * Reads.
     */

    /** @return array{0: string, 1: Money} the lot's remaining quantity and cost, with what this posting moved so far */
    private function lotBalance(CostLedger $ledger, int $lotId): array
    {
        $balance = DB::table('lot_cost_balances')->where('lot_id', $lotId)->first(['remaining_qty_base', 'remaining_cost']);

        if (! is_object($balance)) {
            throw new InvalidArgumentException("Lot {$lotId} has no cost guard yet.");
        }

        $key = ['lot_id' => $lotId];

        return [
            (string) BigDecimal::of(Row::text($balance, 'remaining_qty_base'))->plus($ledger->moved('lot_cost_balances', $key, 'remaining_qty_base')),
            Money::of(Row::text($balance, 'remaining_cost'))->plus(Money::of((string) $ledger->moved('lot_cost_balances', $key, 'remaining_cost'))),
        ];
    }

    private static function lot(int $lotId): object
    {
        return DB::table('inventory_lots')->where('id', $lotId)->first(['id', 'source_company_id', 'acquisition_kind', 'receiving_line_id', 'acquired_qty_base'])
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

    private static function lineBalance(int $lineId): object
    {
        return DB::table('purchase_line_balances')->where('purchase_line_id', $lineId)->first()
            ?? throw new InvalidArgumentException("Purchase line {$lineId} has no balance.");
    }

    /** The line's received quantity: its normal received net plus its drop-ship confirmed net (§6.1). */
    private static function received(object $line): string
    {
        return (string) BigDecimal::of(Row::text($line, 'normal_received_net_qty_base'))->plus(Row::text($line, 'dropship_confirmed_net_qty_base'));
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
     * The received quantity of lines that can take a cost: each non-replacement receipt lot by its net units —
     * acquired less receipt reversals — and each non-replacement drop-ship line by its net quantity, lots first, each in
     * id order; units with none left are not targets.
     *
     * @param  list<int>  $lines
     * @return list<array{kind: string, id: int, qty: string, company?: int, project?: int}>
     */
    private static function targets(CostLedger $ledger, array $lines): array
    {
        $targets = [];

        foreach (self::lotsOfLines($lines) as $lot) {
            $reversed = (string) DB::table('stock_movement_entries as e')->join('stock_movements as m', 'm.id', '=', 'e.stock_movement_id')
                ->where('e.lot_id', $lot)->where('m.movement_type', 'RECEIPT_REVERSAL')->sum('e.qty_delta_base');
            $acquired = DB::table('inventory_lots')->where('id', $lot)->value('acquired_qty_base');
            $net = BigDecimal::of(is_numeric($acquired) ? (string) $acquired : '0')->plus($reversed);

            if ($net->isPositive()) {
                $targets[] = ['kind' => 'lot', 'id' => $lot, 'qty' => (string) $net];
            }
        }

        $dropship = $lines === [] ? [] : DB::table('dropship_confirmation_lines as l')->join('dropship_confirmations as c', 'c.id', '=', 'l.dropship_confirmation_id')
            ->leftJoin('stock_consumption_balances as b', 'b.dropship_confirmation_line_id', '=', 'l.id')
            ->whereIn('l.purchase_line_id', $lines)->where('l.is_replacement', false)->orderBy('l.id')
            ->get(['l.id', 'l.company_id', 'c.project_id', 'l.qty_base', 'b.restored_qty_base'])->all();

        foreach ($dropship as $line) {
            $net = BigDecimal::of(Row::text($line, 'qty_base'))->minus(Row::nullable($line, 'restored_qty_base') ?? '0');

            if ($net->isPositive()) {
                $targets[] = ['kind' => 'dropship', 'id' => (int) Row::text($line, 'id'), 'qty' => (string) $net, 'company' => (int) Row::text($line, 'company_id'), 'project' => (int) Row::text($line, 'project_id')];
            }
        }

        return $targets;
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
        $order = ['GOODS' => 0, 'NONCREDITABLE_TAX' => 1, 'CHARGE' => 2, 'BASIS' => 3];
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

        usort($components, fn (array $a, array $b): int => [$order[$a['component']], $a['references']['purchase_charge_id'] ?? 0, $a['references']['purchase_charge_line_share_id'] ?? 0, $a['references']['respread_from_purchase_line_id'] ?? 0]
            <=> [$order[$b['component']], $b['references']['purchase_charge_id'] ?? 0, $b['references']['purchase_charge_line_share_id'] ?? 0, $b['references']['respread_from_purchase_line_id'] ?? 0]);

        return $components;
    }

    private static function dropshipLine(int $dropshipLineId): object
    {
        return DB::table('dropship_confirmation_lines as l')->join('dropship_confirmations as c', 'c.id', '=', 'l.dropship_confirmation_id')->where('l.id', $dropshipLineId)
            ->first(['l.id', 'l.purchase_line_id', 'l.company_id', 'c.project_id', 'l.is_replacement'])
            ?? throw new InvalidArgumentException("No drop-ship confirmation line {$dropshipLineId}.");
    }

    /**
     * @param  array{consumptions: list<object>, returned: list<Money>, projects?: array<int, true>}  $a
     * @param  array{consumptions: list<object>, returned: list<Money>, projects?: array<int, true>}  $b
     * @return array{consumptions: list<object>, returned: list<Money>, projects?: array<int, true>}
     */
    private static function merge(array $a, array $b): array
    {
        return [
            'consumptions' => [...$a['consumptions'], ...$b['consumptions']],
            'returned' => [...$a['returned'], ...$b['returned']],
            'projects' => ($a['projects'] ?? []) + ($b['projects'] ?? []),
        ];
    }

    /**
     * @param  array{consumptions: list<object>, returned: list<Money>, projects?: array<int, true>}  $affected
     * @return array{consumptions: list<object>, returned: list<Money>, projects?: array<int, true>}
     */
    private static function project(array $affected, ?string $project): array
    {
        if ($project !== null) {
            $affected['projects'] = ($affected['projects'] ?? []) + [(int) $project => true];
        }

        return $affected;
    }
}
