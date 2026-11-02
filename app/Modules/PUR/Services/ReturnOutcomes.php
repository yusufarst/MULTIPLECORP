<?php

namespace App\Modules\PUR\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\OPS\Services\CorrectionCases;
use App\Modules\PUR\Data\ReturnSettlement;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * A purchase return's outcome, declared or corrected (ROUTE-177; NX-16; WF-PUR-03): a replacement the purchase line now expects, separate from its normal remainder, never above the units the case returned, one case's at a time on a line (C-29; DATABASE §5.7); a refund expected, which keeps the case open until its money arrives through TASK-054's command (L-08); a supplier credit evidenced against the purchase value still unpaid, a RETURN_REFUNDED adjustment of the line (DATABASE §3.1, §4.6); or the unrecovered share, a loss once on the case's causal project, otherwise the lot's own company, through CostPosting's RETURN_SETTLEMENT pair (SF-LOSS; L-38; L-44). A correction takes back what an earlier outcome of its kind declared, never more. PUR never calls INV (ARCHITECTURE §3): the bearer's authority is read here.
 */
final class ReturnOutcomes
{
    public const KINDS = ['REPLACEMENT', 'REFUND', 'CREDIT', 'UNRECOVERED'];

    private const REOPENED = 'A purchase return outcome changed its figures (NX-16; C-46).';

    /** The purchase value its lines carry: goods and non-creditable tax. */
    private const VALUE = 'coalesce(sum(b.goods_value + b.noncreditable_tax_value), 0) as value';

    /** A case's audited replacement declarations, each a quantity, a correction taking one back. */
    private const REPLACEMENTS = "a.action = 'PUR.ReturnOutcomeRecorded' and a.entity_type = 'correction_case' and a.after->>'outcome' = 'REPLACEMENT'";

    public function __construct(
        private readonly CostPosting $cost,
        private readonly CorrectionCases $cases,
        private readonly PurchasePosting $purchases,
        private readonly AuditRecorder $audit,
    ) {}

    /** NX-16's set: the case and its projects, the purchase and the case's line guard, the returned consumptions' lot cost, the evidence. */
    public static function locks(int $caseId, ?int $evidence): LockSet
    {
        $set = CorrectionCases::locks($caseId);
        $line = self::lineOf($caseId);

        if ($line !== null) {
            $set->add('purchases', ['id' => $line['purchase']]);
            $set->add('purchase_line_balances', ['purchase_line_id' => $line['line']]);
        }

        foreach (self::consumptions($caseId) as $consumption) {
            $set = $set->union(CostPosting::consumptionLocks($consumption));
        }

        return $evidence === null ? $set : $set->union(EvidenceGuard::locks($evidence));
    }

    /** ROUTE-177: one outcome, or its correction, on an open purchase-return case; the case. */
    public function record(CommandContext $context, int $caseId, string $kind, bool $correction, ?string $qty, ?Money $amount, ?int $evidence, ?string $allocationReason, string $reason, string $businessDate, bool $prefilled): int
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $context->require(self::locks($caseId, $evidence));
        $case = DB::table('correction_cases')->where('id', $caseId)->first(['company_id', 'case_kind', 'state', 'causal_project_id', 'public_id', 'returned_cost', 'refunded_value', 'replaced_value', 'unrecovered_value']);

        if ($case === null || Row::text($case, 'case_kind') !== 'PURCHASE_RETURN') {
            throw Refused::rejected('C-14', ['reason' => 'case']);
        }

        // A closed case reopens only through the facts that change its figures (C-46): an outcome waits for no closed case.
        if (Row::text($case, 'state') !== 'OPEN') {
            throw Refused::conflict('ST-02', ['state' => Row::text($case, 'state')]);
        }

        if (trim($reason) === '') {
            throw Refused::rejected('C-14', ['reason' => 'reason']);
        }

