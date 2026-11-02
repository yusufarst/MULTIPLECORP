<?php

namespace App\Modules\PUR\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\OPS\Services\CorrectionCases;
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
 * A purchase return's outcome (ROUTE-177; NX-16; WF-PUR-03): a replacement the purchase line now expects, separate from its normal remainder and never above the units its lots returned (C-29); a refund expected, which keeps the case open until its money arrives through TASK-054's command (L-08); a supplier credit evidenced against the purchase value, as a RETURN_REFUNDED adjustment of the line; or the unrecovered share, a loss once on the case's causal project, otherwise the lot's own company, through CostPosting's RETURN_SETTLEMENT pair (SF-LOSS; L-38; L-44). PUR never calls INV (ARCHITECTURE §3): the bearer's authority is read here.
 */
final class ReturnOutcomes
{
    public const KINDS = ['REPLACEMENT', 'REFUND', 'CREDIT', 'UNRECOVERED'];

    private const REOPENED = 'A purchase return outcome changed its figures (NX-16; C-46).';

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

    /** ROUTE-177: one outcome on an open purchase-return case; the case. */
    public function record(CommandContext $context, int $caseId, string $kind, ?string $qty, ?Money $amount, ?int $evidence, ?string $allocationReason, string $reason, string $businessDate, bool $prefilled): int
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
        $change = match ($kind) {
            'REPLACEMENT' => $this->replacement($context, $line, $open, $qty),
            'REFUND' => ['expected' => $amount === null ? null : $amount->toString()],
            'CREDIT' => $this->credit($context, $caseId, $company, $line, $open, $amount, $evidence, $reason),
            'UNRECOVERED' => $this->unrecovered($context, $caseId, $case, $open, $amount, $allocationReason, $businessDate),
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
            change: AuditChange::created(['outcome' => $kind, ...$change]),
            metadata: ['business_date' => $businessDate],
        );

        return $caseId;
    }

    /**
     * A replacement the line now expects: never above the units its lots returned to the supplier, less what it already expects (C-29; PX-03).
     *
     * @param  array{line: int, purchase: int}  $line
     * @return array<string, string>
     */
    private function replacement(CommandContext $context, array $line, Money $open, ?string $qty): array
    {
        $wanted = BigDecimal::of($qty ?? '0');

        if (! $wanted->isPositive() || ! $open->isPositive()) {
            throw Refused::rejected('C-29', ['reason' => 'nothing_open']);
        }

        $returned = BigDecimal::of((string) DB::table('stock_consumptions as c')->join('inventory_lots as i', 'i.id', '=', 'c.lot_id')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')
            ->where('r.purchase_line_id', $line['line'])->where('c.kind', 'PURCHASE_RETURN')->sum('c.qty_base'));
        $expected = DB::table('purchase_line_balances as b')->where('purchase_line_id', $line['line'])->value('replacement_expected_qty_base');
        $room = $returned->minus(is_string($expected) ? $expected : '0');

        if ($wanted->isGreaterThan($room)) {
            throw Refused::rejected('C-29', ['reason' => 'above_returned', 'remainder' => (string) $room]);
        }

        $this->purchases->move($context, $line['line'], ['replacement_expected_qty_base' => (string) $wanted]);

        return ['quantity' => (string) $wanted];
    }

    /**
     * A supplier credit against the purchase value, evidenced: a RETURN_REFUNDED adjustment of the line, its negative the purchase value it takes back, and the case's refunded figure (DATABASE §4.6; C-46).
     *
     * @param  array{line: int, purchase: int}  $line
     * @return array<string, string>
     */
    private function credit(CommandContext $context, int $caseId, int $company, array $line, Money $open, ?Money $amount, ?int $evidence, string $reason): array
    {
        if ($amount === null || ! $amount->isPositive() || $amount->compareTo($open) > 0) {
            throw Refused::rejected('C-46', ['reason' => 'above_open', 'open' => $open->toString()]);
        }

        $proof = $evidence === null ? null : DB::table('evidence_documents')->where('id', $evidence)->where('company_id', $company)->where('evidence_scope', 'COMPANY')->exists();

        if ($proof !== true) {
            throw Refused::rejected('C-14', ['reason' => 'evidence']);
        }

        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $now = $context->now();
        DB::table('purchase_line_adjustments')->insert([
            'purchase_line_id' => $line['line'], 'company_id' => $company, 'kind' => 'RETURN_REFUNDED', 'amount_delta' => $amount->negated()->toString(), 'reason' => trim($reason), 'correction_case_id' => $caseId,
            'command_id' => $commandId, 'command_line_no' => self::lineNo('purchase_line_adjustments', $commandId), 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        DB::table('evidence_links')->insert([
            'evidence_document_id' => $evidence, 'evidence_scope' => 'COMPANY', 'company_id' => $company, 'role' => 'PRIMARY_PROOF', 'correction_case_id' => $caseId,
            'command_id' => $commandId, 'command_line_no' => self::lineNo('evidence_links', $commandId), 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $this->cases->post($context, $caseId, ['refunded_value' => $amount->toString()], self::REOPENED);

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

        $source = (int) Row::text($case, 'company_id');
        $project = Row::nullable($case, 'causal_project_id');
        $company = $project === null ? $source : (int) DB::table('projects')->where('id', (int) $project)->value('company_id');
        $crossed = $company !== $source;
        $this->ensureBearer($context, $company, $source, $crossed ? $allocationReason : null);
        $left = $amount;

        foreach (self::consumptions($caseId) as $consumption) {
            if (! $left->isPositive()) {
                break;
            }

            $carried = Money::of((string) DB::table('cost_attributions')->where('stock_consumption_id', $consumption)->where('cost_class', 'SUPPLIER_RETURN')->sum('amount'));
            $part = Money::min($carried, $left);

            if ($part->isPositive()) {
                $this->cost->settleReturn($context, $consumption, $part, $company, $project === null ? null : (int) $project, $crossed ? trim((string) $allocationReason) : null, $businessDate);
                $left = $left->minus($part);
            }
        }

        if ($left->isPositive()) {
            throw Refused::rejected('C-24', ['reason' => 'above_return']);
        }

        return ['amount' => $amount->toString(), ...($crossed ? ['allocated' => 'true', 'allocation_reason' => trim((string) $allocationReason)] : [])];
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

    /** What the case's returned cost still awaits: returned − refunded − replaced − unrecovered (C-46). */
    private static function open(object $case): Money
    {
        return Money::of(Row::text($case, 'returned_cost'))->minus(Money::of(Row::text($case, 'refunded_value')))->minus(Money::of(Row::text($case, 'replaced_value')))->minus(Money::of(Row::text($case, 'unrecovered_value')));
    }

    /** @return array{line: int, purchase: int}|null the purchase line of the case's lot */
    private static function lineOf(int $caseId): ?array
    {
        $row = DB::table('correction_cases as k')->join('inventory_lots as i', 'i.id', '=', 'k.source_lot_id')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->join('purchase_lines as l', 'l.id', '=', 'r.purchase_line_id')
            ->where('k.id', $caseId)->first(['l.id', 'l.purchase_id']);

        return $row === null ? null : ['line' => (int) Row::text($row, 'id'), 'purchase' => (int) Row::text($row, 'purchase_id')];
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
