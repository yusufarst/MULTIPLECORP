<?php

use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\OtherCashWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-054; AZ-01; AZ-11; RG-06; SM-01; SM-04; SM-25; XL-5; CS-07; criterion 7): `expense.record` with `cost.view`, `disbursement.record` and `cashin.record` each granted on its own, the
// command forbidden without it and its form absent; expenses only on the cost side; another company's record unknown; a transfer citation without both companies in scope refused.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->account = PaymentWorld::accountHandle(PaymentWorld::account($this->company->id));
    $this->proof = PaymentWorld::proof($this->company->id, 'TRANSFER_PROOF')->public_id;
    $this->body = fn (array $fields): array => ['command_id' => (string) Str::uuid(), 'perusahaan' => $this->company->public_id, 'jumlah' => '1000', 'tanggal' => BusinessDate::today(), 'bukti' => [$this->proof], ...$fields];
});

afterEach(fn () => @unlink($this->hotFile));

it('forbids each command without its own capability, whatever else is held (AZ-01; SM-04; criterion 7)', function () {
    $reader = Authorization::admin([$this->company->id], ['finance.view', 'cost.view', 'evidence.view', 'finance.contra']);

    $this->actingAs($reader)->get('/keuangan/kas-lain')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('branches.0.can', ['disbursement' => false, 'expense' => false, 'cashin' => false]));
    $this->actingAs($reader)->post('/keuangan/kas-lain/beban', ($this->body)(['kategori' => 'Sewa']))->assertForbidden();
    $this->actingAs($reader)->post('/keuangan/kas-lain/pengeluaran', ($this->body)(['jenis' => 'INTERCOMPANY_TRANSFER_OUT', 'rekening' => $this->account]))->assertForbidden();
    $this->actingAs($reader)->post('/keuangan/kas-lain/kas-masuk', ($this->body)(['kategori' => 'OTHER_INCOME', 'rekening' => $this->account]))->assertForbidden();

    // Each capability opens its own command only.
    $payer = Authorization::admin([$this->company->id], ['finance.view', 'evidence.view', 'disbursement.record']);
    $this->actingAs($payer)->post('/keuangan/kas-lain/kas-masuk', ($this->body)(['kategori' => 'OTHER_INCOME', 'rekening' => $this->account]))->assertForbidden();
    $this->actingAs($payer)->post('/keuangan/kas-lain/pengeluaran', ($this->body)(['jenis' => 'INTERCOMPANY_TRANSFER_OUT', 'rekening' => $this->account]))->assertRedirect();

    expect(DB::table('disbursements')->count())->toBe(1)->and(DB::table('other_cash_receipts')->count())->toBe(0)->and(DB::table('expenses')->count())->toBe(0);
});

it('records an expense only with cost.view beside expense.record, its prerequisite, forbidden otherwise (RG-06; SM-25; criterion 7)', function () {
    $blind = Authorization::admin([$this->company->id], ['finance.view', 'evidence.view', 'expense.record']);

    $this->actingAs($blind)->post('/keuangan/kas-lain/beban', ($this->body)(['kategori' => 'Sewa']))->assertForbidden();
    $this->actingAs($blind)->get('/keuangan/kas-lain')->assertInertia(fn (AssertableInertia $page) => $page->where('tabs', ['pengeluaran', 'kas-masuk'])->where('branches.0.can.expense', false));

    $seeing = Authorization::admin([$this->company->id], ['cost.view', 'evidence.view', 'finance.view', 'expense.record']);
    $this->actingAs($seeing)->post('/keuangan/kas-lain/beban', ($this->body)(['kategori' => 'Sewa']))->assertRedirect();
    expect(DB::table('expenses')->count())->toBe(1);
});

it('answers a company outside the actor\'s scope like an unknown one, and lists only its own (SM-01; AZ-11; CS-07)', function () {
    $other = InvoiceWorld::make()->company;
    $actor = Authorization::admin([$this->company->id], OtherCashWorld::CAPABILITIES);
    OtherCashWorld::transferOut($other->id, '100000');

    $this->actingAs($actor)->post('/keuangan/kas-lain/pengeluaran', [...($this->body)(['jenis' => 'INTERCOMPANY_TRANSFER_OUT', 'rekening' => $this->account]), 'perusahaan' => $other->public_id])->assertNotFound();
    $this->actingAs($actor)->get('/keuangan/kas-lain')->assertInertia(fn (AssertableInertia $page) => $page->has('branches', 1)->where('rows', []));
    $this->actingAs($actor)->get('/keuangan/kas-lain?perusahaan='.$other->public_id)->assertInertia(fn (AssertableInertia $page) => $page->where('branch', null)->where('rows', []));
});

it('refuses a transfer-in citing a company outside the actor\'s scope, which the form never offers (XL-5; SM-04; criterion 6)', function () {
    $other = InvoiceWorld::make()->company;
    $transfer = OtherCashWorld::transferOut($other->id, '100000');
    $actor = Authorization::admin([$this->company->id], OtherCashWorld::CAPABILITIES);

    $answer = $this->actingAs($actor)->followingRedirects()->post('/keuangan/kas-lain/kas-masuk', ($this->body)(['kategori' => 'INTERCOMPANY_TRANSFER_IN', 'rekening' => $this->account, 'transfer' => DB::table('disbursements')->where('id', $transfer)->value('public_id')]))->viewData('page')['flash']['answer'];
    expect($answer['code'])->toBe('XL-5')->and(DB::table('other_cash_receipts')->count())->toBe(0);

    // Both companies in scope with `finance.view` on the paying side: offered and cited.
    Authorization::grantCompany($actor, $other->id);
    $form = $this->actingAs($actor)->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $this->actingAs($actor)->get('/keuangan/kas-lain')->viewData('page')['version'], 'X-Inertia-Partial-Component' => 'FIN/OtherCash', 'X-Inertia-Partial-Data' => 'form'])
        ->get('/keuangan/kas-lain?untuk='.$this->company->public_id)->json('props.form.transfers');
    expect(array_column($form, 'company'))->toBe([$other->code]);
});