        $line = self::lineOf($caseId) ?? throw Refused::rejected('C-14', ['reason' => 'line']);
        $company = (int) Row::text($case, 'company_id');
        $open = self::open($case);
        $change = match (true) {
            $kind === 'REPLACEMENT' && ! $correction => $this->replacement($context, $caseId, $line, $open, $qty),
            $kind === 'REPLACEMENT' => $this->replacementWithdrawn($context, $caseId, $line, $qty),
            $kind === 'REFUND' && ! $correction => ['expected' => $amount === null ? null : $amount->toString()],
            $kind === 'CREDIT' && ! $correction => $this->credit($context, $caseId, $company, $line, $open, $amount, $evidence, $reason),
            $kind === 'CREDIT' => $this->creditWithdrawn($context, $caseId, $company, $line, $amount, $reason),
            $kind === 'UNRECOVERED' && ! $correction => $this->unrecovered($context, $caseId, $case, $open, $amount, $allocationReason, $businessDate),
            $kind === 'UNRECOVERED' => $this->unrecoveredReversed($context, $caseId, $case, $amount, $allocationReason, $businessDate),
            default => throw Refused::rejected('C-07', ['reason' => 'kind']),
        };
        $this->audit->record(
            action: 'PUR.ReturnOutcomeRecorded',
            authorityClass: AuthorityClass::AdmPlus,
            actorUserId: $context->request->actorUserId,
            companyId: $company,
            entityType: 'correction_case',
            entityId: $caseId,
            entityPublicId: Row::text($case, 'public_id'),
            reason: trim($reason),
            commandId: $context->request->commandId,
            change: AuditChange::created(['outcome' => $kind, 'correction' => $correction, ...$change]),
            metadata: ['business_date' => $businessDate],
        );

