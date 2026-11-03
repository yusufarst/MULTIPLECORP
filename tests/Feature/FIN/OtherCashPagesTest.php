<?php

use App\Modules\CST\Queries\CostFigures;
use App\Modules\PUR\Services\PurchaseCorrections;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\CostWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\OtherCashWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\SettlementWorld;

// TL-04, TL-06, TL-07 (TASK-054; ROUTE-049; ROUTE-257–ROUTE-259; ROUTE-260; NAV-049; INT-257–INT-259; SM-01; SM-04; SM-25; criteria 1–8): PG-049 by tab and capability; "Catat Beban",
// "Catat Pengeluaran Kas" and "Catat Kas Masuk Lain" answering in place with each refusal their contracts name; another company's reference refused like an unknown one; a wrong fact
// corrected only by its contra, both visible.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->project = $this->world->project();
    $this->actor = Authorization::admin([$this->company->id], [...OtherCashWorld::CAPABILITIES, 'finance.contra', 'evidence.upload', 'projects.view']);
    $this->accountId = PaymentWorld::account($this->company->id);
    $this->account = PaymentWorld::accountHandle($this->accountId);
    $this->proof = PaymentWorld::proof($this->company->id, 'TRANSFER_PROOF')->public_id;
    $this->today = BusinessDate::today();
});

afterEach(fn () => @unlink($this->hotFile));

function otherCashAnswer(object $test, object $actor, string $url, array $body): array
{
    $answer = $test->actingAs($actor)->followingRedirects()->post($url, ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'perusahaan' => $test->company->public_id, ...$body])->viewData('page')['flash']['answer'];
    Auth::forgetGuards();

    return $answer;
}

function otherCashForm(object $test, object $actor): array
{
    $version = (string) $test->actingAs($actor)->get('/keuangan/kas-lain')->viewData('page')['version'];
    $form = $test->actingAs($actor)->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'FIN/OtherCash', 'X-Inertia-Partial-Data' => 'form'])
        ->get('/keuangan/kas-lain?untuk='.$test->company->public_id)->json('props.form');
    Auth::forgetGuards();

    return is_array($form) ? $form : [];
}

it('shows PG-049 by tab — disbursements, expenses with cost.view only, other Cash-In with its income class — each form by capability (NAV-049; RG-06; SM-25; AZ-01; criterion 7)', function () {
    $actor = OtherCashWorld::actor($this->company->id);
    OtherCashWorld::committed(OtherCashWorld::disburse($actor, $this->company->id, 'INTERCOMPANY_TRANSFER_OUT', '150000', $this->accountId));
    OtherCashWorld::committed(OtherCashWorld::expense($actor, $this->company->id, $this->project->id, '50000'));
    OtherCashWorld::committed(OtherCashWorld::cashIn($actor, $this->company->id, 'CASHBACK_INCOME', '20000', $this->accountId));

    $this->actingAs($this->actor)->get('/keuangan/kas-lain')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('FIN/OtherCash', false)
        ->where('tabs', ['pengeluaran', 'beban', 'kas-masuk'])->where('tab', 'pengeluaran')->where('rows.0.kind', 'INTERCOMPANY_TRANSFER_OUT')->where('rows.0.amount', '150000.00')->where('rows.0.can.contra', true)
        ->where('branches.0.can', ['disbursement' => true, 'expense' => true, 'cashin' => true]));
    $this->actingAs($this->actor)->get('/keuangan/kas-lain?tab=beban')->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.category', 'OPERASIONAL')->where('rows.0.project', $this->project->public_id)->where('rows.0.net', '50000.00'));
    $this->actingAs($this->actor)->get('/keuangan/kas-lain?tab=kas-masuk')->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.category', 'CASHBACK_INCOME')->where('rows.0.income', true));

    // Finance without the cost side: no expense tab, no expense figure, no expense form (row 78; SM-25).
    $finance = Authorization::admin([$this->company->id], ['finance.view', 'disbursement.record']);
    $this->actingAs($finance)->get('/keuangan/kas-lain?tab=beban')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('tabs', ['pengeluaran', 'kas-masuk'])->where('tab', 'pengeluaran')
        ->where('branches.0.can', ['disbursement' => true, 'expense' => false, 'cashin' => false]));
    expect(json_encode($this->actingAs($finance)->get('/keuangan/kas-lain?tab=beban')->viewData('page')))->not->toContain('OPERASIONAL');

    // RG-06: the expense recorder with the cost side opens the page to its expenses alone.
    $recorder = Authorization::admin([$this->company->id], ['cost.view', 'expense.record']);
    $this->actingAs($recorder)->get('/keuangan/kas-lain')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('tabs', ['beban'])->where('tab', 'beban')->where('rows.0.net', '50000.00'));
    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view']))->get('/keuangan/kas-lain')->assertForbidden();
});

