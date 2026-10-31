<?php

namespace App\Modules\PUR\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentVoiding;
use App\Modules\OPS\Services\Corrections;
use App\Modules\PUR\Models\Purchase;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * What ends or corrects a purchase's facts (AX-12; AX-32; AX-34; CM-17; CM-18; CM-35; L-23; L-47; `DIR-101` F1): a cancellation closing every line to its ordered quantity, a remainder closure, a cost or basis correction through CST's cascade, the performed mark of a non-stock line. Each its one transaction, each audited with its reason.
 */
final class PurchaseCorrections
{
    public function __construct(
        private readonly PurchasePosting $posting,
        private readonly CostPosting $cost,
        private readonly PurchaseRecords $records,
        private readonly Corrections $corrections,
        private readonly AuditRecorder $audit,
        private readonly DocumentChain $chain,
        private readonly DocumentVoiding $voiding,
    ) {}

    /** AX-12's set: the purchase's cost locks and its PO's documents. */
    public function cancelLocks(Purchase $purchase): LockSet
    {
        $set = CostPosting::purchaseLocks($purchase->id);

        foreach (DB::table('documents')->where('purchase_id', $purchase->id)->orderBy('id')->pluck('id') as $document) {
            if (is_numeric($document)) {
                $issued = $this->chain->issuedOf((int) $document);
                $row = $this->chain->document((int) $document);
                $set = $set->union($this->chain->locks($row, $issued === null ? [] : [$issued->id]));

                if ($issued !== null) {
                    $set = $set->union($this->chain->participantLocks($this->chain->change(DocumentChange::DOCUMENT_VOIDED, $row, $issued->id, null)));
                }
            }
        }

        return $set;
    }

    /** AX-12: only with nothing received or confirmed and no performed or paid non-stock line, in one transaction. */
    public function cancel(CommandContext $context, Purchase $purchase, string $reason, string $businessDate, bool $prefilled): void
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $purchase->refresh();

        if ($purchase->state !== 'OPEN') {
            throw Refused::conflict('ST-02', ['state' => $purchase->state]);
        }

        // A document issued while this command waited names rows it does not hold (§5 steps 3–4; LK-30).
        $context->require($this->cancelLocks($purchase));
        $lines = DB::table('purchase_lines as l')->join('purchase_line_balances as b', 'b.purchase_line_id', '=', 'l.id')->where('l.purchase_id', $purchase->id)->orderBy('l.line_no')
            ->get(['l.id', 'l.kind', 'l.performed_at', 'b.ordered_qty_base', 'b.normal_received_net_qty_base', 'b.closed_qty_base', 'b.dropship_confirmed_net_qty_base', 'b.replacement_received_net_qty_base']);

        foreach ($lines as $line) {
            foreach (['normal_received_net_qty_base', 'dropship_confirmed_net_qty_base', 'replacement_received_net_qty_base'] as $column) {
                if (BigDecimal::of(Row::text($line, $column))->isPositive()) {
                    throw Refused::rejected('C-29', ['reason' => 'received']);
                }
            }

            if (Row::text($line, 'kind') === 'NONSTOCK' && Row::nullable($line, 'performed_at') !== null) {
                throw Refused::rejected('C-29', ['reason' => 'performed']);
            }
        }

        $hasNonStock = $lines->contains(fn (object $line): bool => Row::text($line, 'kind') === 'NONSTOCK');

        if ($hasNonStock && self::paid($purchase->id)) {
            throw Refused::rejected('C-29', ['reason' => 'paid']);
        }

        $moved = DB::table('purchases')->where('id', $purchase->id)->where('state', 'OPEN')->update(['state' => 'CANCELLED', 'cancelled_by' => $context->request->actorUserId, 'cancelled_at' => $context->now(), 'cancelled_reason' => $reason, 'updated_at' => $context->now(), 'lock_version' => $purchase->lock_version + 1]);

        if ($moved !== 1) {
            throw Refused::conflict('ST-02', ['state' => 'CANCELLED']);
        }

        foreach ($lines as $line) {
            $id = (int) Row::text($line, 'id');

            // Closed to its ordered quantity, what an earlier closure closed already counted, in one statement (C-29; GU-01).
            $deltas = ['closed_qty_base' => (string) BigDecimal::of(Row::text($line, 'ordered_qty_base'))->minus(Row::text($line, 'closed_qty_base'))];

            if (Row::text($line, 'kind') === 'NONSTOCK') {
                $posted = $this->cost->nonStockCancelled($context, $id, $businessDate);
                $deltas += ['posted_goods_value' => $posted->goods->toString(), 'posted_noncreditable_tax_value' => $posted->tax->toString()];
            }

            $this->posting->move($context, $id, $deltas);
            $this->cost->remainderEnded($context, $id, $businessDate);
        }