        return $caseId;
    }

    /**
     * A replacement the line now expects: within the units the case returned less what it already declared, whole in the product's scale, and only while no other case's replacement awaits on the line — a replacement receipt's basis divides one case's open cost by the line's awaited quantity (C-29; DATABASE §5.7).
     *
     * @param  array{line: int, purchase: int, scale: int}  $line
     * @return array<string, string>
     */
    private function replacement(CommandContext $context, int $caseId, array $line, Money $open, ?string $qty): array
    {
        $wanted = BigDecimal::of($qty ?? '0');

        if (! $wanted->isPositive() || ! $open->isPositive()) {
            throw Refused::rejected('C-29', ['reason' => 'nothing_open']);
        }

        if ($wanted->stripTrailingZeros()->getScale() > $line['scale']) {
            throw Refused::rejected('C-09', ['reason' => 'scale']);
        }

        if (self::awaited($line['line'])->isPositive() && self::awaitedBy($line['line']) !== $caseId) {
            throw Refused::rejected('C-29', ['reason' => 'awaited']);
        }

        $returned = BigDecimal::of((string) DB::table('stock_consumptions')->where('correction_case_id', $caseId)->where('kind', 'PURCHASE_RETURN')->sum('qty_base'));
        $room = $returned->minus(self::declared($caseId));

        if ($wanted->isGreaterThan($room)) {
            throw Refused::rejected('C-29', ['reason' => 'above_returned', 'remainder' => (string) $room]);
        }

        $this->purchases->move($context, $line['line'], ['replacement_expected_qty_base' => (string) $wanted]);

        return ['quantity' => (string) $wanted];
    }

    /**
     * A replacement declared in error taken back: only by the case whose replacement the line awaits, never below what arrived (C-29).
     *
     * @param  array{line: int, purchase: int, scale: int}  $line
     * @return array<string, string>
     */
    private function replacementWithdrawn(CommandContext $context, int $caseId, array $line, ?string $qty): array
    {
        $wanted = BigDecimal::of($qty ?? '0');
        $awaited = self::awaited($line['line']);

        if (! $wanted->isPositive() || self::awaitedBy($line['line']) !== $caseId || $wanted->isGreaterThan($awaited) || $wanted->isGreaterThan(self::declared($caseId))) {
            throw Refused::rejected('C-29', ['reason' => 'above_awaited', 'remainder' => (string) $awaited]);
        }

        $this->purchases->move($context, $line['line'], ['replacement_expected_qty_base' => (string) $wanted->negated()]);

        return ['quantity' => (string) $wanted];
    }

    /**
     * A supplier credit against the purchase value still unpaid, evidenced: a RETURN_REFUNDED adjustment of the line and the case's refunded figure (DATABASE §3.1: refunded = Σ RETURN_REFUNDED; C-46).
     *
     * @param  array{line: int, purchase: int, scale: int}  $line
     * @return array<string, string>
     */
    private function credit(CommandContext $context, int $caseId, int $company, array $line, Money $open, ?Money $amount, ?int $evidence, string $reason): array
    {
        $cap = Money::min($open, self::unpaid($line['purchase']));

        if ($amount === null || ! $amount->isPositive() || $amount->compareTo($cap) > 0) {
            throw Refused::rejected('C-46', ['reason' => 'above_open', 'open' => $cap->toString()]);
        }

        $proof = $evidence === null ? null : DB::table('evidence_documents')->where('id', $evidence)->where('company_id', $company)->where('evidence_scope', 'COMPANY')->exists();

        if ($proof !== true) {
            throw Refused::rejected('C-14', ['reason' => 'evidence']);
        }

        $this->adjust($context, $caseId, $company, $line['line'], $amount, $reason);
        DB::table('evidence_links')->insert([
            'evidence_document_id' => $evidence, 'evidence_scope' => 'COMPANY', 'company_id' => $company, 'role' => 'PRIMARY_PROOF', 'correction_case_id' => $caseId,
            'command_id' => $context->request->commandId, 'command_line_no' => self::lineNo('evidence_links', $context->request->commandId), 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
        ]);
        $this->cases->post($context, $caseId, ['refunded_value' => $amount->toString()], self::REOPENED);

        return ['amount' => $amount->toString()];
    }

    /**
     * A supplier credit recorded in error taken back: never more than the case's credits (C-46).
     *
     * @param  array{line: int, purchase: int, scale: int}  $line
     * @return array<string, string>
     */
    private function creditWithdrawn(CommandContext $context, int $caseId, int $company, array $line, ?Money $amount, string $reason): array
    {
        $credited = Money::of((string) DB::table('purchase_line_adjustments')->where('correction_case_id', $caseId)->where('kind', 'RETURN_REFUNDED')->sum('amount_delta'));

        if ($amount === null || ! $amount->isPositive() || $amount->compareTo($credited) > 0) {
            throw Refused::rejected('C-46', ['reason' => 'above_open', 'open' => $credited->toString()]);
        }

        $this->adjust($context, $caseId, $company, $line['line'], $amount->negated(), $reason);
        $this->cases->post($context, $caseId, ['refunded_value' => $amount->negated()->toString()], self::REOPENED);

        return ['amount' => $amount->toString()];
    }

    /**
     * The unrecovered share, once (C-24): spread over the case's returned consumptions in their order, each never above the SUPPLIER_RETURN it still carries; its bearer the causal project, otherwise the lot's own company, another company's only with `stock.allocate_intercompany` and the reason (L-44; XL-1), otherwise refused and routed (AZ-08).
     *
     * @return array<string, string>
     */
    private function unrecovered(CommandContext $context, int $caseId, object $case, Money $open, ?Money $amount, ?string $allocationReason, string $businessDate): array
    {
        if ($amount === null || ! $amount->isPositive() || $amount->compareTo($open) > 0) {
            throw Refused::rejected('C-46', ['reason' => 'above_open', 'open' => $open->toString()]);
        }

        ['company' => $company, 'project' => $project, 'source' => $source] = self::bearerOf($case);
        $crossed = $company !== $source;
        $this->ensureBearer($context, $company, $source, $crossed ? $allocationReason : null);
        $carried = [];

        foreach (self::consumptions($caseId) as $consumption) {
            $carried[$consumption] = Money::of((string) DB::table('cost_attributions')->where('stock_consumption_id', $consumption)->where('cost_class', 'SUPPLIER_RETURN')->sum('amount'));
        }

        foreach (ReturnSettlement::split($amount, $carried) ?? throw Refused::rejected('C-24', ['reason' => 'above_return']) as $consumption => $part) {
            $this->cost->settleReturn($context, $consumption, $part, $company, $project, $crossed ? trim((string) $allocationReason) : null, $businessDate);
        }

        return ['amount' => $amount->toString(), ...($crossed ? ['allocated' => 'true', 'allocation_reason' => trim((string) $allocationReason)] : [])];
    }

    /**
     * An unrecovered share recognized in error, or exceeding a returned cost a correction lowered, taken back: from the case's latest settlements first, never more than they carry (C-24; AX-34); another company's bearer's loss only with the same authority that charged it (XL-1; AZ-08).
     *
     * @return array<string, string>
     */
    private function unrecoveredReversed(CommandContext $context, int $caseId, object $case, ?Money $amount, ?string $allocationReason, string $businessDate): array
    {
        $settled = Money::of(Row::text($case, 'unrecovered_value'));

        if ($amount === null || ! $amount->isPositive() || $amount->compareTo($settled) > 0) {
            throw Refused::rejected('C-46', ['reason' => 'above_open', 'open' => $settled->toString()]);
        }

        ['company' => $company, 'source' => $source] = self::bearerOf($case);
        $this->ensureBearer($context, $company, $source, $company !== $source ? $allocationReason : null);
        $carried = [];

        foreach (array_reverse(self::consumptions($caseId)) as $consumption) {
            $carried[$consumption] = Money::of((string) DB::table('cost_attributions')->where('stock_consumption_id', $consumption)->where('cost_class', 'LOSS')->where('cause', 'RETURN_SETTLEMENT')->sum('amount'));
        }

        foreach (ReturnSettlement::split($amount, $carried) ?? throw Refused::rejected('C-24', ['reason' => 'above_settled']) as $consumption => $part) {
            $this->cost->unsettleReturn($context, $consumption, $part, $businessDate);
        }

        return ['amount' => $amount->toString()];
    }

    /**
     * The unrecovered share's bearer: the case's causal project and its company, otherwise the lot's own company (L-38).
     *
     * @return array{company: int, project: ?int, source: int}
     */
    private static function bearerOf(object $case): array
    {
        $source = (int) Row::text($case, 'company_id');
        $project = Row::nullable($case, 'causal_project_id');
        $company = $project === null ? null : DB::table('projects')->where('id', (int) $project)->value('company_id');

        return ['company' => is_numeric($company) ? (int) $company : $source, 'project' => $project === null ? null : (int) $project, 'source' => $source];
    }

    /** The bearer in the actor's scope with the capability; another company than the lot's also `stock.allocate_intercompany` and its reason (XL-1), otherwise refused and routed (AZ-08; QS-20). */
    private function ensureBearer(CommandContext $context, int $company, int $source, ?string $allocationReason): void
    {
        $authority = $context->authority();
        $capability = $context->request->capability;
        $explained = $allocationReason !== null && trim($allocationReason) !== '';
        $allowed = $authority !== null && $authority->scope->includes($company) && $authority->allows($capability, $company)
            && ($company === $source || ($explained && $authority->allows('stock.allocate_intercompany', $company)));

        if ($allowed) {
            return;
        }

        $actor = $context->request->actorUserId;
        $command = $context->request->commandId;
        $type = $context->request->commandType;

        throw Refused::rejected('AZ-08', ['routed' => true, 'reason' => 'bearer'], function (ConnectionInterface $connection) use ($company, $source, $actor, $command, $type): void {
            foreach (array_unique([$company, $source]) as $routed) {
                $this->audit->record(action: 'PUR.ReturnOutcomeRouted', authorityClass: AuthorityClass::System, actorUserId: $actor, companyId: $routed, entityType: 'company', entityId: $routed, commandId: $command, metadata: ['routed_action' => $type], connection: $connection);
            }
        });
    }

    /** A RETURN_REFUNDED adjustment of the line: positive for a credit, negative for its correction. */
    private function adjust(CommandContext $context, int $caseId, int $company, int $lineId, Money $amount, string $reason): void
    {
        DB::table('purchase_line_adjustments')->insert([
            'purchase_line_id' => $lineId, 'company_id' => $company, 'kind' => 'RETURN_REFUNDED', 'amount_delta' => $amount->toString(), 'reason' => trim($reason), 'correction_case_id' => $caseId,
            'command_id' => $context->request->commandId, 'command_line_no' => self::lineNo('purchase_line_adjustments', $context->request->commandId), 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
        ]);
    }

    /** The purchase value not yet paid or credited: its lines' goods and non-creditable tax, less its payments and its credits. */
    private static function unpaid(int $purchaseId): Money
    {
        $value = DB::table('purchase_line_balances as b')->join('purchase_lines as l', 'l.id', '=', 'b.purchase_line_id')->where('l.purchase_id', $purchaseId)->selectRaw(self::VALUE)->value('value');
        $paid = DB::table('disbursements')->where('purchase_id', $purchaseId)->where('kind', 'PURCHASE_PAYMENT')->sum('amount');
        $credited = DB::table('purchase_line_adjustments as a')->join('purchase_lines as l', 'l.id', '=', 'a.purchase_line_id')->where('l.purchase_id', $purchaseId)->where('a.kind', 'RETURN_REFUNDED')->sum('a.amount_delta');

        return Money::max(Money::zero(), Money::of(is_numeric($value) ? (string) $value : '0')->minus(Money::of((string) $paid))->minus(Money::of((string) $credited)));
    }

    /** The replacement the line still awaits: expected less received. */
    private static function awaited(int $lineId): BigDecimal
    {
        $guard = DB::table('purchase_line_balances as b')->where('purchase_line_id', $lineId)->first(['replacement_expected_qty_base', 'replacement_received_net_qty_base']);

        return $guard === null ? BigDecimal::zero() : BigDecimal::of(Row::text($guard, 'replacement_expected_qty_base'))->minus(Row::text($guard, 'replacement_received_net_qty_base'));
    }

    /** The case whose replacement the line awaits: the last one declared on it, as one case's at a time. */
    private static function awaitedBy(int $lineId): ?int
    {
        $case = DB::table('audit_events as a')->whereRaw(self::REPLACEMENTS)->whereIn('a.entity_id', self::casesOfLine($lineId))->whereRaw("coalesce(a.after->>'correction', 'false') = 'false'")->orderByDesc('a.id')->value('a.entity_id');

        return is_numeric($case) ? (int) $case : null;
    }

    /** The replacement the case declared, net of its corrections. */
    private static function declared(int $caseId): BigDecimal
    {
        $net = BigDecimal::zero();

        foreach (DB::table('audit_events as a')->whereRaw(self::REPLACEMENTS)->where('a.entity_id', $caseId)->orderBy('a.id')->get(['a.after'])->all() as $event) {
            $after = json_decode(Row::nullable($event, 'after') ?? '{}', true);
            $quantity = is_array($after) && is_string($after['quantity'] ?? null) ? $after['quantity'] : '0';
            $net = is_array($after) && ($after['correction'] ?? false) === true ? $net->minus($quantity) : $net->plus($quantity);
        }

        return $net;
    }

    /** @return list<int> the purchase-return cases of the line's lots */
    private static function casesOfLine(int $lineId): array
    {
        return array_values(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, DB::table('correction_cases as k')->join('inventory_lots as i', 'i.id', '=', 'k.source_lot_id')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')
            ->where('r.purchase_line_id', $lineId)->where('k.case_kind', 'PURCHASE_RETURN')->pluck('k.id')->all()));
    }

    /** What the case's returned cost still awaits: returned − refunded − replaced − unrecovered (C-46). */
    private static function open(object $case): Money
    {
        return ReturnSettlement::open(Money::of(Row::text($case, 'returned_cost')), Money::of(Row::text($case, 'refunded_value')), Money::of(Row::text($case, 'replaced_value')), Money::of(Row::text($case, 'unrecovered_value')));
    }

    /** @return array{line: int, purchase: int, scale: int}|null the purchase line of the case's lot, its product's scale */
    private static function lineOf(int $caseId): ?array
    {
        $row = DB::table('correction_cases as k')->join('inventory_lots as i', 'i.id', '=', 'k.source_lot_id')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->join('purchase_lines as l', 'l.id', '=', 'r.purchase_line_id')
            ->where('k.id', $caseId)->first(['l.id', 'l.purchase_id', 'i.quantity_scale']);

        return $row === null ? null : ['line' => (int) Row::text($row, 'id'), 'purchase' => (int) Row::text($row, 'purchase_id'), 'scale' => (int) Row::text($row, 'quantity_scale')];
    }

    /** @return list<int> the case's returned consumptions, in their order */
    private static function consumptions(int $caseId): array
    {
        return array_values(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, DB::table('stock_consumptions')->where('correction_case_id', $caseId)->where('kind', 'PURCHASE_RETURN')->orderBy('id')->pluck('id')->all()));
    }

    private static function lineNo(string $table, string $commandId): int
    {
        return DB::table($table)->where('command_id', $commandId)->count() + 1;
    }
}
