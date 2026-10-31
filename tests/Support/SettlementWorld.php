<?php

namespace Tests\Support;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\FIN\Actions\RecordContra;
use App\Modules\FIN\Actions\RecordDeductionSettlement;
use App\Modules\FIN\Actions\ResolveResidualSettlement;
use App\Modules\FIN\Data\SettlementEntry;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\Payment;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use Database\Factories\ORG\TaxTreatmentRuleFactory;
use Database\Factories\ORG\TaxTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TASK-052's world (TL-04; TL-05; TL-08): a deduction's evidence with its guard, a claimed fee or tax on a payment, a tax type with its treatment, and ROUTE-255, ROUTE-260 and ROUTE-263's actions as their own commands.
 * Static, so the closures of the two-connection harness can call them (Sessions::run).
 */
final class SettlementWorld
{
    public static function evidence(int $companyId, string $type, string $proven): EvidenceDocument
    {
        $evidence = EvidenceDocument::factory()->create(['company_id' => $companyId, 'evidence_type' => $type, 'proven_amount' => $proven, 'identity_key' => 'penerbit|'.$type.'|'.Str::random(8).'|2026-10-01']);
        DB::table('evidence_balances')->insert(['evidence_document_id' => $evidence->id, 'proven_amount' => $proven, 'recorded_at' => now()]);

        return $evidence;
    }

