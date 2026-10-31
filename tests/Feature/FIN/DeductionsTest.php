<?php

use App\Modules\CST\Queries\CostFigures;
use App\Modules\DOC\Actions\ReplaceEvidence;
use App\Modules\FIN\Actions\ReallocateApplication;
use App\Modules\FIN\Actions\RecordContra;
use App\Modules\IAM\Models\User;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Database\Factories\ORG\TaxTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Evidence;
use Tests\Support\InvoiceWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\SettlementWorld;

// TL-04 (TASK-052; AX-21; AX-33; SF-SETTLE; SF-CONTRA; WF-FIN-04; CM-22; CM-38; L-28; L-41; L-45; L-46; BD-08; C-15; C-18; C-22; C-23; C-44; C-45; C-60; criteria 1–9, 11): ROUTE-255, ROUTE-260 and ROUTE-263 with each refusal; a settlement moves the invoice, the
// payment's source and the evidence together, creating no Cash-In and no application; a fee is a project expense once, however often it is re-recorded; a tax only through its treatment; a replaced evidence re-evaluated in its transaction.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = User::query()->findOrFail(Authorization::admin([$this->company->id], array_values(array_unique([...InvoiceWorld::CAPABILITIES, 'settlement.record', 'finance.contra', 'evidence.view', 'evidence.upload', 'cost.view', 'refund.record', 'payment.record', 'payment.apply'])))->id);
    $this->project = $this->world->project();
    $this->invoice = $this->world->issued($this->project, '1000000');
    $this->today = BusinessDate::today();
});

afterEach(fn () => @unlink($this->hotFile));

function deductionAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function deductionBalance(int $invoice): object
{
    return DB::table('invoice_balances')->where('invoice_id', $invoice)->first();
}

function deductionSource(int $payment): object
{
    return DB::table('application_source_balances')->where('payment_id', $payment)->first();
}

function deductionEvidence(int $evidence): object
{
    return DB::table('evidence_balances')->where('evidence_document_id', $evidence)->first();
}

it('settles a SIPLAH net remittance: Cash-In for the money received, one fee closing the receivable, one project expense, no formula (annex 6; BR-FIN-09; FS-13; challenge 9a; criterion 1)', function () {
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '970000', '970000');
    SettlementWorld::claimFee($payment->id, '30000');
    $evidence = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '30000');

    expect(SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, '30000', $evidence->id)->kind)->toBe('COMMITTED');

    $balance = deductionBalance($this->invoice);
    $source = deductionSource($payment->id);
    expect($balance->outstanding)->toBe('0.00')->and($balance->fee_settled_amount)->toBe('30000.00')->and($balance->applied_amount)->toBe('970000.00')
        ->and($source->settled_amount)->toBe('30000.00')->and($source->invoice_applied_amount)->toBe('970000.00')
        ->and(DB::table('payments')->count())->toBe(1)->and(DB::table('payment_applications')->count())->toBe(1)
        ->and(deductionEvidence($evidence->id)->cited_amount)->toBe('30000.00')->and(deductionEvidence($evidence->id)->expensed_amount)->toBe('30000.00');

    $expense = DB::table('expenses')->first();
    expect($expense->kind)->toBe('FEE_DEDUCTION')->and($expense->amount)->toBe('30000.00')->and((int) $expense->project_id)->toBe($this->project->id)->and(DB::table('expenses')->count())->toBe(1);
    expect(app(CostFigures::class)->project($this->actor->id, $this->project->id)['expenses'])->toBe('30000.00');
    expect(DB::table('audit_events')->where('action', 'FIN.DeductionSettled')->value('authority_class'))->toBe('ADM_PLUS');
});