it('offers each form\'s choices for one company only to its capability, evidence within its family (PJ-08; AZ-01)', function () {
    SettlementWorld::evidence($this->company->id, 'SUPPLIER_BILL', '100000');
    $form = otherCashForm($this, $this->actor);
    expect($form['can'])->toBe(['expense' => true, 'disbursement' => true, 'cashin' => true, 'upload' => true])
        ->and(array_column($form['accounts'], 'id'))->toContain($this->account)->and($form['bills'])->toHaveCount(1)->and(array_column($form['projects'], 'id'))->toContain($this->project->public_id);

    $cashier = Authorization::admin([$this->company->id], ['finance.view', 'cashin.record']);
    $narrow = otherCashForm($this, $cashier);
    expect($narrow['can']['expense'])->toBeFalse()->and($narrow['bills'])->toBe([])->and($narrow['projects'])->toBe([])->and($narrow['expenses'])->toBe([])->and($narrow['tax_proofs'])->toBe([]);
});

it('records an expense through ROUTE-257: once in its project\'s cost, a company one the company\'s, the fee category refused, a bill capped with its figure, evidence required (CALC-09; CALC-11; C-23; C-20; L-45; criteria 2, 3)', function () {
    $bill = SettlementWorld::evidence($this->company->id, 'SUPPLIER_BILL', '100000');
    $project = otherCashAnswer($this, $this->actor, '/keuangan/kas-lain/beban', ['kategori' => 'Sewa alat', 'jumlah' => '70000', 'proyek' => $this->project->public_id, 'tagihan' => $bill->public_id]);
    expect($project['kind'])->toBe('COMMITTED')->and(app(CostFigures::class)->project($this->actor->id, $this->project->id)['expenses'])->toBe('70000.00');

    $company = otherCashAnswer($this, $this->actor, '/keuangan/kas-lain/beban', ['kategori' => 'Listrik kantor', 'jumlah' => '30000', 'bukti' => [$this->proof]]);
    expect($company['kind'])->toBe('COMMITTED')
        ->and(collect(app(CostFigures::class)->companies($this->actor->id))->firstWhere('company_id', $this->company->id)['expenses'])->toBe('100000.00')
        ->and(DB::table('evidence_links')->whereNotNull('expense_id')->value('role'))->toBe('PRIMARY_PROOF');

    $capped = otherCashAnswer($this, $this->actor, '/keuangan/kas-lain/beban', ['kategori' => 'Sewa alat', 'jumlah' => '30001', 'tagihan' => $bill->public_id]);
    expect($capped['code'])->toBe('C-20')->and($capped['state']['uncited'])->toBe('30000.00')->and($capped['text'])->toContain('30.000');

    foreach (['FEE_DEDUCTION', 'tax_treatment', 'Purchase_Charge'] as $reserved) {
        expect(otherCashAnswer($this, $this->actor, '/keuangan/kas-lain/beban', ['kategori' => $reserved, 'jumlah' => '1000', 'bukti' => [$this->proof]])['code'])->toBe('C-23');
    }

    expect(otherCashAnswer($this, $this->actor, '/keuangan/kas-lain/beban', ['kategori' => 'Tanpa bukti', 'jumlah' => '1000'])['code'])->toBe('L-45')
        ->and(DB::table('disbursements')->count())->toBe(0)
        ->and(DB::table('audit_events')->where('action', 'CST.ExpenseRecorded')->count())->toBe(2);
});

