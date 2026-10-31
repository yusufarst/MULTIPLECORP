<?php

namespace App\Modules\FIN\Services;

use App\Support\Command\CommandContext;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * A settlement re-recorded where it now belongs (DATABASE §11.4; L-28; C-45): ACTIVE → CONTRAED with a new ACTIVE row citing the same evidence on the invoice or payment
 * named, a `financial_contras` row and its money-fact guard, a fee's expense moving exactly once. The invoice and source guards are the caller's, netted in their one statement.
 */
final class SettlementMoves
{
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

    /** The settlement contra'd and re-recorded on the invoice and payment named; the new row's id. */
    public function rerecord(CommandContext $context, int $companyId, int $id, int $invoiceId, int $paymentId, string $reason, string $businessDate, int $line): int
    {
        $row = DB::table('deduction_settlements')->where('id', $id)->first();

        if ($row === null) {
            throw Refused::rejected('C-16', ['reason' => 'undisposed']);
        }

        $amount = Row::text($row, 'amount');
        $now = $context->now();
        $commandId = $context->request->commandId;
        $ended = DB::table('deduction_settlements')->where('id', $id)->where('state', 'ACTIVE')->update(['state' => 'CONTRAED']);

        if ($ended !== 1) {
            throw Refused::conflict('ST-07', ['settlement' => 'ended']);
        }

        DB::table('financial_contras')->insert(['company_id' => $companyId, 'deduction_settlement_id' => $id, 'amount' => $amount, 'reason' => $reason, 'business_date' => $businessDate,
            'recorded_by' => $context->request->actorUserId, 'command_id' => $commandId, 'command_line_no' => $line, 'recorded_at' => $now]);
        GuardUpdate::apply($context->connection, 'money_fact_balances', [['key' => ['deduction_settlement_id' => $id], 'deltas' => ['contra_amount' => $amount]]]);
        $moved = DB::table('deduction_settlements')->insertGetId(['company_id' => $companyId, 'client_organization_id' => (int) Row::text($row, 'client_organization_id'), 'invoice_id' => $invoiceId, 'payment_id' => $paymentId,
            'settlement_class' => Row::text($row, 'settlement_class'), 'tax_type_id' => self::id(Row::nullable($row, 'tax_type_id')), 'fee_type' => Row::nullable($row, 'fee_type'), 'tax_direction' => Row::nullable($row, 'tax_direction'),
            'specific_type_key' => Row::text($row, 'specific_type_key'), 'treatment_rule_id' => self::id(Row::nullable($row, 'treatment_rule_id')), 'amount' => $amount, 'evidence_document_id' => (int) Row::text($row, 'evidence_document_id'), 'evidence_type' => Row::text($row, 'evidence_type'),
            'business_date' => $businessDate, 'state' => 'ACTIVE', 'command_id' => $commandId, 'command_line_no' => $line, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId]);
        GuardUpdate::bear($context->connection, 'money_fact_balances', ['deduction_settlement_id' => $moved], ['company_id' => $companyId, 'fact_kind' => 'SETTLEMENT', 'amount' => $amount, 'recorded_at' => $now]);

        return $moved;
    }

    private static function id(?string $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
