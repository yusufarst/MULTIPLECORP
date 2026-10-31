<?php

namespace App\Modules\FIN\Services;

use App\Modules\CST\Services\CostPosting;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * A settlement re-recorded where it now belongs (DATABASE §11.4; L-28; C-45): ACTIVE → CONTRAED with a new ACTIVE row citing the same evidence on the invoice or payment
 * named, a `financial_contras` row and its money-fact guard, an expense moving exactly once (BR-FIN-09; C-23). The invoice and source guards are the caller's, netted in their one statement.
 */
final class SettlementMoves
{
    /** A contra of a settlement's expense keeps the settlement's own command line free in `financial_contras`. */
    private const EXPENSE_LINES = 30000;

    public function __construct(private readonly CostPosting $cost) {}

    /**
     * The payment's ACTIVE settlements, by id, on one invoice or on all of them.
     *
     * @return array<int, array{amount: string, invoice: int, class: string, direction: ?string}>
     */
    public static function anchored(int $paymentId, ?int $invoiceId = null): array
    {
        $found = [];

        foreach (DB::table('deduction_settlements')->where('payment_id', $paymentId)->where('state', 'ACTIVE')->when($invoiceId !== null, fn ($query) => $query->where('invoice_id', $invoiceId))->orderBy('id')
            ->get(['id', 'amount', 'invoice_id', 'settlement_class', 'tax_direction']) as $row) {
            $found[(int) Row::text($row, 'id')] = ['amount' => Row::text($row, 'amount'), 'invoice' => (int) Row::text($row, 'invoice_id'), 'class' => Row::text($row, 'settlement_class'), 'direction' => Row::nullable($row, 'tax_direction')];
        }

        return $found;
    }

    /** A settlement's row and the money-fact guards of it and of its expense (LK-26; LK-27). */
    public static function locks(int $settlementId): LockSet
    {
        $set = (new LockSet)->add('deduction_settlements', ['id' => $settlementId])->add('money_fact_balances', ['deduction_settlement_id' => $settlementId]);

        foreach (DB::table('expenses')->where('deduction_settlement_id', $settlementId)->orderBy('id')->pluck('id') as $expense) {
            if (is_numeric($expense)) {
                $set = $set->add('money_fact_balances', ['expense_id' => (int) $expense]);
            }
        }

        return $set;
    }

    /** The settlement contra'd and re-recorded on the invoice and payment named; the new row's id. */
    public function rerecord(CommandContext $context, int $companyId, int $id, int $invoiceId, int $paymentId, string $reason, string $businessDate, int $line): int
    {
        $row = DB::table('deduction_settlements')->where('id', $id)->first();

        if ($row === null) {
            throw Refused::rejected('C-16', ['reason' => 'undisposed']);
        }

        $amount = Row::text($row, 'amount');
        $commandId = $context->request->commandId;
        $ended = DB::table('deduction_settlements')->where('id', $id)->where('state', 'ACTIVE')->update(['state' => 'CONTRAED']);

        if ($ended !== 1) {
            throw Refused::conflict('ST-07', ['settlement' => 'ended']);
        }

        DB::table('financial_contras')->insert(['company_id' => $companyId, 'deduction_settlement_id' => $id, 'amount' => $amount, 'reason' => $reason, 'business_date' => $businessDate,
            'recorded_by' => $context->request->actorUserId, 'command_id' => $commandId, 'command_line_no' => $line, 'recorded_at' => $context->now()]);
        GuardUpdate::apply($context->connection, 'money_fact_balances', [['key' => ['deduction_settlement_id' => $id], 'deltas' => ['contra_amount' => $amount]]]);
        $moved = $this->successor($context, $companyId, $row, $invoiceId, $paymentId, $businessDate, $line);
        // The expense moves once with its settlement: the evidence's expensed amount nets to nothing, so it is not touched.
        $expense = $this->contraExpense($context, $companyId, $id, $reason, $businessDate, null, $line);

        if ($expense !== null) {
            $this->cost->settlementExpense($context, $moved, $expense['kind'], self::projectOf($invoiceId), $businessDate);
        }

        return $moved;
    }

    /** A new ACTIVE settlement copying the row's type and evidence, with its money-fact guard; its id. */
    public function successor(CommandContext $context, int $companyId, object $row, int $invoiceId, int $paymentId, string $businessDate, int $line): int
    {
        $now = $context->now();
        $moved = DB::table('deduction_settlements')->insertGetId(['company_id' => $companyId, 'client_organization_id' => (int) Row::text($row, 'client_organization_id'), 'invoice_id' => $invoiceId, 'payment_id' => $paymentId,
            'settlement_class' => Row::text($row, 'settlement_class'), 'tax_type_id' => self::id(Row::nullable($row, 'tax_type_id')), 'fee_type' => Row::nullable($row, 'fee_type'), 'tax_direction' => Row::nullable($row, 'tax_direction'),
            'specific_type_key' => Row::text($row, 'specific_type_key'), 'treatment_rule_id' => self::id(Row::nullable($row, 'treatment_rule_id')), 'amount' => Row::text($row, 'amount'), 'evidence_document_id' => (int) Row::text($row, 'evidence_document_id'), 'evidence_type' => Row::text($row, 'evidence_type'),
            'business_date' => $businessDate, 'state' => 'ACTIVE', 'command_id' => $context->request->commandId, 'command_line_no' => $line, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId]);
        GuardUpdate::bear($context->connection, 'money_fact_balances', ['deduction_settlement_id' => $moved], ['company_id' => $companyId, 'fact_kind' => 'SETTLEMENT', 'amount' => Row::text($row, 'amount'), 'recorded_at' => $now]);

        return $moved;
    }

    /**
     * What of the settlement's expense still stands, contra'd: its amount and kind, or null where it has none. The evidence's expensed amount is the caller's.
     *
     * @return array{amount: string, kind: string}|null
     */
    public function contraExpense(CommandContext $context, int $companyId, int $settlementId, string $reason, string $businessDate, ?int $caseId, int $line): ?array
    {
        $row = DB::table('expenses as x')->join('money_fact_balances as f', 'f.expense_id', '=', 'x.id')->where('x.deduction_settlement_id', $settlementId)->whereColumn('f.contra_amount', '<', 'f.amount')->orderBy('x.id')
            ->first(['x.id', 'x.kind', 'f.amount', 'f.contra_amount']);

        if ($row === null) {
            return null;
        }

        $standing = Money::of(Row::text($row, 'amount'))->minus(Money::of(Row::text($row, 'contra_amount')))->toString();
        DB::table('financial_contras')->insert(['company_id' => $companyId, 'expense_id' => (int) Row::text($row, 'id'), 'amount' => $standing, 'reason' => $reason, 'business_date' => $businessDate, 'correction_case_id' => $caseId,
            'recorded_by' => $context->request->actorUserId, 'command_id' => $context->request->commandId, 'command_line_no' => self::EXPENSE_LINES + $line, 'recorded_at' => $context->now()]);
        GuardUpdate::apply($context->connection, 'money_fact_balances', [['key' => ['expense_id' => (int) Row::text($row, 'id')], 'deltas' => ['contra_amount' => $standing]]]);

        return ['amount' => $standing, 'kind' => Row::text($row, 'kind')];
    }

    public static function projectOf(int $invoiceId): ?int
    {
        $project = DB::table('invoices')->where('id', $invoiceId)->value('project_id');

        return is_numeric($project) ? (int) $project : null;
    }

    private static function id(?string $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