it('records each disbursement kind through ROUTE-258: a purchase paid only through it, an expense paid, a transfer out, a tax remittance citing its proof under the rule and never cost (BR-FIN-11; BR-FIN-17; L-43; J-FIN-07; criteria 1, 5)', function () {
    $purchase = CostWorld::make($this->company)->purchase();
    expect(PurchaseCorrections::paid($purchase->id))->toBeFalse();
    expect(otherCashAnswer($this, $this->actor, '/keuangan/kas-lain/pengeluaran', ['jenis' => 'PURCHASE_PAYMENT', 'jumlah' => '400000', 'rekening' => $this->account, 'pembelian' => $purchase->public_id, 'bukti' => [$this->proof]])['kind'])->toBe('COMMITTED')
        ->and(PurchaseCorrections::paid($purchase->id))->toBeTrue()
        ->and(DB::table('disbursements')->where('purchase_id', $purchase->id)->count())->toBe(1);

    $expense = OtherCashWorld::committed(OtherCashWorld::expense($this->actor->id, $this->company->id, null, '80000'));
    expect(otherCashAnswer($this, $this->actor, '/keuangan/kas-lain/pengeluaran', ['jenis' => 'EXPENSE_PAYMENT', 'jumlah' => '80000', 'rekening' => $this->account, 'beban' => RowHandle::of('expenses', $expense), 'bukti' => [$this->proof]])['kind'])->toBe('COMMITTED')
        ->and(collect(app(CostFigures::class)->companies($this->actor->id))->firstWhere('company_id', $this->company->id)['expenses'])->toBe('80000.00');

    expect(otherCashAnswer($this, $this->actor, '/keuangan/kas-lain/pengeluaran', ['jenis' => 'INTERCOMPANY_TRANSFER_OUT', 'jumlah' => '250000', 'rekening' => $this->account, 'bukti' => [$this->proof]])['kind'])->toBe('COMMITTED')
        ->and(DB::table('money_fact_balances')->where('fact_kind', 'TRANSFER_OUT')->value('amount'))->toBe('250000.00');

    // BR-FIN-17: the remittance cites its TAX_PAYMENT_PROOF within what it proves, under the rule in force; remitted output tax never becomes cost.
    $type = SettlementWorld::taxType($this->company->id, 'TAX_REMITTANCE', 'REMITTANCE_NOT_COST');
    $code = (string) DB::table('tax_types')->where('id', $type)->value('code');
    $taxProof = SettlementWorld::evidence($this->company->id, 'TAX_PAYMENT_PROOF', '110000');
    $remit = fn (string $amount, string $taxCode, ?string $proof = null): array => otherCashAnswer($this, $this->actor, '/keuangan/kas-lain/pengeluaran', ['jenis' => 'TAX_REMITTANCE', 'jumlah' => $amount, 'rekening' => $this->account, 'bukti_pajak' => $proof ?? $taxProof->public_id, 'jenis_pajak' => $taxCode]);
    $above = $remit('110001', $code);
    expect($above['code'])->toBe('C-18')->and($above['state']['uncited'])->toBe('110000.00')
        ->and($remit('110000', $code)['kind'])->toBe('COMMITTED')
        ->and(DB::table('evidence_balances')->where('evidence_document_id', $taxProof->id)->value('cited_amount'))->toBe('110000.00')
        ->and(DB::table('disbursements')->where('kind', 'TAX_REMITTANCE')->value('treatment_rule_id'))->not->toBeNull()
        ->and(collect(app(CostFigures::class)->companies($this->actor->id))->firstWhere('company_id', $this->company->id)['expenses'])->toBe('80000.00')
        ->and($remit('1000', $code, $this->proof)['code'])->toBe('C-58');

    $unruled = (string) DB::table('tax_types')->where('id', SettlementWorld::taxType($this->company->id, 'PAYMENT_WITHHELD'))->value('code');
    $expensed = SettlementWorld::taxType($this->company->id, 'TAX_REMITTANCE', 'REMITTANCE_TO_EXPENSE');
    $more = SettlementWorld::evidence($this->company->id, 'TAX_PAYMENT_PROOF', '5000')->public_id;
    expect($remit('1000', $unruled, $more)['code'])->toBe('BR-FIN-17')
        ->and($remit('1000', (string) DB::table('tax_types')->where('id', $expensed)->value('code'), $more)['state']['reason'])->toBe('treatment')
        ->and(otherCashAnswer($this, $this->actor, '/keuangan/kas-lain/pengeluaran', ['jenis' => 'INTERCOMPANY_TRANSFER_OUT', 'jumlah' => '1000', 'rekening' => $this->account])['code'])->toBe('L-29')
        ->and(DB::table('audit_events')->where('action', 'FIN.DisbursementRecorded')->count())->toBe(4);
});

