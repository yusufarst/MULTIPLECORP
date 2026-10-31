<?php

namespace Tests\Support;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\FIN\Actions\ApplyPayment;
use App\Modules\FIN\Actions\ContraPayment;
use App\Modules\FIN\Actions\CorrectBankCitation;
use App\Modules\FIN\Actions\ReallocateApplication;
use App\Modules\FIN\Actions\RecordPayment;
use App\Modules\FIN\Actions\VoidInvoice;
use App\Modules\FIN\Data\ContraEntry;
use App\Modules\FIN\Data\InvoiceCorrection;
use App\Modules\FIN\Data\PaymentEntry;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\Payment;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use App\Support\RowHandle;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Database\Factories\ORG\CompanyBankAccountFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TASK-051's world (TL-04; TL-05; TL-08): the company's accounts, statement lines and proofs, a payment through ROUTE-251's own action, and until TASK-052 and TASK-053 exist the synthetic refund the dispositions act on, its guards moved as AX-23 will move them.
 * Static, so the closures of the two-connection harness can call them (Sessions::run).
 */
final class PaymentWorld
{
    public static function account(int $companyId): int
    {
        return CompanyBankAccountFactory::new()->createOne(['company_id' => $companyId])->id;
    }

    public static function accountHandle(int $accountId): string
    {
        return RowHandle::of('company_bank_accounts', $accountId);
    }

    public static function proof(int $companyId, string $type = 'PAYMENT_PROOF', ?string $issuer = null): EvidenceDocument
    {
        return EvidenceDocumentFactory::new()->createOne(['company_id' => $companyId, 'evidence_type' => $type, ...($issuer === null ? [] : ['declared_issuer' => $issuer])]);
    }