    /** A fee claim on the payment, its guard raised as AX-18 raises it; its id. */
    public static function claimFee(int $paymentId, string $amount, string $feeType = 'BIAYA_SIPLAH'): int
    {
        $id = DB::table('payment_claimed_deductions')->insertGetId(['payment_id' => $paymentId, 'deduction_class' => 'FEE', 'fee_type' => $feeType, 'claimed_amount' => $amount, 'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => DB::table('users')->orderBy('id')->value('id')]);
        DB::table('application_source_balances')->where('payment_id', $paymentId)->update(['claimed_deductions_amount' => DB::raw("claimed_deductions_amount + {$amount}")]);

        return $id;
    }

    public static function claimTax(int $paymentId, int $taxTypeId, string $amount): int
    {
        $id = DB::table('payment_claimed_deductions')->insertGetId(['payment_id' => $paymentId, 'deduction_class' => 'TAX', 'tax_type_id' => $taxTypeId, 'claimed_amount' => $amount, 'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => DB::table('users')->orderBy('id')->value('id')]);
        DB::table('application_source_balances')->where('payment_id', $paymentId)->update(['claimed_deductions_amount' => DB::raw("claimed_deductions_amount + {$amount}")]);

        return $id;
    }

    /** A withholding or collection tax with its treatment on the date; its id. */
    public static function taxType(?int $companyId, string $context = 'PAYMENT_WITHHELD', string $treatment = 'SETTLEMENT_NO_EXPENSE', string $from = '2026-01-01'): int
    {
        $type = TaxTypeFactory::new()->createOne(['category' => 'WITHHOLDING', 'code' => 'PPH-'.Str::upper(Str::random(8))]);
        TaxTreatmentRuleFactory::new()->createOne(['tax_type_id' => $type->id, 'company_id' => $companyId, 'transaction_context' => $context, 'treatment' => $treatment, 'effective' => "[{$from},)"]);

        return $type->id;
    }

    public static function rule(int $taxTypeId, string $context, string $treatment, ?int $companyId = null, string $from = '2026-01-01'): void
    {
        TaxTreatmentRuleFactory::new()->createOne(['tax_type_id' => $taxTypeId, 'company_id' => $companyId, 'transaction_context' => $context, 'treatment' => $treatment, 'effective' => "[{$from},)"]);
    }

    /** ROUTE-255's action for a fee, as its own command; the outcome. */
    public static function fee(int $actorId, int $invoiceId, int $paymentId, string $amount, int $evidenceId, string $feeType = 'BIAYA_SIPLAH', ?string $commandId = null): Outcome
    {
        return self::record($actorId, $invoiceId, new SettlementEntry($invoiceId, $paymentId, 'FEE', null, $feeType, null, $amount, $evidenceId, BusinessDate::today(), false, 'Nota biaya terverifikasi'), $commandId);
    }

    public static function tax(int $actorId, int $invoiceId, int $paymentId, string $amount, int $evidenceId, int $taxTypeId, string $direction, ?string $commandId = null): Outcome
    {
        return self::record($actorId, $invoiceId, new SettlementEntry($invoiceId, $paymentId, 'TAX', $taxTypeId, null, $direction, $amount, $evidenceId, BusinessDate::today(), false, 'Bukti resmi'), $commandId);
    }

    private static function record(int $actorId, int $invoiceId, SettlementEntry $entry, ?string $commandId): Outcome
    {
        $invoice = Invoice::query()->findOrFail($invoiceId);

        return app(RecordDeductionSettlement::class)->run($commandId ?? (string) Str::uuid(), $actorId, $invoice->company_id, $invoice->client_organization_id, $entry, ['invoice' => $invoiceId, 'payment' => $entry->paymentId, 'amount' => $entry->amount, 'type' => $entry->specificType(), 'evidence' => $entry->evidenceId]);
    }

    /** ROUTE-260's action, as its own command; the outcome. */
    public static function contra(int $actorId, int $companyId, string $kind, int $factId, ?string $amount = null, ?string $commandId = null): Outcome
    {
        return app(RecordContra::class)->run($commandId ?? (string) Str::uuid(), $actorId, $companyId, $kind, $factId, $amount, 'Salah catat', BusinessDate::today(), false, ['kind' => $kind, 'fact' => $factId, 'amount' => $amount]);
    }

    /** ROUTE-263's action, as its own command; the outcome. */
    public static function resolve(int $actorId, int $companyId, int $settlementId, ?int $invoiceId, ?int $paymentId, ?int $issuerEvidenceId, ?string $commandId = null): Outcome
    {
        return app(ResolveResidualSettlement::class)->run($commandId ?? (string) Str::uuid(), $actorId, $companyId, $settlementId, $invoiceId, $paymentId, $issuerEvidenceId, 'Bukti penerbit', BusinessDate::today(), false, ['settlement' => $settlementId, 'invoice' => $invoiceId, 'payment' => $paymentId]);
    }

    /** A manual expense with its money-fact guard, as TASK-054 will post it; its id. */
    public static function manualExpense(int $companyId, ?int $projectId, string $amount, ?int $billId = null): int
    {
        $id = DB::table('expenses')->insertGetId(['company_id' => $companyId, 'project_id' => $projectId, 'kind' => 'MANUAL', 'category' => 'OPERASIONAL', 'amount' => $amount, 'source_bill_evidence_id' => $billId, 'source_bill_evidence_type' => $billId === null ? null : 'SUPPLIER_BILL', 'business_date' => BusinessDate::today(), 'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => DB::table('users')->orderBy('id')->value('id')]);
        DB::table('money_fact_balances')->insert(['company_id' => $companyId, 'expense_id' => $id, 'fact_kind' => 'EXPENSE', 'amount' => $amount, 'recorded_at' => now()]);

        return $id;
    }

    /** A settlement left RESIDUAL by its payment's contra, as CM-19 leaves it; the case's id. */
    public static function residual(int $actorId, Payment $payment, string $contra, int $settlementId): int
    {
        $word = PaymentWorld::contraInCommand($actorId, $payment->id, $contra, PaymentWorld::keep($payment->id), [$settlementId => 'RESIDUAL']);

        if ($word !== 'COMMITTED') {
            throw new \RuntimeException("The contra answered {$word}.");
        }

        $case = DB::table('deduction_settlements')->where('id', $settlementId)->value('correction_case_id');

        return is_numeric($case) ? (int) $case : 0;
    }
}