it('settles a treasurer\'s taxes collected and withheld, leaving nothing outstanding and no revenue, payment or expense (annex 13; BR-FIN-16; criterion 2)', function () {
    $invoice = $this->world->issued($this->world->project(), '1000000');
    DB::table('invoice_balances')->where('invoice_id', $invoice)->update(['output_tax_value' => '110000.00', 'gross_value' => '1110000.00']);
    $payment = PaymentWorld::paid($this->actor->id, $invoice, '985000', '985000');
    $collected = SettlementWorld::taxType(null, 'PAYMENT_COLLECTED');
    $withheld = SettlementWorld::taxType(null);
    SettlementWorld::claimTax($payment->id, $collected, '110000');
    SettlementWorld::claimTax($payment->id, $withheld, '15000');

    expect(SettlementWorld::tax($this->actor->id, $invoice, $payment->id, '110000', SettlementWorld::evidence($this->company->id, 'TAX_COLLECTION_PROOF', '110000')->id, $collected, 'COLLECTED')->kind)->toBe('COMMITTED')
        ->and(SettlementWorld::tax($this->actor->id, $invoice, $payment->id, '15000', SettlementWorld::evidence($this->company->id, 'TAX_WITHHOLDING_PROOF', '15000')->id, $withheld, 'WITHHELD')->kind)->toBe('COMMITTED');

    $balance = deductionBalance($invoice);
    expect($balance->outstanding)->toBe('0.00')->and($balance->tax_settled_amount)->toBe('125000.00')->and($balance->collected_tax_settled_amount)->toBe('110000.00')
        ->and(DB::table('expenses')->count())->toBe(0)->and(DB::table('payments')->count())->toBe(1);
});

it('expenses a tax only through a rule that says so, once (BR-FIN-16; DATABASE §12)', function () {
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '900000', '900000');
    $tax = SettlementWorld::taxType(null, 'PAYMENT_WITHHELD', 'SETTLEMENT_TO_EXPENSE');
    SettlementWorld::claimTax($payment->id, $tax, '20000');
    $evidence = SettlementWorld::evidence($this->company->id, 'TAX_WITHHOLDING_PROOF', '20000');

    expect(SettlementWorld::tax($this->actor->id, $this->invoice, $payment->id, '20000', $evidence->id, $tax, 'WITHHELD')->kind)->toBe('COMMITTED')
        ->and(DB::table('expenses')->value('kind'))->toBe('TAX_TREATMENT')->and(deductionEvidence($evidence->id)->expensed_amount)->toBe('20000.00');
});

it('refuses each way out of SF-SETTLE (C-18; C-22; C-58; challenge 6d; criteria 3 and 4)', function () {
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '500000', '500000');
    SettlementWorld::claimFee($payment->id, '100000');
    $fee = fn (string $amount, int $evidence) => SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, $amount, $evidence);

    // Above the evidence, across payments and termins.
    $short = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '40000');
    $refused = $fee('50000', $short->id);
    expect($refused->kind)->toBe('REJECTED')->and($refused->code)->toBe('C-18')->and($refused->state['uncited'])->toBe('40000.00');

    // The wrong type of evidence.
    expect($fee('10000', SettlementWorld::evidence($this->company->id, 'TAX_WITHHOLDING_PROOF', '50000')->id)->code)->toBe('C-58');

    // Above what the remittance advice claims.
    $wide = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '200000');
    $above = $fee('100001', $wide->id);
    expect($above->code)->toBe('C-22')->and($above->state['reason'])->toBe('above_claimed');
});

it('refuses a second ACTIVE settlement of one key as a CONFLICT, above the outstanding, and with no treatment (BD-08; C-15; BR-FIN-16; criterion 3)', function () {
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '950000', '950000');
    SettlementWorld::claimFee($payment->id, '60000');
    $evidence = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '100000');

    expect(SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, '10000', $evidence->id)->kind)->toBe('COMMITTED');
    $second = SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, '10000', $evidence->id);
    expect($second->kind)->toBe('CONFLICT')->and($second->code)->toBe('BD-08');

    $above = SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, '50000', $evidence->id, 'BIAYA_BANK');
    expect($above->code)->toBe('C-15')->and($above->state['outstanding'])->toBe('40000.00');

    $tax = TaxTypeFactory::new()->createOne(['category' => 'WITHHOLDING', 'code' => 'PPH-'.Str::upper(Str::random(8))]);
    $noRule = SettlementWorld::tax($this->actor->id, $this->invoice, $payment->id, '1000', SettlementWorld::evidence($this->company->id, 'TAX_WITHHOLDING_PROOF', '1000')->id, $tax->id, 'WITHHELD');
    expect($noRule->code)->toBe('BR-FIN-16');
});