it('records other Cash-In through ROUTE-259: cashback as income, a supplier refund as restitution, a statement line cited once within its room, a transfer in only with both companies in scope within its cap (BR-FIN-10; L-08; C-19; XL-5; criteria 4, 6)', function () {
    $cashback = otherCashAnswer($this, $this->actor, '/keuangan/kas-lain/kas-masuk', ['kategori' => 'CASHBACK_INCOME', 'jumlah' => '15000', 'rekening' => $this->account, 'bukti' => [$this->proof]]);
    $purchase = CostWorld::make($this->company)->purchase();
    $refund = otherCashAnswer($this, $this->actor, '/keuangan/kas-lain/kas-masuk', ['kategori' => 'SUPPLIER_REFUND', 'jumlah' => '40000', 'rekening' => $this->account, 'pembelian' => $purchase->public_id, 'bukti' => [$this->proof]]);
    expect($cashback['kind'])->toBe('COMMITTED')->and($refund['kind'])->toBe('COMMITTED')
        ->and(DB::table('other_cash_receipts')->orderBy('id')->pluck('income_class')->all())->toBe(['INCOME', 'NON_INCOME'])
        ->and(otherCashAnswer($this, $this->actor, '/keuangan/kas-lain/kas-masuk', ['kategori' => 'SUPPLIER_REFUND', 'jumlah' => '1000', 'rekening' => $this->account, 'bukti' => [$this->proof]])['code'])->toBe('C-14');

    $line = PaymentWorld::line($this->company->id, $this->accountId, '60000');
    $lined = fn (string $amount): array => otherCashAnswer($this, $this->actor, '/keuangan/kas-lain/kas-masuk', ['kategori' => 'OTHER_NON_INCOME', 'jumlah' => $amount, 'rekening' => $this->account, 'mutasi' => RowHandle::of('bank_statement_lines', $line)]);
    $over = $lined('60001');
    expect($over['code'])->toBe('C-19')->and($over['state']['uncited'])->toBe('60000.00')
        ->and($lined('60000')['kind'])->toBe('COMMITTED')
        ->and(DB::table('evidence_balances')->where('bank_statement_line_id', $line)->value('cited_amount'))->toBe('60000.00')
        ->and($lined('1')['code'])->toBe('C-19');

    // XL-5: the other company out of the Admin's scope refuses the citation; the Owner records it, within the transfer-out's net amount.
    $sender = InvoiceWorld::make()->company->id;
    $transfer = OtherCashWorld::transferOut($sender, '500000');
    $publicTransfer = (string) DB::table('disbursements')->where('id', $transfer)->value('public_id');
    $transferIn = fn (object $actor, string $amount): array => otherCashAnswer($this, $actor, '/keuangan/kas-lain/kas-masuk', ['kategori' => 'INTERCOMPANY_TRANSFER_IN', 'jumlah' => $amount, 'rekening' => $this->account, 'transfer' => $publicTransfer, 'bukti' => [$this->proof]]);
    expect($transferIn($this->actor, '300000')['code'])->toBe('XL-5');

    $owner = Authorization::owner();
    $beyond = $transferIn($owner, '500001');
    expect($beyond['code'])->toBe('BR-FIN-07')->and($beyond['state']['left'])->toBe('500000.00')
        ->and($transferIn($owner, '500000')['kind'])->toBe('COMMITTED')
        ->and(DB::table('money_fact_balances')->where('disbursement_id', $transfer)->value('transfer_cited_amount'))->toBe('500000.00')
        ->and(DB::table('other_cash_receipts')->where('transfer_from_disbursement_id', $transfer)->value('income_class'))->toBe('NON_INCOME');

    // Each side sees the other only as "perusahaan lain" unless both are in its scope (INFORMATION_ARCHITECTURE §7).
    $this->actingAs($this->actor)->get('/keuangan/kas-lain?tab=kas-masuk')->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.category', 'INTERCOMPANY_TRANSFER_IN')->where('rows.0.transfer_from', ''));
    $this->actingAs($owner)->get('/keuangan/kas-lain?tab=kas-masuk&perusahaan='.$this->company->public_id)->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.transfer_from', DB::table('companies')->where('id', $sender)->value('code')));
});

