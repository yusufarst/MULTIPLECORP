<?php

use App\Modules\DOC\Models\BankStatementLine;
use App\Modules\DOC\Models\Document;
use App\Modules\FIN\Models\DeductionSettlement;
use App\Modules\FIN\Models\Disbursement;
use App\Modules\FIN\Models\FinancialContra;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\OpeningCustomerCredit;
use App\Modules\FIN\Models\OtherCashReceipt;
use App\Modules\FIN\Models\Payment;
use App\Modules\FIN\Models\PaymentApplication;
use App\Modules\FIN\Models\PaymentClaimedDeduction;
use App\Modules\FIN\Models\PrepaymentKuitansiLink;
use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\TaxType;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PUR\Models\Purchase;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\FIN\DeductionSettlementFactory;
use Database\Factories\FIN\DisbursementFactory;
use Database\Factories\FIN\InvoiceFactory;
use Database\Factories\OPS\ImportRowFactory;
use Database\Factories\ORG\CompanyBankAccountFactory;
use Database\Factories\ORG\TaxTreatmentRuleFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-009), criterion 2 for DATABASE §4.12's money tables and §11.3–§11.5: a payment lands in its
// company's own account and cites its company's bank line, set once and corrected only with reason (C-19;
// C-32; C-59); applications and settlements stay within one company and client (C-14), leave ACTIVE once
// by their typed exits (C-44; C-60), one ACTIVE settlement per type (C-22); the source and money-fact guards
// keep applications, refund backing, settlements and contras within real money (C-13; C-22; C-44; C-45);
// a disbursement and other Cash-In cite their own references by kind (C-58; L-08); a Kuitansi link is one per
// payment and invoice (C-43); a contra names one original (C-45).

uses(RefreshDatabase::class);