it('holds collected tax to the invoice\'s tax component (C-22; criterion 4)', function () {
    DB::table('invoice_balances')->where('invoice_id', $this->invoice)->update(['output_tax_value' => '10000.00', 'gross_value' => '1010000.00']);
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '900000', '900000');
    $tax = SettlementWorld::taxType(null, 'PAYMENT_COLLECTED');
    SettlementWorld::claimTax($payment->id, $tax, '50000');
    $refused = SettlementWorld::tax($this->actor->id, $this->invoice, $payment->id, '10001', SettlementWorld::evidence($this->company->id, 'TAX_COLLECTION_PROOF', '50000')->id, $tax, 'COLLECTED');

    expect($refused->code)->toBe('C-22')->and($refused->state['reason'])->toBe('collected_above_tax');
});

it('reverses a contra\'d settlement\'s legs, citation and expense, both visible, and expenses a fee once however often it is re-recorded (CM-22; C-23; C-60; criteria 5 and 7)', function () {
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '970000', '970000');
    SettlementWorld::claimFee($payment->id, '30000');
    $evidence = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '30000');
    $id = SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, '30000', $evidence->id)->resultId;

    expect(SettlementWorld::contra($this->actor->id, $this->company->id, RecordContra::SETTLEMENT, $id)->kind)->toBe('COMMITTED');

    $balance = deductionBalance($this->invoice);
    expect($balance->outstanding)->toBe('30000.00')->and($balance->fee_settled_amount)->toBe('0.00')->and(deductionSource($payment->id)->settled_amount)->toBe('0.00')
        ->and(deductionEvidence($evidence->id)->cited_amount)->toBe('0.00')->and(deductionEvidence($evidence->id)->expensed_amount)->toBe('0.00')
        ->and(DB::table('deduction_settlements')->where('id', $id)->value('state'))->toBe('CONTRAED')
        ->and(DB::table('financial_contras')->where('deduction_settlement_id', $id)->count())->toBe(1)
        ->and(app(CostFigures::class)->project($this->actor->id, $this->project->id)['expenses'])->toBe('0.00');

    // A second contra answers the state, nothing moved.
    expect(SettlementWorld::contra($this->actor->id, $this->company->id, RecordContra::SETTLEMENT, $id)->code)->toBe('ST-02');

    // Re-recorded with its evidence: one expense standing, at most the evidence.
    expect(SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, '30000', $evidence->id)->kind)->toBe('COMMITTED')
        ->and(deductionEvidence($evidence->id)->expensed_amount)->toBe('30000.00')
        ->and(app(CostFigures::class)->project($this->actor->id, $this->project->id)['expenses'])->toBe('30000.00')
        ->and(DB::table('expenses')->count())->toBe(2)->and(DB::table('deduction_settlements')->where('state', 'ACTIVE')->count())->toBe(1);
});