it('answers another company\'s purchase, project, bill, account or proof like an unknown one (CS-04; SM-01; MSG-09)', function () {
    $other = InvoiceWorld::make();
    $foreignPurchase = CostWorld::make($other->company)->purchase()->public_id;
    $foreignProject = $other->project()->public_id;
    $foreignBill = SettlementWorld::evidence($other->company->id, 'SUPPLIER_BILL', '100000')->public_id;
    $foreignAccount = PaymentWorld::accountHandle(PaymentWorld::account($other->company->id));
    $foreignProof = PaymentWorld::proof($other->company->id, 'TRANSFER_PROOF')->public_id;
    $send = fn (string $url, array $body) => $this->actingAs($this->actor)->post($url, ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'perusahaan' => $this->company->public_id, ...$body]);

    $send('/keuangan/kas-lain/pengeluaran', ['jenis' => 'PURCHASE_PAYMENT', 'jumlah' => '1000', 'rekening' => $this->account, 'pembelian' => $foreignPurchase, 'bukti' => [$this->proof]])->assertSessionHasErrors('pembelian');
    $send('/keuangan/kas-lain/beban', ['kategori' => 'Sewa', 'jumlah' => '1000', 'proyek' => $foreignProject, 'bukti' => [$this->proof]])->assertSessionHasErrors('proyek');
    $send('/keuangan/kas-lain/beban', ['kategori' => 'Sewa', 'jumlah' => '1000', 'tagihan' => $foreignBill])->assertSessionHasErrors('tagihan');
    $send('/keuangan/kas-lain/kas-masuk', ['kategori' => 'OTHER_INCOME', 'jumlah' => '1000', 'rekening' => $foreignAccount, 'bukti' => [$this->proof]])->assertSessionHasErrors('rekening');
    $send('/keuangan/kas-lain/kas-masuk', ['kategori' => 'OTHER_INCOME', 'jumlah' => '1000', 'rekening' => $this->account, 'bukti' => [$foreignProof]])->assertSessionHasErrors('bukti');
    $this->actingAs($this->actor)->post('/keuangan/kas-lain/beban', ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'perusahaan' => $other->company->public_id, 'kategori' => 'Sewa', 'jumlah' => '1000'])->assertNotFound();

    expect(DB::table('expenses')->count() + DB::table('disbursements')->count() + DB::table('other_cash_receipts')->count())->toBe(0);
});

it('corrects a wrong disbursement or expense only by its contra from PG-049, both visible, a paid expense after its payment (CM-38; AX-33; BR-CR-02; criterion 8)', function () {
    $actor = OtherCashWorld::actor($this->company->id);
    $expense = OtherCashWorld::committed(OtherCashWorld::expense($actor, $this->company->id, $this->project->id, '50000'));
    $payment = OtherCashWorld::committed(OtherCashWorld::disburse($actor, $this->company->id, 'EXPENSE_PAYMENT', '50000', $this->accountId, ['expense' => $expense]));
    $contra = fn (string $kind, string $record): array => otherCashAnswer($this, $this->actor, '/keuangan/kontra', ['jenis' => $kind, 'catatan' => $record, 'jumlah' => '50000', 'alasan' => 'Salah catat', 'kembali' => '/keuangan/kas-lain?tab=beban']);

    $this->actingAs($this->actor)->get('/keuangan/kas-lain?tab=beban')->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.paid', true)->where('rows.0.can.contra', false));
    expect($contra('beban', RowHandle::of('expenses', $expense))['state']['reason'])->toBe('expense_paid');

    $paid = $contra('pengeluaran', (string) DB::table('disbursements')->where('id', $payment)->value('public_id'));
    expect($paid['kind'])->toBe('COMMITTED')->and($paid['text'])->toBe(__('deductions.answer.contraed_pengeluaran'))
        ->and($contra('beban', RowHandle::of('expenses', $expense))['kind'])->toBe('COMMITTED')
        ->and(app(CostFigures::class)->project($this->actor->id, $this->project->id)['expenses'])->toBe('0.00');

    $this->actingAs($this->actor)->get('/keuangan/kas-lain')->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.net', '0.00')->where('rows.0.amount', '50000.00')->where('rows.0.can.contra', false));
    $this->actingAs($this->actor)->get('/keuangan/kas-lain?tab=beban')->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.net', '0.00')->where('rows.0.can.contra', false));
});