it('lands a payment in its company\'s own account unless in cash, its references its company\'s and client\'s (C-14; C-32; C-59; BR-FIN-07)', function () {
    $payment = Payment::factory()->create();
    $own = ['company_id' => $payment->company_id, 'client_organization_id' => $payment->client_organization_id];
    $transferOut = Disbursement::factory()->create(['kind' => 'INTERCOMPANY_TRANSFER_OUT', 'purchase_id' => null]);

    Refusal::assert(Refusal::CHECK, 'payments_c32_account_unless_cash', fn () => Payment::factory()->create([...$own, 'company_bank_account_id' => null]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'payments_c32_bank_account_foreign', fn () => Payment::factory()->create([...$own, 'company_bank_account_id' => CompanyBankAccountFactory::new()->createOne()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'payments_c19_statement_line_foreign', fn () => Payment::factory()->create([...$own, 'bank_statement_line_id' => BankStatementLine::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'payments_c14_recovery_foreign', fn () => Payment::factory()->create([...$own, 'recovery_for_invoice_id' => InvoiceFactory::of($payment->company_id, ClientOrganization::factory()->create()->id)->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'payments_c59_rerecords_foreign', fn () => Payment::factory()->create([...$own, 'rerecords_payment_id' => Payment::factory()->create()->id]));
    Refusal::assert(Refusal::CHECK, 'payments_transfer_typed', fn () => Payment::factory()->create([...$own, 'transfer_from_disbursement_id' => $transferOut->id, 'transfer_from_disbursement_kind' => 'REFUND']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'payments_transfer_foreign', fn () => Payment::factory()->create([
        ...$own, 'transfer_from_disbursement_id' => Disbursement::factory()->create()->id, 'transfer_from_disbursement_kind' => 'INTERCOMPANY_TRANSFER_OUT',
    ]));

    expect(Payment::factory()->create([...$own, 'method' => 'CASH', 'company_bank_account_id' => null])->exists)->toBeTrue()
        // Client money moved from another company by a real transfer, an intentional cross-company link (§15).
        ->and(Payment::factory()->create([...$own, 'transfer_from_disbursement_id' => $transferOut->id, 'transfer_from_disbursement_kind' => 'INTERCOMPANY_TRANSFER_OUT'])->exists)->toBeTrue()
        ->and(Payment::factory()->create([...$own, 'rerecords_payment_id' => $payment->id, 'recovery_for_invoice_id' => InvoiceFactory::of($payment->company_id, $payment->client_organization_id)->id])->exists)->toBeTrue();
});

it('cites a bank line once when it arrives, then only by a reasoned correction, never back to none (C-19; C-59; L-22)', function () {
    $payment = Payment::factory()->create();
    $line = fn (): int => BankStatementLine::factory()->create(['company_id' => $payment->company_id, 'company_bank_account_id' => $payment->company_bank_account_id])->id;
    $row = fn () => DB::table('payments')->where('id', $payment->id);
    $correction = fn (int $minutes = 0): array => ['bank_statement_line_id' => $line(), 'bank_citation_corrected_at' => now()->addMinutes($minutes), 'bank_citation_corrected_by' => User::factory()->create()->id, 'bank_citation_corrected_reason' => 'Mutasi yang dikutip salah baris'];

    $row()->update(['bank_statement_line_id' => $line()]);

    Refusal::assert(Refusal::CHECK, 'payments_c19_citation_once_then_corrected', fn () => $row()->update(['bank_statement_line_id' => null]));
    Refusal::assert(Refusal::CHECK, 'payments_c19_citation_once_then_corrected', fn () => $row()->update(['bank_statement_line_id' => $line()]));
    Refusal::assert(Refusal::CHECK, 'payments_c19_citation_correction', fn () => $row()->update([...$correction(), 'bank_citation_corrected_reason' => null]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table payments', fn () => $row()->update(['amount' => '1.00']));

    $row()->update($correction());
    $row()->update($correction(5));

    Refusal::assert(Refusal::CHECK, 'payments_c19_citation_once_then_corrected', fn () => $row()->update(['bank_statement_line_id' => $line()]));

    expect($row()->value('bank_citation_corrected_reason'))->toBe('Mutasi yang dikutip salah baris');
});

it('corrects a claimed deduction by a superseding row of its payment with reason, once (C-59; L-28)', function () {
    $claimed = PaymentClaimedDeduction::factory()->create();
    $supersede = fn (array $attributes = []): PaymentClaimedDeduction => PaymentClaimedDeduction::factory()->create([
        'payment_id' => $claimed->payment_id, 'supersedes_claimed_deduction_id' => $claimed->id, 'claimed_amount' => '10000.00', 'reason' => 'Nilai bukti potong dikoreksi', ...$attributes,
    ]);

    Refusal::assert(Refusal::CHECK, 'payment_claimed_deductions_type_by_class', fn () => PaymentClaimedDeduction::factory()->create(['tax_type_id' => TaxType::factory()->create()->id]));
    Refusal::assert(Refusal::CHECK, 'payment_claimed_deductions_type_by_class', fn () => PaymentClaimedDeduction::factory()->create(['deduction_class' => 'TAX']));
    Refusal::assert(Refusal::CHECK, 'payment_claimed_deductions_c59_reason', fn () => $supersede(['reason' => null]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'payment_claimed_deductions_c59_supersedes_foreign', fn () => $supersede(['payment_id' => Payment::factory()->create()->id]));

    $supersede();

    Refusal::assert(Refusal::UNIQUE, 'payment_claimed_deductions_c59_linear_chain', fn () => $supersede());

    expect(PaymentClaimedDeduction::query()->where('payment_id', $claimed->payment_id)->count())->toBe(2);
});

it('keeps a source\'s applications, refund backing and settlements within its real money (C-13; C-22; C-44; C-45; L-28; L-46)', function () {
    $payment = Payment::factory()->create(['amount' => '1000000.00']);
    $source = ['company_id' => $payment->company_id, 'client_organization_id' => $payment->client_organization_id, 'gross_amount' => 1000000];
    DB::table('application_source_balances')->insert(['payment_id' => $payment->id, ...$source]);
    $row = fn () => DB::table('application_source_balances')->where('payment_id', $payment->id);

    Refusal::assert(Refusal::CHECK, 'application_source_balances_one_source', fn () => DB::table('application_source_balances')->insert([
        ...$source, 'payment_id' => Payment::factory()->create(['company_id' => $payment->company_id, 'client_organization_id' => $payment->client_organization_id])->id,
        'opening_customer_credit_id' => OpeningCustomerCredit::factory()->create(['company_id' => $payment->company_id, 'client_organization_id' => $payment->client_organization_id])->id,
    ]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'application_source_balances_c14_payment_foreign', fn () => DB::table('application_source_balances')->insert([
        'payment_id' => Payment::factory()->create(['company_id' => $payment->company_id])->id, 'company_id' => $payment->company_id,
        'client_organization_id' => $payment->client_organization_id, 'gross_amount' => 1,
    ]));
    Refusal::assert(Refusal::CHECK, 'application_source_balances_c13_capacity', fn () => $row()->update(['invoice_applied_amount' => 600000, 'refund_backed_amount' => 400001]));
    Refusal::assert(Refusal::CHECK, 'application_source_balances_amounts', fn () => $row()->update(['contra_amount' => 1000001]));
    Refusal::assert(Refusal::CHECK, 'application_source_balances_c44_shortfall', fn () => $row()->update(['refund_shortfall_amount' => 100000]));
    Refusal::assert(Refusal::CHECK, 'application_source_balances_c22_settled', fn () => $row()->update(['claimed_deductions_amount' => 15000, 'settled_amount' => 15001]));
    Refusal::assert(Refusal::CHECK, 'application_source_balances_c45_contra_settled', fn () => $row()->update(['contra_amount' => 1000000, 'claimed_deductions_amount' => 15000, 'settled_amount' => 15000]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table application_source_balances', fn () => $row()->update(['gross_amount' => 2000000]));

    // A shortfall is admitted only on a source left with no invoice application and no capacity (L-46).
    $row()->update(['contra_amount' => 800000, 'refund_backed_amount' => 200000, 'refund_shortfall_amount' => 100000]);

    expect($row()->value('capacity_amount'))->toBe('0.00');
});

it('applies a source within its company and client, ending once by the exits of its target, a contra exit naming its contra (C-14; C-44; L-46; CM-21)', function () {
    $application = PaymentApplication::factory()->create();
    $payment = Payment::query()->whereKey($application->payment_id)->firstOrFail();
    $own = ['payment_id' => $payment->id, 'company_id' => $payment->company_id, 'client_organization_id' => $payment->client_organization_id];
    $refund = DisbursementFactory::refund($payment->company_id, $payment->client_organization_id);
    $row = fn (int $id) => DB::table('payment_applications')->where('id', $id);
    $end = fn (string $reason, array $attributes = []): array => ['state' => 'RELEASED', 'end_reason' => $reason, 'end_command_id' => (string) Str::uuid7(), ...$attributes];
    $contra = FinancialContra::factory()->create(['payment_id' => $payment->id])->id;

    Refusal::assert(Refusal::FOREIGN_KEY, 'payment_applications_c14_invoice_foreign', fn () => PaymentApplication::factory()->create([...$own, 'invoice_id' => InvoiceFactory::of($payment->company_id, ClientOrganization::factory()->create()->id)->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'payment_applications_c14_refund_foreign', fn () => PaymentApplication::factory()->create([
        ...$own, 'invoice_id' => null, 'target_kind' => 'REFUND', 'refund_disbursement_id' => DisbursementFactory::refund($payment->company_id, ClientOrganization::factory()->create()->id)->id,
    ]));
    Refusal::assert(Refusal::CHECK, 'payment_applications_target_by_kind', fn () => PaymentApplication::factory()->create([...$own, 'refund_disbursement_id' => $refund->id]));
    $exit = PaymentApplication::factory()->create([...$own, 'invoice_id' => null, 'target_kind' => 'REFUND', 'refund_disbursement_id' => $refund->id]);

    // A refund's application leaves only by RE_RECORDED, SOURCE_CONTRA or REFUND_CONTRA, a contra exit naming the contra of its source or of its refund.
    Refusal::assert(Refusal::CHECK, 'payment_applications_c44_exit_by_target', fn () => $row($exit->id)->update([...$end('REALLOCATED'), 'state' => 'SUPERSEDED']));
    Refusal::assert(Refusal::CHECK, 'payment_applications_c44_ended_once', fn () => $row($exit->id)->update($end('SOURCE_CONTRA', ['end_contra_id' => FinancialContra::factory()->create(['company_id' => $payment->company_id, 'payment_id' => Payment::factory()->create(['company_id' => $payment->company_id])->id])->id])));
    Refusal::assert(Refusal::CHECK, 'payment_applications_c44_ended_once', fn () => $row($exit->id)->update($end('REFUND_CONTRA', ['end_contra_id' => $contra])));
    // A re-recording commits with its successor from the payment that re-records the source.
    Refusal::assert(Refusal::CHECK, 'payment_applications_c44_superseded_by_successor', function () use ($row, $exit, $end): void {
        $row($exit->id)->update([...$end('RE_RECORDED'), 'state' => 'SUPERSEDED']);
        DB::statement('set constraints payment_applications_c44_superseded_by_successor immediate');
    });
    Refusal::assert(Refusal::CHECK, 'payment_applications_c44_end_contra', fn () => $row($application->id)->update($end('SOURCE_CONTRA')));
    Refusal::assert(Refusal::CHECK, 'payment_applications_end_columns', fn () => $row($application->id)->update(['state' => 'SUPERSEDED', 'end_reason' => 'REALLOCATED']));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table payment_applications', fn () => $row($application->id)->update(['amount' => '1.00']));

    $backing = PaymentApplication::factory()->create([...$own, 'invoice_id' => null, 'target_kind' => 'REFUND', 'refund_disbursement_id' => $refund->id]);
    $row($backing->id)->update($end('SOURCE_CONTRA', ['end_contra_id' => $contra]));
    $other = PaymentApplication::factory()->create([...$own, 'invoice_id' => null, 'target_kind' => 'REFUND', 'refund_disbursement_id' => DisbursementFactory::refund($payment->company_id, $payment->client_organization_id)->id]);

    Refusal::assert(Refusal::FOREIGN_KEY, 'payment_applications_c44_resolves_foreign', fn () => PaymentApplication::factory()->create([
        ...$own, 'invoice_id' => null, 'target_kind' => 'REFUND', 'refund_disbursement_id' => $refund->id, 'resolves_application_id' => $other->id,
    ]));
    Refusal::assert(Refusal::CHECK, 'payment_applications_c44_ended_once', fn () => $row($backing->id)->update($end('REFUND_CONTRA', ['end_contra_id' => $contra])));

    $row($application->id)->update([...$end('REALLOCATED'), 'state' => 'SUPERSEDED']);
    $successor = PaymentApplication::factory()->create([...$own, 'invoice_id' => $application->invoice_id, 'supersedes_application_id' => $application->id]);
    $resolving = PaymentApplication::factory()->create([...$own, 'invoice_id' => null, 'target_kind' => 'REFUND', 'refund_disbursement_id' => $refund->id, 'resolves_application_id' => $backing->id]);

    expect($successor->supersedes?->is($application))->toBeTrue()
        ->and($resolving->resolves?->is($backing))->toBeTrue();
});

it('settles one ACTIVE deduction per type, against the proof its class and direction take, moving ACTIVE → RESIDUAL → CONTRAED once (C-14; C-22; C-58; C-60)', function () {
    $settlement = DeductionSettlement::factory()->create();
    $own = ['payment_id' => $settlement->payment_id, 'invoice_id' => $settlement->invoice_id];
    $row = fn () => DB::table('deduction_settlements')->where('id', $settlement->id);
    $tax = TaxType::factory()->create();
    $withheld = fn (array $attributes = []): DeductionSettlement => DeductionSettlement::factory()->create([...$own, 'settlement_class' => 'TAX', 'fee_type' => null,
        'tax_type_id' => $tax->id, 'tax_direction' => 'WITHHELD', 'specific_type_key' => "{$tax->id}:WITHHELD",
        'treatment_rule_id' => TaxTreatmentRuleFactory::new()->createOne(['tax_type_id' => $tax->id])->id,
        'evidence_document_id' => DeductionSettlementFactory::evidence($settlement->company_id, 'TAX_WITHHOLDING_PROOF')->id, 'evidence_type' => 'TAX_WITHHOLDING_PROOF', ...$attributes]);
    $case = CorrectionCase::factory()->create(['company_id' => $settlement->company_id])->id;

    Refusal::assert(Refusal::UNIQUE, 'deduction_settlements_c22_one_active', fn () => DeductionSettlement::factory()->create([...$own, 'evidence_document_id' => $settlement->evidence_document_id]));
    Refusal::assert(Refusal::CHECK, 'deduction_settlements_c22_specific_type', fn () => DeductionSettlement::factory()->create([...$own, 'specific_type_key' => 'LAIN']));
    Refusal::assert(Refusal::CHECK, 'deduction_settlements_c58_evidence_type', fn () => $withheld(['evidence_type' => 'TAX_COLLECTION_PROOF',
        'evidence_document_id' => DeductionSettlementFactory::evidence($settlement->company_id, 'TAX_COLLECTION_PROOF')->id]));
    Refusal::assert(Refusal::CHECK, 'deduction_settlements_type_by_class', fn () => $withheld(['treatment_rule_id' => null]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'deduction_settlements_c14_invoice_foreign', fn () => DeductionSettlement::factory()->create([
        'payment_id' => $settlement->payment_id, 'invoice_id' => InvoiceFactory::of($settlement->company_id, ClientOrganization::factory()->create()->id)->id,
    ]));
    Refusal::assert(Refusal::CHECK, 'deduction_settlements_c60_residual_case', fn () => $row()->update(['state' => 'RESIDUAL']));
    Refusal::assert(Refusal::CHECK, 'deduction_settlements_c60_transition', fn () => $row()->update(['correction_case_id' => $case]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table deduction_settlements', fn () => $row()->update(['amount' => '1.00']));

    $row()->update(['state' => 'RESIDUAL', 'correction_case_id' => $case]);

    Refusal::assert(Refusal::CHECK, 'deduction_settlements_c60_transition', fn () => $row()->update(['state' => 'ACTIVE']));

    // The residual frees its key: the deduction is re-recorded on the invoice its evidence covers.
    $rerecorded = DeductionSettlement::factory()->create([...$own, 'evidence_document_id' => $settlement->evidence_document_id]);
    $row()->update(['state' => 'CONTRAED']);

    Refusal::assert(Refusal::CHECK, 'deduction_settlements_c60_transition', fn () => $row()->update(['state' => 'RESIDUAL']));

    expect($rerecorded->state ?? 'ACTIVE')->toBe('ACTIVE')
        ->and($withheld()->specific_type_key)->toBe("{$tax->id}:WITHHELD");
});

it('links a pre-payment Kuitansi once per payment and invoice, unlinked once with reason (C-43; AX-31)', function () {
    $link = PrepaymentKuitansiLink::factory()->create();
    $row = fn () => DB::table('prepayment_kuitansi_links')->where('id', $link->id);
    $again = ['company_id' => $link->company_id, 'document_id' => $link->document_id, 'invoice_id' => $link->invoice_id, 'payment_id' => $link->payment_id];

    Refusal::assert(Refusal::UNIQUE, 'prepayment_kuitansi_links_c43_one_link', fn () => PrepaymentKuitansiLink::factory()->create($again));
    Refusal::assert(Refusal::CHECK, 'prepayment_kuitansi_links_c43_pre_payment_document', fn () => PrepaymentKuitansiLink::factory()->create([...$again,
        'document_id' => Document::factory()->create(['company_id' => $link->company_id, 'document_type_code' => DocumentTypeFactory::ensure('DOC-06'), 'project_id' => null, 'invoice_id' => $link->invoice_id])->id,
    ]));
    Refusal::assert(Refusal::CHECK, 'prepayment_kuitansi_links_unlink_reason', fn () => $row()->update(['state' => 'UNLINKED']));

    $row()->update(['state' => 'UNLINKED', 'unlink_reason' => 'Aplikasi dialihkan ke faktur lain']);

    Refusal::assert(Refusal::CHECK, 'prepayment_kuitansi_links_c43_unlinked_once', fn () => $row()->update(['state' => 'LINKED', 'unlink_reason' => null]));

    expect(PrepaymentKuitansiLink::factory()->create($again)->exists)->toBeTrue();
});

it('pays out only with the reference its kind takes, a refund of one client, a remittance with its tax proof and rule (C-32; C-58; BR-FIN-11; L-43)', function () {
    $company = Company::factory()->create()->id;
    $payout = fn (array $attributes): Disbursement => Disbursement::factory()->create(['company_id' => $company, ...$attributes]);
    $remittance = ['kind' => 'TAX_REMITTANCE', 'purchase_id' => null, 'treatment_rule_id' => TaxTreatmentRuleFactory::new()->createOne()->id];

    Refusal::assert(Refusal::CHECK, 'disbursements_reference_by_kind', fn () => $payout(['kind' => 'REFUND', 'purchase_id' => null]));
    Refusal::assert(Refusal::CHECK, 'disbursements_reference_by_kind', fn () => $payout(['purchase_id' => null]));
    Refusal::assert(Refusal::CHECK, 'disbursements_reference_by_kind', fn () => $payout(['client_organization_id' => ClientOrganization::factory()->create()->id]));
    Refusal::assert(Refusal::CHECK, 'disbursements_c58_tax_evidence_type', fn () => $payout([...$remittance,
        'tax_evidence_id' => DeductionSettlementFactory::evidence($company, 'CLIENT_ORDER')->id, 'tax_evidence_type' => 'CLIENT_ORDER',
    ]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'disbursements_c58_tax_evidence_type_foreign', fn () => $payout([...$remittance,
        'tax_evidence_id' => DeductionSettlementFactory::evidence($company, 'CLIENT_ORDER')->id, 'tax_evidence_type' => 'TAX_PAYMENT_PROOF',
    ]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'disbursements_c32_purchase_foreign', fn () => $payout(['purchase_id' => Purchase::factory()->create()->id]));

    expect($payout([...$remittance, 'tax_evidence_id' => DeductionSettlementFactory::evidence($company, 'TAX_PAYMENT_PROOF')->id, 'tax_evidence_type' => 'TAX_PAYMENT_PROOF'])->exists)->toBeTrue()
        ->and(DisbursementFactory::refund($company, ClientOrganization::factory()->create()->id)->kind)->toBe('REFUND');
});

it('receives other Cash-In as income or not by its category, a transfer in citing exactly a transfer out (L-08; BR-FIN-07, BR-FIN-10)', function () {
    $transferOut = Disbursement::factory()->create(['kind' => 'INTERCOMPANY_TRANSFER_OUT', 'purchase_id' => null]);
    $transferIn = ['category' => 'INTERCOMPANY_TRANSFER_IN', 'income_class' => 'NON_INCOME', 'transfer_from_disbursement_id' => $transferOut->id, 'transfer_from_disbursement_kind' => 'INTERCOMPANY_TRANSFER_OUT'];

    Refusal::assert(Refusal::CHECK, 'other_cash_receipts_income_by_category', fn () => OtherCashReceipt::factory()->create(['income_class' => 'INCOME']));
    Refusal::assert(Refusal::CHECK, 'other_cash_receipts_income_by_category', fn () => OtherCashReceipt::factory()->create(['category' => 'CASHBACK_INCOME']));
    Refusal::assert(Refusal::CHECK, 'other_cash_receipts_transfer_by_category', fn () => OtherCashReceipt::factory()->create(['category' => 'INTERCOMPANY_TRANSFER_IN']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'other_cash_receipts_transfer_foreign', fn () => OtherCashReceipt::factory()->create([...$transferIn, 'transfer_from_disbursement_id' => Disbursement::factory()->create()->id]));

    expect(OtherCashReceipt::factory()->create($transferIn)->exists)->toBeTrue()
        ->and(OtherCashReceipt::factory()->create(['category' => 'CASHBACK_INCOME', 'income_class' => 'INCOME'])->exists)->toBeTrue();
});

it('contras one money fact of its company, a refund\'s contra resolving an application of the same refund (C-44; C-45; BR-CR-02)', function () {
    $payment = Payment::factory()->create();
    $refund = DisbursementFactory::refund($payment->company_id, $payment->client_organization_id);
    $own = ['payment_id' => $payment->id, 'company_id' => $payment->company_id, 'client_organization_id' => $payment->client_organization_id, 'invoice_id' => null, 'target_kind' => 'REFUND'];
    $backing = PaymentApplication::factory()->create([...$own, 'refund_disbursement_id' => $refund->id]);
    $elsewhere = PaymentApplication::factory()->create([...$own, 'refund_disbursement_id' => DisbursementFactory::refund($payment->company_id, $payment->client_organization_id)->id]);
    $contraOf = fn (array $attributes): FinancialContra => FinancialContra::factory()->create(['company_id' => $payment->company_id, 'payment_id' => null, ...$attributes]);

    Refusal::assert(Refusal::CHECK, 'financial_contras_one_original', fn () => $contraOf(['payment_id' => $payment->id, 'disbursement_id' => $refund->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'financial_contras_c32_payment_foreign', fn () => $contraOf(['payment_id' => Payment::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'financial_contras_c44_resolves_foreign', fn () => $contraOf(['disbursement_id' => $refund->id, 'resolves_application_id' => $elsewhere->id]));
    Refusal::assert(Refusal::CHECK, 'financial_contras_c44_resolves_refund', fn () => $contraOf(['payment_id' => $payment->id, 'resolves_application_id' => $backing->id]));

    expect($contraOf(['disbursement_id' => $refund->id, 'resolves_application_id' => $backing->id])->exists)->toBeTrue();
});

it('keeps a money fact\'s contras within it, a refund backed at creation, its shortfall typed and its backing released only by its contra (C-44; C-45; L-46; BR-FIN-07)', function () {
    $refund = DisbursementFactory::refund(Company::factory()->create()->id, ClientOrganization::factory()->create()->id);
    $transfer = Disbursement::factory()->create(['kind' => 'INTERCOMPANY_TRANSFER_OUT', 'purchase_id' => null]);
    $fact = fn (Disbursement $disbursement, string $kind, array $amounts = []): bool => DB::table('money_fact_balances')->insert([
        'company_id' => $disbursement->company_id, 'disbursement_id' => $disbursement->id, 'fact_kind' => $kind, 'amount' => $disbursement->amount, ...$amounts,
    ]);
    $row = fn () => DB::table('money_fact_balances')->where('disbursement_id', $refund->id);

    Refusal::assert(Refusal::CHECK, 'money_fact_balances_c44_refund_backed', fn () => $fact($refund, 'REFUND'));
    Refusal::assert(Refusal::CHECK, 'money_fact_balances_fact_by_kind', fn () => DB::table('money_fact_balances')->insert([
        'company_id' => $refund->company_id, 'disbursement_id' => $refund->id, 'fact_kind' => 'CASH_IN', 'amount' => 1,
    ]));
    Refusal::assert(Refusal::CHECK, 'money_fact_balances_c44_refund_only', fn () => $fact($transfer, 'TRANSFER_OUT', ['backed_amount' => 1]));
    Refusal::assert(Refusal::CHECK, 'money_fact_balances_transfer_cited', fn () => $fact($transfer, 'TRANSFER_OUT', ['transfer_cited_amount' => $transfer->amount + 1]));

    $fact($refund, 'REFUND', ['backed_amount' => $refund->amount]);

    Refusal::assert(Refusal::CHECK, 'money_fact_balances_c44_shortfall_case', fn () => $row()->update(['backed_amount' => 150000, 'shortfall_amount' => 50000]));
    Refusal::assert(Refusal::CHECK, 'money_fact_balances_c44_released', fn () => $row()->update(['released_amount' => 1]));

    $row()->update(['backed_amount' => 150000, 'shortfall_amount' => 50000, 'residual_case_id' => CorrectionCase::factory()->create(['company_id' => $refund->company_id])->id]);
    $fact($transfer, 'TRANSFER_OUT');

    Refusal::assert(Refusal::CHECK, 'money_fact_balances_c45_contra', fn () => DB::table('money_fact_balances')->where('disbursement_id', $transfer->id)->update(['contra_amount' => $transfer->amount + 1]));

    expect($row()->value('shortfall_amount'))->toBe('50000.00');
});

it('carries an opening credit once per import row of its company, signed off by its company\'s proof (C-32; C-47; L-50)', function () {
    $credit = OpeningCustomerCredit::factory()->create();
    $other = Company::factory()->create()->id;

    Refusal::assert(Refusal::UNIQUE, 'opening_customer_credits_import_row_id_unique', fn () => OpeningCustomerCredit::factory()->create(['company_id' => $credit->company_id, 'import_row_id' => $credit->import_row_id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'opening_customer_credits_c47_import_row_foreign', fn () => OpeningCustomerCredit::factory()->create([
        'company_id' => $credit->company_id, 'import_row_id' => ImportRowFactory::new()->createOne(['company_id' => $other])->id,
    ]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'opening_customer_credits_c32_evidence_foreign', fn () => OpeningCustomerCredit::factory()->create([
        'company_id' => $credit->company_id, 'signed_evidence_id' => DeductionSettlementFactory::evidence($other, 'OPENING_SIGNOFF')->id,
    ]));
    Refusal::assert(Refusal::CHECK, 'opening_customer_credits_amount_positive', fn () => OpeningCustomerCredit::factory()->create(['amount' => '0.00']));

    expect($credit->signedEvidence?->company_id)->toBe($credit->company_id);
});