it('moves a fee\'s expense once with a reallocation, to the project the settlement moves to (L-28; BR-FIN-09; C-23)', function () {
    $project = $this->world->project();
    $target = $this->world->issued($project, '500000');
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '400000', '400000');
    SettlementWorld::claimFee($payment->id, '30000');
    $evidence = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '30000');
    $id = SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, '30000', $evidence->id)->resultId;
    $application = (int) DB::table('payment_applications')->where('payment_id', $payment->id)->value('id');

    $outcome = app(ReallocateApplication::class)->run((string) Str::uuid(), $this->actor->id, $payment, $application, $target, '400000', [$id => true], $this->today, false, 'Salah invoice', ['t' => 1]);
    expect($outcome->kind)->toBe('COMMITTED');

    expect(deductionEvidence($evidence->id)->expensed_amount)->toBe('30000.00')->and(deductionEvidence($evidence->id)->cited_amount)->toBe('30000.00')
        ->and(app(CostFigures::class)->project($this->actor->id, $this->project->id)['expenses'])->toBe('0.00')
        ->and(app(CostFigures::class)->project($this->actor->id, $project->id)['expenses'])->toBe('30000.00');
});

it('contras a disbursement and a manual expense within their net, and refuses a settlement\'s expense alone (CM-38; C-23; C-45; criterion 8)', function () {
    $manual = SettlementWorld::manualExpense($this->company->id, $this->project->id, '50000');
    $above = SettlementWorld::contra($this->actor->id, $this->company->id, RecordContra::EXPENSE, $manual, '50001');
    expect($above->code)->toBe('C-45')->and($above->state['net'])->toBe('50000.00');
    expect(SettlementWorld::contra($this->actor->id, $this->company->id, RecordContra::EXPENSE, $manual, '20000')->kind)->toBe('COMMITTED')
        ->and(app(CostFigures::class)->project($this->actor->id, $this->project->id)['expenses'])->toBe('30000.00');

    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '970000', '970000');
    SettlementWorld::claimFee($payment->id, '30000');
    SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, '30000', SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '30000')->id);
    $fee = (int) DB::table('expenses')->where('kind', 'FEE_DEDUCTION')->value('id');
    $refused = SettlementWorld::contra($this->actor->id, $this->company->id, RecordContra::EXPENSE, $fee, '1000');
    expect($refused->code)->toBe('C-23')->and($refused->state['reason'])->toBe('not_manual');
});

it('releases a refund\'s backing to credit only through its contra, up to its amount (CM-38; L-46; C-44; criterion 8)', function () {
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '500000', '300000');
    $refund = PaymentWorld::refund($payment->id, '200000');

    expect(SettlementWorld::contra($this->actor->id, $this->company->id, RecordContra::DISBURSEMENT, $refund['refund'], '50000')->kind)->toBe('COMMITTED');

    $source = deductionSource($payment->id);
    $fact = DB::table('money_fact_balances')->where('disbursement_id', $refund['refund'])->first();
    expect($source->refund_backed_amount)->toBe('150000.00')->and($source->refund_released_amount)->toBe('50000.00')->and($source->capacity_amount)->toBe('50000.00')
        ->and($fact->contra_amount)->toBe('50000.00')->and($fact->backed_amount)->toBe('150000.00')->and($fact->released_amount)->toBe('50000.00')
        ->and(DB::table('payment_applications')->where('id', $refund['application'])->value('end_reason'))->toBe('REFUND_CONTRA')
        ->and(DB::table('payment_applications')->where('refund_disbursement_id', $refund['refund'])->where('state', 'ACTIVE')->value('amount'))->toBe('150000.00');
});