    /** A statement line on the account, its guard born as NX-08 bears it; its id. */
    public static function line(int $companyId, int $accountId, string $amount, string $reference = 'TRX-1', ?string $date = null): int
    {
        $evidence = self::proof($companyId, 'BANK_STATEMENT');
        $id = DB::table('bank_statement_lines')->insertGetId(['company_id' => $companyId, 'company_bank_account_id' => $accountId, 'statement_evidence_id' => $evidence->id, 'statement_evidence_type' => 'BANK_STATEMENT', 'business_date' => $date ?? BusinessDate::today(), 'amount' => $amount, 'line_reference' => $reference,
            'state' => 'ACTIVE', 'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => DB::table('users')->orderBy('id')->value('id')]);
        DB::table('evidence_balances')->insert(['bank_statement_line_id' => $id, 'proven_amount' => $amount, 'recorded_at' => now()]);

        return $id;
    }

    /**
     * ROUTE-251's entry with a proof's reason standing in for proof, unless the test names one.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function entry(int $companyId, int $clientId, string $amount, array $fields = []): PaymentEntry
    {
        $values = [
            'companyId' => $companyId, 'clientId' => $clientId, 'businessDate' => BusinessDate::today(), 'prefilled' => false, 'amount' => $amount, 'method' => 'TRANSFER', 'accountId' => null, 'lineId' => null, 'reference' => null, 'intermediary' => null,
            'evidence' => [], 'unevidencedReason' => 'Bukti menyusul', 'claimed' => [], 'applications' => [], 'resolves' => [], 'recoveryFor' => null, 'transferFrom' => null, 'rerecords' => null, 'acknowledged' => [], 'overrideReason' => null,
            ...$fields,
        ];

        if ($values['accountId'] === null && $values['method'] !== 'CASH') {
            $values['method'] = 'CASH';
        }

        return new PaymentEntry(...$values);
    }

    /**
     * A payment through its own command; the outcome.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function record(int $actorId, int $companyId, int $clientId, string $amount, array $fields = [], ?string $commandId = null): Outcome
    {
        $entry = self::entry($companyId, $clientId, $amount, $fields);

        return app(RecordPayment::class)->run($commandId ?? (string) Str::uuid(), $actorId, $entry, ['amount' => $amount, 'applications' => array_map('strval', $entry->applications), 'account' => $entry->accountId, 'line' => $entry->lineId, 'date' => $entry->businessDate, 'acknowledged' => $entry->acknowledged]);
    }

    /**
     * The payment recorded on the invoice's client and company, applying `$applied` of it; the payment.
     */
    public static function paid(int $actorId, int $invoiceId, string $amount, ?string $applied = null): Payment
    {
        $invoice = Invoice::query()->findOrFail($invoiceId);
        $outcome = self::record($actorId, $invoice->company_id, $invoice->client_organization_id, $amount, ['applications' => $applied === null ? [] : [$invoiceId => $applied]]);

        return Payment::query()->findOrFail($outcome->resultId);
    }

    /** ROUTE-252's action as its own command; its word. */
    public static function applyInCommand(int $actorId, int $paymentId, int $invoiceId, string $amount): string
    {
        $payment = Payment::query()->findOrFail($paymentId);

        return InvoiceWorld::word(app(ApplyPayment::class)->run((string) Str::uuid(), $actorId, ['payment' => $paymentId, 'credit' => null, 'company' => $payment->company_id, 'client' => $payment->client_organization_id, 'name' => $payment->public_id], [$invoiceId => $amount], [], BusinessDate::today(), false, ['invoice' => $invoiceId, 'amount' => $amount]));
    }

    /** ROUTE-253's action as its own command; its word. */
    public static function reallocateInCommand(int $actorId, int $paymentId, int $applicationId, int $targetId, string $amount): string
    {
        return InvoiceWorld::word(app(ReallocateApplication::class)->run((string) Str::uuid(), $actorId, Payment::query()->findOrFail($paymentId), $applicationId, $targetId, $amount, [], BusinessDate::today(), false, 'Salah invoice', ['application' => $applicationId, 'target' => $targetId, 'amount' => $amount]));
    }

    /**
     * ROUTE-254's action as its own command; its word.
     *
     * @param  array<int, array{action: string, amount: ?string}>  $applications
     * @param  array<int, string>  $settlements
     */
    public static function contraInCommand(int $actorId, int $paymentId, string $amount, array $applications = [], array $settlements = [], ?int $rerecorded = null): string
    {
        return InvoiceWorld::word(app(ContraPayment::class)->run((string) Str::uuid(), $actorId, Payment::query()->findOrFail($paymentId), new ContraEntry($amount, 'Salah catat', BusinessDate::today(), false, $rerecorded, $applications, $settlements), ['amount' => $amount, 'applications' => $applications, 'settlements' => $settlements]));
    }

    /** ROUTE-262's citation correction as its own command; its word. */
    public static function citeInCommand(int $actorId, int $paymentId, int $lineId): string
    {
        return InvoiceWorld::word(app(CorrectBankCitation::class)->run((string) Str::uuid(), $actorId, Payment::query()->findOrFail($paymentId), $lineId, 'Salah baris'));
    }

    /** An invoice void as its own command, every reduction released (M-37); its word. */
    public static function voidInCommand(int $actorId, int $invoiceId): string
    {
        $invoice = Invoice::query()->findOrFail($invoiceId);
        $applications = [];

        foreach (DB::table('payment_applications')->where('invoice_id', $invoiceId)->where('state', 'ACTIVE')->pluck('id') as $id) {
            $applications[(int) $id] = InvoiceCorrection::RELEASE;
        }

        $billed = DB::table('invoice_balances')->where('invoice_id', $invoiceId)->whereNotNull('current_billing_act_id')->exists();
        $input = new InvoiceCorrection($billed ? 'CANCELLATION' : 'UNBILLED_ENTRY_ERROR', 'Batal', BusinessDate::today(), false, applications: $applications);

        return InvoiceWorld::word(app(VoidInvoice::class)->run((string) Str::uuid(), $actorId, $invoice, $invoice->lock_version, $input, ['invoice' => $invoiceId, 'applications' => $applications]));
    }

    /**
     * A refund of `$amount` backed by an application of the payment, as AX-23 will commit it (synthetic until TASK-053).
     *
     * @return array{refund: int, application: int}
     */
    public static function refund(int $paymentId, string $amount): array
    {
        $payment = Payment::query()->findOrFail($paymentId);
        $actor = DB::table('users')->orderBy('id')->value('id');
        $refund = DB::table('disbursements')->insertGetId(['public_id' => (string) Str::uuid(), 'company_id' => $payment->company_id, 'client_organization_id' => $payment->client_organization_id, 'business_date' => BusinessDate::today(), 'amount' => $amount, 'kind' => 'REFUND', 'company_bank_account_id' => self::account($payment->company_id),
            'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $actor]);
        DB::table('money_fact_balances')->insert(['disbursement_id' => $refund, 'company_id' => $payment->company_id, 'fact_kind' => 'REFUND', 'amount' => $amount, 'backed_amount' => $amount, 'recorded_at' => now()]);
        $application = DB::table('payment_applications')->insertGetId(['company_id' => $payment->company_id, 'client_organization_id' => $payment->client_organization_id, 'payment_id' => $paymentId, 'refund_disbursement_id' => $refund, 'target_kind' => 'REFUND', 'amount' => $amount, 'business_date' => BusinessDate::today(),
            'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $actor]);
        DB::table('application_source_balances')->where('payment_id', $paymentId)->update(['refund_backed_amount' => DB::raw("refund_backed_amount + {$amount}")]);

        return ['refund' => $refund, 'application' => $application];
    }

    /** A claimed deduction on the payment, its guard raised as AX-18 raises it. */
    public static function claim(int $paymentId, string $amount): void
    {
        DB::table('payment_claimed_deductions')->insert(['payment_id' => $paymentId, 'deduction_class' => 'FEE', 'fee_type' => 'BIAYA_TRANSFER', 'claimed_amount' => $amount, 'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => DB::table('users')->orderBy('id')->value('id')]);
        DB::table('application_source_balances')->where('payment_id', $paymentId)->update(['claimed_deductions_amount' => DB::raw("claimed_deductions_amount + {$amount}")]);
    }

    /** The disposition of every active row as KEEP, for a contra that needs none (ST-07). */
    public static function keep(int $paymentId): array
    {
        $applications = [];

        foreach (DB::table('payment_applications')->where('payment_id', $paymentId)->where('state', 'ACTIVE')->pluck('id') as $id) {
            $applications[(int) $id] = ['action' => ContraEntry::KEEP, 'amount' => null];
        }

        return $applications;
    }
}