        foreach (DB::table('documents')->where('purchase_id', $purchase->id)->orderBy('id')->pluck('id') as $document) {
            $id = is_numeric($document) ? (int) $document : 0;
            $issued = $this->chain->issuedOf($id);

            if ($issued !== null) {
                $this->voiding->void($context, $this->chain->document($id), $issued, $reason);
            }
        }

        $this->corrections->record($context, 'PUR.PurchaseCancelled', AuthorityClass::AdmPlus, $purchase->company_id, 'purchase', $purchase->id, $purchase->public_id, AuditRelation::Voids, 'purchase', $purchase->public_id, $reason,
            AuditChange::updated(['state' => 'OPEN'], ['state' => 'CANCELLED']), null, ['business_date' => $businessDate]);
    }

    /**
     * AX-32: the open remainder of each line closed with its reason, its pending shares re-spread in the same action (BR-PUR-04; L-47).
     *
     * @param  array<int, string>  $closures  line => quantity in base units
     */
    public function close(CommandContext $context, Purchase $purchase, array $closures, string $reason, string $businessDate, bool $prefilled): void
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $purchase->refresh();

        if ($purchase->state !== 'OPEN') {
            throw Refused::conflict('ST-02', ['state' => $purchase->state]);
        }

        $line = 0;
        $before = [];

        foreach ($closures as $id => $quantity) {
            $row = DB::table('purchase_lines as l')->join('purchase_line_balances as b', 'b.purchase_line_id', '=', 'l.id')->where('l.id', $id)->where('l.purchase_id', $purchase->id)
                ->first(['l.kind', 'l.company_id', 'l.product_id', 'l.quantity_scale', 'b.ordered_qty_base', 'b.normal_received_net_qty_base', 'b.closed_qty_base', 'b.dropship_confirmed_net_qty_base']);

            if ($row === null || Row::text($row, 'kind') !== 'GOODS') {
                throw Refused::rejected('C-14', ['reason' => 'other_line']);
            }

            $open = BigDecimal::of(Row::text($row, 'ordered_qty_base'))->minus(Row::text($row, 'normal_received_net_qty_base'))->minus(Row::text($row, 'closed_qty_base'))->minus(Row::text($row, 'dropship_confirmed_net_qty_base'));

            if (BigDecimal::of($quantity)->isLessThanOrEqualTo(0) || BigDecimal::of($quantity)->isGreaterThan($open)) {
                throw Refused::rejected('C-29', ['reason' => 'above_open', 'open' => (string) $open]);
            }

            $before[RowHandle::of('purchase_lines', $id)] = Row::text($row, 'closed_qty_base');
            DB::table('purchase_line_closures')->insert(['purchase_line_id' => $id, 'company_id' => (int) Row::text($row, 'company_id'), 'product_id' => (int) Row::text($row, 'product_id'), 'quantity_scale' => (int) Row::text($row, 'quantity_scale'), 'closed_qty_base' => $quantity, 'reason' => $reason, 'decided_by' => $context->request->actorUserId, 'business_date' => $businessDate,
                'command_id' => $context->request->commandId, 'command_line_no' => ++$line, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
            $this->posting->move($context, $id, ['closed_qty_base' => $quantity]);
            $this->cost->remainderEnded($context, $id, $businessDate);
        }

        $open = DB::table('purchase_lines as l')->join('purchase_line_balances as b', 'b.purchase_line_id', '=', 'l.id')->where('l.purchase_id', $purchase->id)->where('l.kind', 'GOODS')
            ->whereRaw('b.ordered_qty_base > b.normal_received_net_qty_base + b.dropship_confirmed_net_qty_base + b.closed_qty_base')->exists();

        if (! $open) {
            DB::table('purchases')->where('id', $purchase->id)->where('state', 'OPEN')->update(['state' => 'CLOSED', 'lock_version' => $purchase->lock_version + 1, 'updated_at' => $context->now()]);
        }

        $this->corrections->record($context, 'PUR.RemainderClosed', AuthorityClass::AdmPlus, $purchase->company_id, 'purchase', $purchase->id, $purchase->public_id, AuditRelation::Supersedes, 'purchase', $purchase->public_id, $reason,
            AuditChange::updated(['closed' => $before], ['closed' => array_combine(array_map(fn (int $id): string => RowHandle::of('purchase_lines', $id), array_keys($closures)), array_values($closures))]), null, ['business_date' => $businessDate]);
    }

    /**
     * CM-27's exact inverse of AX-32 (DATABASE §4.6 `supersedes_id`): the closure recorded in error superseded by a row naming it, its quantity open again on the line, and each charge on the line posted again by its unchanged shares, so what the closure re-spread is pending again. An expense it caused never shrinks: a closure that expensed a share waits for that expense's own correction first (C-21; DATABASE §6.1).
     */
    public function supersedeClosure(CommandContext $context, Purchase $purchase, int $closureId, string $reason, string $businessDate, bool $prefilled): void
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $purchase->refresh();

        if ($purchase->state === 'CANCELLED') {
            throw Refused::conflict('ST-02', ['state' => $purchase->state]);
        }

        $closure = DB::table('purchase_line_closures as c')->join('purchase_lines as l', 'l.id', '=', 'c.purchase_line_id')->where('c.id', $closureId)->where('l.purchase_id', $purchase->id)->first(['c.purchase_line_id', 'c.company_id', 'c.product_id', 'c.quantity_scale', 'c.closed_qty_base', 'c.supersedes_id']);

        if ($closure === null || Row::nullable($closure, 'supersedes_id') !== null) {
            throw Refused::rejected('C-14', ['reason' => 'other_closure']);
        }

        if (DB::table('purchase_line_closures')->where('supersedes_id', $closureId)->exists()) {
            throw Refused::conflict('ST-02', ['state' => 'SUPERSEDED']);
        }

        $lineId = (int) Row::text($closure, 'purchase_line_id');
        $shares = DB::table('purchase_charge_share_balances')->where('purchase_line_id', $lineId)->orderBy('purchase_charge_id')->get(['purchase_charge_id', 'expensed_out_amount']);

        if ($shares->contains(fn (object $share): bool => Money::of(Row::text($share, 'expensed_out_amount'))->isPositive())) {
            throw Refused::rejected('C-21', ['reason' => 'closure_expensed']);
        }

        $quantity = Row::text($closure, 'closed_qty_base');
        DB::table('purchase_line_closures')->insert(['purchase_line_id' => $lineId, 'company_id' => (int) Row::text($closure, 'company_id'), 'product_id' => (int) Row::text($closure, 'product_id'), 'quantity_scale' => (int) Row::text($closure, 'quantity_scale'), 'closed_qty_base' => $quantity, 'reason' => $reason, 'decided_by' => $context->request->actorUserId, 'business_date' => $businessDate, 'supersedes_id' => $closureId,
            'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
        $this->posting->move($context, $lineId, ['closed_qty_base' => '-'.$quantity]);
        DB::table('purchases')->where('id', $purchase->id)->where('state', 'CLOSED')->update(['state' => 'OPEN', 'lock_version' => $purchase->lock_version + 1, 'updated_at' => $context->now()]);
        $position = 0;

        foreach ($shares as $share) {
            $charge = (int) Row::text($share, 'purchase_charge_id');
            $version = PurchaseRecords::latestVersion($charge);
            $now = $context->now();
            $row = 0;

            foreach (DB::table('purchase_charge_line_shares')->where('purchase_charge_id', $charge)->where('allocation_version', $version)->orderBy('id')->get(['purchase_line_id', 'company_id', 'basis_value', 'share_amount']) as $kept) {
                DB::table('purchase_charge_line_shares')->insert(['purchase_charge_id' => $charge, 'company_id' => (int) Row::text($kept, 'company_id'), 'purchase_line_id' => (int) Row::text($kept, 'purchase_line_id'), 'basis_value' => Row::text($kept, 'basis_value'), 'share_amount' => Row::text($kept, 'share_amount'), 'allocation_version' => $version + 1,
                    'command_id' => $context->request->commandId, 'command_line_no' => $position * PurchaseRecords::SHARE_ROWS + ++$row, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId]);
            }

            $position++;
            $this->cost->chargeCorrected($context, $charge, null, $businessDate);
        }

        $this->corrections->record($context, 'PUR.RemainderClosureSuperseded', AuthorityClass::AdmPlus, $purchase->company_id, 'purchase', $purchase->id, $purchase->public_id, AuditRelation::Supersedes, 'purchase', $purchase->public_id, $reason,
            AuditChange::updated(['closed' => $quantity], ['closed' => '0', 'line' => RowHandle::of('purchase_lines', $lineId)]), null, ['business_date' => $businessDate]);
    }

    /** CM-17: a line's goods value or non-creditable tax corrected, the cascade in the same transaction (AX-34; C-57). */
    public function correctLine(CommandContext $context, Purchase $purchase, int $lineId, string $kind, string $value, string $reason, string $businessDate, bool $prefilled): void
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $purchase->refresh();

        if ($purchase->state === 'CANCELLED') {
            throw Refused::conflict('ST-02', ['state' => $purchase->state]);
        }

        $line = DB::table('purchase_line_balances as b')->join('purchase_lines as l', 'l.id', '=', 'b.purchase_line_id')->where('l.id', $lineId)->where('l.purchase_id', $purchase->id)->first(['l.kind', 'b.goods_value', 'b.noncreditable_tax_value']);

        if ($line === null) {
            throw Refused::rejected('C-14', ['reason' => 'other_line']);
        }

        // CM-17: before any effect a goods line is simply edited (ROUTE-172), its first adjustment would end that.
        if (Row::text($line, 'kind') === 'GOODS' && ! self::hasFirstEffect($purchase->id)) {
            throw Refused::rejected('L-23', ['reason' => 'edit_instead']);
        }

        $column = $kind === 'PRICE_CORRECTION' ? 'goods_value' : 'noncreditable_tax_value';
        $delta = Money::of($value)->minus(Money::of(Row::text($line, $column)));

        if ($delta->isZero()) {
            throw Refused::rejected('AX-34', ['reason' => 'unchanged']);
        }

        $adjustment = DB::table('purchase_line_adjustments')->insertGetId(['purchase_line_id' => $lineId, 'company_id' => $purchase->company_id, 'kind' => $kind, 'amount_delta' => $delta->toString(), 'reason' => $reason, 'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
        // The value and what is posted of it move together, so a lowered value never stands below the old posted one (C-57; GU-01).
        $posted = $this->cost->lineCorrected($context, $adjustment, $businessDate, Money::of($value));
        $this->posting->move($context, $lineId, [$column => $delta->toString(), 'posted_goods_value' => $posted->goods->toString(), 'posted_noncreditable_tax_value' => $posted->tax->toString()]);
        $this->corrections->record($context, 'PUR.CostCorrected', AuthorityClass::AdmPlus, $purchase->company_id, 'purchase', $purchase->id, $purchase->public_id, AuditRelation::Revises, 'purchase', $purchase->public_id, $reason,
            AuditChange::updated([$column => Row::text($line, $column)], [$column => $value, 'line' => RowHandle::of('purchase_lines', $lineId)]), null, ['business_date' => $businessDate]);
    }

    /**
     * CM-17's charge amount or CM-35's basis: the adjustment for an amount, a new version of the shares, the cascade (AX-34; C-20; C-21).
     *
     * @param  list<?string>  $entered
     */
    public function correctCharge(CommandContext $context, Purchase $purchase, int $chargeId, ?string $amount, ?string $basis, array $entered, string $reason, string $businessDate, bool $prefilled): void
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $purchase->refresh();

        if ($purchase->state === 'CANCELLED') {
            throw Refused::conflict('ST-02', ['state' => $purchase->state]);
        }

        $charge = DB::table('purchase_charges as c')->join('purchase_charge_balances as b', 'b.purchase_charge_id', '=', 'c.id')->where('c.id', $chargeId)->where('c.purchase_id', $purchase->id)->first(['c.classification', 'c.allocation_basis', 'b.amount']);

        if ($charge === null) {
            throw Refused::rejected('C-14', ['reason' => 'other_charge']);
        }

        // An expensed charge holds no allocation: its correction is the expense's (DATABASE §6.3; TASK-054's contra).
        if (Row::text($charge, 'classification') !== 'ATTRIBUTABLE') {
            throw Refused::rejected('CM-35', ['reason' => 'not_attributable']);
        }

        $current = Money::of(Row::text($charge, 'amount'));
        $target = $amount === null ? $current : Money::of($amount);
        $delta = $target->minus($current);
        $adjustment = $delta->isZero() ? null : DB::table('purchase_line_adjustments')->insertGetId(['purchase_charge_id' => $chargeId, 'company_id' => $purchase->company_id, 'kind' => 'CHARGE_CORRECTION', 'amount_delta' => $delta->toString(), 'reason' => $reason, 'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);

        if ($adjustment === null && $basis === null) {
            throw Refused::rejected('AX-34', ['reason' => 'unchanged']);
        }

        $version = PurchaseRecords::latestVersion($chargeId) + 1;
        // An amount alone keeps the basis as it stands, a weight's or an explicit split's values with it.
        $this->records->insertShares($context, $chargeId, $purchase->company_id, $purchase->id, $target, $basis ?? Row::text($charge, 'allocation_basis'), $basis === null ? PurchaseRecords::keptBasis($chargeId, $purchase->id) : $entered, $version, PurchaseRecords::SHARE_ROWS);
        $this->cost->chargeCorrected($context, $chargeId, $adjustment, $businessDate);
        $this->corrections->record($context, 'PUR.CostCorrected', AuthorityClass::AdmPlus, $purchase->company_id, 'purchase', $purchase->id, $purchase->public_id, AuditRelation::Revises, 'purchase', $purchase->public_id, $reason,
            AuditChange::updated(['amount' => $current->toString(), 'basis' => Row::nullable($charge, 'allocation_basis')], ['amount' => $target->toString(), 'basis' => $basis, 'charge' => RowHandle::of('purchase_charges', $chargeId)]), null, ['business_date' => $businessDate]);
    }

    /** `DIR-101` F1: a non-stock line's performed mark, or its taking back when recorded in error. No cost posted, no first effect. */
    public function mark(CommandContext $context, Purchase $purchase, int $lineId, bool $performed, ?string $reason): void
    {
        $purchase->refresh();

        if ($purchase->state !== 'OPEN') {
            throw Refused::rejected('NX-04', ['reason' => 'not_open']);
        }

        $line = DB::table('purchase_lines')->where('id', $lineId)->where('purchase_id', $purchase->id)->first(['kind', 'performed_at']);

        if ($line === null || Row::text($line, 'kind') !== 'NONSTOCK') {
            throw Refused::rejected('NX-04', ['reason' => 'not_nonstock']);
        }

        if ((Row::nullable($line, 'performed_at') !== null) === $performed) {
            throw Refused::conflict('ST-02', ['state' => $performed ? 'PERFORMED' : 'NOT_PERFORMED']);
        }

        DB::table('purchase_lines')->where('id', $lineId)->update(['performed_at' => $performed ? $context->now() : null]);
        $handle = RowHandle::of('purchase_lines', $lineId);

        if ($performed) {
            $this->audit->record(action: 'PUR.LinePerformed', authorityClass: AuthorityClass::Adm, actorUserId: $context->request->actorUserId, companyId: $purchase->company_id, entityType: 'purchase', entityId: $purchase->id, entityPublicId: $purchase->public_id, reason: null, commandId: $context->request->commandId, change: AuditChange::updated(['performed' => false], ['performed' => true, 'line' => $handle]));

            return;
        }

        $this->corrections->record($context, 'PUR.LinePerformedTakenBack', AuthorityClass::AdmPlus, $purchase->company_id, 'purchase', $purchase->id, $purchase->public_id, AuditRelation::Reverses, 'purchase', $purchase->public_id, (string) $reason, AuditChange::updated(['performed' => true], ['performed' => false, 'line' => $handle]));
    }

    /** A live PURCHASE_PAYMENT disbursement citing the purchase, which names no line (DATABASE §4.12; `DIR-101`). */
    public static function paid(int $purchaseId): bool
    {
        return DB::table('disbursements as d')->leftJoin('money_fact_balances as f', 'f.disbursement_id', '=', 'd.id')->where('d.purchase_id', $purchaseId)->where('d.kind', 'PURCHASE_PAYMENT')->whereRaw('coalesce(f.contra_amount, 0) < d.amount')->exists();
    }

    /** L-23: its first effect — a goods line's receipt, confirmation, closure or adjustment, or a disbursement. */
    public static function hasFirstEffect(int $purchaseId): bool
    {
        $effect = DB::selectOne("select exists (select 1 from purchase_lines l where l.purchase_id = ? and l.kind = 'GOODS' and purchase_line_has_effect(l.id)) as effect", [$purchaseId]);

        return (is_object($effect) && ($effect->effect ?? false) === true) || DB::table('disbursements')->where('purchase_id', $purchaseId)->exists();
    }
}