it('resolves a refund\'s shortfall first, the rest released, the case falling by what it resolved (L-46; C-44; DATABASE §11.3; criterion 8)', function () {
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '200000');
    $refund = PaymentWorld::refund($payment->id, '200000');
    expect(PaymentWorld::contraInCommand($this->actor->id, $payment->id, '50000', [$refund['application'] => ['action' => 'SHORTFALL', 'amount' => '50000']]))->toBe('COMMITTED');
    $case = (int) DB::table('money_fact_balances')->where('disbursement_id', $refund['refund'])->value('residual_case_id');
    expect(DB::table('correction_cases')->where('id', $case)->value('unbacked_refund_amount'))->toBe('50000.00');

    expect(SettlementWorld::contra($this->actor->id, $this->company->id, RecordContra::DISBURSEMENT, $refund['refund'], '80000')->kind)->toBe('COMMITTED');

    $fact = DB::table('money_fact_balances')->where('disbursement_id', $refund['refund'])->first();
    $source = deductionSource($payment->id);
    expect($fact->shortfall_amount)->toBe('0.00')->and($fact->released_amount)->toBe('30000.00')->and($fact->backed_amount)->toBe('120000.00')->and($fact->contra_amount)->toBe('80000.00')
        ->and($source->refund_shortfall_amount)->toBe('0.00')->and($source->refund_released_amount)->toBe('30000.00')
        ->and(DB::table('correction_cases')->where('id', $case)->value('unbacked_refund_amount'))->toBe('0.00')
        ->and(DB::table('financial_contras')->where('disbursement_id', $refund['refund'])->whereNotNull('resolves_application_id')->value('amount'))->toBe('50000.00');
});

it('resolves a residual once: re-recorded on the invoice its evidence covers, the case falling, a second refused (DATABASE §11.4; C-60; ROUTE-263; criterion 11)', function () {
    $target = $this->world->issued($this->world->project(), '500000');
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '950000', '900000');
    SettlementWorld::claimFee($payment->id, '30000');
    $evidence = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '30000');
    $id = SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, '30000', $evidence->id)->resultId;
    $case = SettlementWorld::residual($this->actor->id, $payment, '1', $id);

    expect(DB::table('deduction_settlements')->where('id', $id)->value('state'))->toBe('RESIDUAL')->and(deductionEvidence($evidence->id)->cited_amount)->toBe('30000.00');
    // CM-22's contra is not the exit of a residual.
    expect(SettlementWorld::contra($this->actor->id, $this->company->id, RecordContra::SETTLEMENT, $id)->code)->toBe('C-60');

    $resolved = SettlementWorld::resolve($this->actor->id, $this->company->id, $id, $target, $payment->id, null);
    expect($resolved->kind)->toBe('COMMITTED');
    $new = DB::table('deduction_settlements')->where('state', 'ACTIVE')->first();
    expect((int) $new->invoice_id)->toBe($target)->and((int) $new->evidence_document_id)->toBe($evidence->id)
        ->and(DB::table('correction_cases')->where('id', $case)->value('residual_settlement_amount'))->toBe('0.00')
        ->and(deductionEvidence($evidence->id)->cited_amount)->toBe('30000.00')->and(deductionEvidence($evidence->id)->expensed_amount)->toBe('30000.00')
        ->and(deductionBalance($target)->fee_settled_amount)->toBe('30000.00');

    expect(SettlementWorld::resolve($this->actor->id, $this->company->id, $id, $target, $payment->id, null)->code)->toBe('ST-02');
});

it('resolves a residual on the issuer\'s evidence: the citation and the expense released, the evidence linked (DATABASE §11.4; C-60; criterion 11)', function () {
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '950000', '900000');
    SettlementWorld::claimFee($payment->id, '30000');
    $evidence = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '30000');
    $id = SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, '30000', $evidence->id)->resultId;
    $case = SettlementWorld::residual($this->actor->id, $payment, '1', $id);

    expect(SettlementWorld::resolve($this->actor->id, $this->company->id, $id, null, null, null)->code)->toBe('C-60');
    $issuer = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '30000');
    expect(SettlementWorld::resolve($this->actor->id, $this->company->id, $id, null, null, $issuer->id)->kind)->toBe('COMMITTED');

    expect(deductionEvidence($evidence->id)->cited_amount)->toBe('0.00')->and(deductionEvidence($evidence->id)->expensed_amount)->toBe('0.00')
        ->and(DB::table('correction_cases')->where('id', $case)->value('residual_settlement_amount'))->toBe('0.00')
        ->and(DB::table('evidence_links')->where('evidence_document_id', $issuer->id)->where('deduction_settlement_id', $id)->exists())->toBeTrue()
        ->and(app(CostFigures::class)->project($this->actor->id, $this->project->id)['expenses'])->toBe('0.00');
});

it('lists claims waiting for evidence and no field for a short payment; PG-047\'s page by the routes (QS-13; J-FIN-04; ROUTE-047; criterion 6)', function () {
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '900000', '900000');
    SettlementWorld::claimFee($payment->id, '30000');
    $evidence = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '20000');
    SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, '20000', $evidence->id);

    $this->actingAs($this->actor)->get('/keuangan/potongan')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('FIN/Deductions', false)
        ->where('waiting.0.payment', $payment->public_id)->where('waiting.0.waiting', '10000.00')->where('waiting.0.claimed', '30000.00')
        ->where('rows.0.amount', '20000.00')->where('rows.0.can.contra', true)->where('rows.0.can.resolve', false)->where('rows.0.evidence', $evidence->public_id));
    expect(deductionBalance($this->invoice)->outstanding)->toBe('80000.00');
});

it('records through ROUTE-255 from the page\'s own handles, the reason on the review trail, the answer in place (INT-255; AZ-10)', function () {
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '900000', '900000');
    $claim = SettlementWorld::claimFee($payment->id, '30000');
    $evidence = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '30000');
    $body = ['command_id' => (string) Str::uuid(), 'pembayaran' => $payment->public_id, 'klaim' => RowHandle::of('payment_claimed_deductions', $claim), 'invoice' => InvoiceWorld::publicId($this->invoice), 'jumlah' => '30000', 'bukti' => $evidence->public_id, 'tanggal' => $this->today, 'alasan' => 'Nota biaya terverifikasi'];

    $this->actingAs($this->actor)->post('/keuangan/potongan', $body)->assertRedirect('/keuangan/potongan');
    expect(deductionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('deduction_settlements')->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'FIN.DeductionSettled')->value('reason'))->toBe('Nota biaya terverifikasi');

    $this->actingAs($this->actor)->post('/keuangan/potongan', [...$body, 'command_id' => (string) Str::uuid(), 'jumlah' => '0'])->assertSessionHasErrors('jumlah');
    $this->actingAs($this->actor)->post('/keuangan/potongan', [...$body, 'command_id' => (string) Str::uuid(), 'alasan' => ''])->assertSessionHasErrors('alasan');
});

it('re-evaluates a settlement inside the replacement of its evidence, on the review trail (L-41; challenge 20a; criterion 9)', function () {
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '900000', '900000');
    SettlementWorld::claimFee($payment->id, '30000');
    $evidence = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '30000');
    $id = SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, '30000', $evidence->id)->resultId;
    foreach ([1, 2] as $number) {
        DB::table('evidence_document_versions')->insert(['evidence_document_id' => $evidence->id, 'version_no' => $number, 'file_object_id' => Evidence::upload($this->actor, $this->company->id, Evidence::pdf((string) $number))->id, 'uploaded_by' => $this->actor->id, 'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now()]);
    }

    $outcome = app(ReplaceEvidence::class)->run((string) Str::uuid(), $this->actor->id, $evidence->refresh(), $this->company->public_id, 2, 'Salinan lebih jelas');
    expect($outcome->kind)->toBe('COMMITTED');

    $trail = DB::table('audit_events')->where('action', 'FIN.SettlementEvidenceReplaced')->first();
    expect($trail)->not->toBeNull()->and((int) $trail->entity_id)->toBe($id)->and($trail->authority_class)->toBe('ADM_PLUS');
});

it('registers a deduction\'s evidence from PG-047\'s form with its proven amount, the answer back in place (IP-16; C-18; ROUTE-229)', function () {
    $file = Evidence::upload($this->actor, $this->company->id);
    $back = '/keuangan/potongan?perusahaan='.$this->company->public_id;

    $this->actingAs($this->actor)->post('/bukti', Evidence::registration($file, $this->company->public_id, ['type' => 'FEE_ADVICE', 'amount' => '30000', 'kembali' => $back]))->assertRedirect($back);

    $evidence = DB::table('evidence_documents')->where('evidence_type', 'FEE_ADVICE')->first();
    expect($evidence)->not->toBeNull()->and($evidence->proven_amount)->toBe('30000.00')->and(deductionEvidence((int) $evidence->id)->proven_amount)->toBe('30000.00');
});

it('answers a repeated full settlement as already recorded, not by the room it left (BD-08; criterion 3)', function () {
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '970000', '970000');
    SettlementWorld::claimFee($payment->id, '30000');
    $evidence = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '30000');

    expect(SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, '30000', $evidence->id)->kind)->toBe('COMMITTED');
    $again = SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, '30000', $evidence->id);
    expect($again->kind)->toBe('CONFLICT')->and($again->code)->toBe('BD-08');
});

it('nets a company-level expense\'s contra in CALC-11 (C-23; CALC-11)', function () {
    $manual = SettlementWorld::manualExpense($this->company->id, null, '50000');
    expect(SettlementWorld::contra($this->actor->id, $this->company->id, RecordContra::EXPENSE, $manual, '20000')->kind)->toBe('COMMITTED');

    $figures = collect(app(CostFigures::class)->companies($this->actor->id))->firstWhere('company_id', $this->company->id);
    expect($figures['expenses'])->toBe('30000.00');
});

it('refuses a paid expense\'s contra until its disbursement is contra\'d, and releases a bill it cites (AX-33; C-18; L-43)', function () {
    $bill = SettlementWorld::evidence($this->company->id, 'SUPPLIER_BILL', '50000');
    DB::table('evidence_balances')->where('evidence_document_id', $bill->id)->update(['cited_amount' => '50000.00']);
    $manual = SettlementWorld::manualExpense($this->company->id, $this->project->id, '50000', $bill->id);
    $account = PaymentWorld::account($this->company->id);
    $paying = DB::table('disbursements')->insertGetId(['public_id' => (string) Str::uuid(), 'company_id' => $this->company->id, 'business_date' => $this->today, 'amount' => '50000', 'kind' => 'EXPENSE_PAYMENT', 'company_bank_account_id' => $account, 'expense_id' => $manual, 'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $this->actor->id]);
    DB::table('money_fact_balances')->insert(['company_id' => $this->company->id, 'disbursement_id' => $paying, 'fact_kind' => 'OTHER_DISBURSEMENT', 'amount' => '50000', 'recorded_at' => now()]);

    $refused = SettlementWorld::contra($this->actor->id, $this->company->id, RecordContra::EXPENSE, $manual, '50000');
    expect($refused->code)->toBe('C-45')->and($refused->state['reason'])->toBe('expense_paid');

    expect(SettlementWorld::contra($this->actor->id, $this->company->id, RecordContra::DISBURSEMENT, $paying, '50000')->kind)->toBe('COMMITTED')
        ->and(SettlementWorld::contra($this->actor->id, $this->company->id, RecordContra::EXPENSE, $manual, '50000')->kind)->toBe('COMMITTED')
        ->and(deductionEvidence($bill->id)->cited_amount)->toBe('0.00');
});

it('refuses the settlement\'s own evidence as the issuer\'s proof (DATABASE §11.4; C-60)', function () {
    $payment = PaymentWorld::paid($this->actor->id, $this->invoice, '950000', '900000');
    SettlementWorld::claimFee($payment->id, '30000');
    $evidence = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '30000');
    $id = SettlementWorld::fee($this->actor->id, $this->invoice, $payment->id, '30000', $evidence->id)->resultId;
    SettlementWorld::residual($this->actor->id, $payment, '1', $id);

    expect(SettlementWorld::resolve($this->actor->id, $this->company->id, $id, null, null, $evidence->id)->code)->toBe('C-60');
});
