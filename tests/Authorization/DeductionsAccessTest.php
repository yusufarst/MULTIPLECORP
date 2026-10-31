<?php

use App\Modules\FIN\Actions\RecordContra;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Database\Factories\ORG\CompanyFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\SettlementWorld;

// TL-06 (TASK-052; AZ-01; AZ-11; SM-01; SM-04; SM-25; PJ-08; CS-07; criterion 10): `settlement.record` and `finance.contra` as separately granted ADM_PLUS capabilities; another company's payment, invoice or evidence answers alike; what a settlement cites shows only with `evidence.view`;
// the fee's expense stays on the cost side.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->invoice = $this->world->issued($this->world->project(), '1000000');
    $this->today = BusinessDate::today();
    $this->owner = Authorization::admin([$this->company->id], array_values(array_unique([...InvoiceWorld::CAPABILITIES, 'settlement.record', 'finance.contra', 'evidence.view', 'payment.record', 'payment.apply'])))->id;
    $this->payment = PaymentWorld::paid($this->owner, $this->invoice, '900000', '900000');
    $this->claim = SettlementWorld::claimFee($this->payment->id, '30000');
    $this->evidence = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '30000');
    $this->body = fn (array $fields = []): array => ['command_id' => (string) Str::uuid(), 'pembayaran' => $this->payment->public_id, 'klaim' => RowHandle::of('payment_claimed_deductions', $this->claim), 'invoice' => InvoiceWorld::publicId($this->invoice), 'jumlah' => '30000', 'bukti' => $this->evidence->public_id, 'tanggal' => $this->today, 'alasan' => 'Nota biaya', ...$fields];
});

afterEach(fn () => @unlink($this->hotFile));

it('has PG-047 and "Catat Potongan" only with settlement.record, the page and the command forbidden without it (AZ-01; SM-04; criterion 10)', function () {
    $reader = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'evidence.view', 'finance.contra']);

    $this->actingAs($reader)->get('/keuangan/potongan')->assertForbidden();
    $this->actingAs($reader)->post('/keuangan/potongan', ($this->body)())->assertForbidden();
    $this->actingAs($reader)->post('/keuangan/potongan/'.RowHandle::of('deduction_settlements', 1).'/selesaikan-sisa', ['command_id' => (string) Str::uuid()])->assertForbidden();
    expect(DB::table('deduction_settlements')->count())->toBe(0);
});

it('has the contra only with finance.contra, separately granted: absent from the row, the route forbidden (AZ-01; SM-04; criterion 10)', function () {
    $settler = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'evidence.view', 'settlement.record']);
    $id = SettlementWorld::fee($this->owner, $this->invoice, $this->payment->id, '30000', $this->evidence->id)->resultId;

    $this->actingAs($settler)->get('/keuangan/potongan')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.can.contra', false));
    $this->actingAs($settler)->post('/keuangan/kontra', ['command_id' => (string) Str::uuid(), 'jenis' => RecordContra::SETTLEMENT, 'catatan' => RowHandle::of('deduction_settlements', $id), 'tanggal' => $this->today, 'alasan' => 'Salah'])->assertForbidden();
    expect(DB::table('financial_contras')->count())->toBe(0);
});

it('answers another company\'s payment, invoice, evidence and fact as unknown, alike (SM-01; AZ-11; DP-13)', function () {
    $other = CompanyFactory::new()->createOne();
    $settler = Authorization::admin([$other->id], ['projects.view', 'finance.view', 'evidence.view', 'settlement.record', 'finance.contra']);
    $id = SettlementWorld::fee($this->owner, $this->invoice, $this->payment->id, '30000', $this->evidence->id)->resultId;

    $this->actingAs($settler)->post('/keuangan/potongan', ($this->body)())->assertNotFound();
    $this->actingAs($settler)->post('/keuangan/kontra', ['command_id' => (string) Str::uuid(), 'jenis' => RecordContra::SETTLEMENT, 'catatan' => RowHandle::of('deduction_settlements', $id), 'tanggal' => $this->today, 'alasan' => 'Salah'])->assertNotFound();
    $this->actingAs($settler)->post('/keuangan/potongan/'.RowHandle::of('deduction_settlements', $id).'/selesaikan-sisa', ['command_id' => (string) Str::uuid(), 'pilihan' => 'kontra', 'bukti' => $this->evidence->public_id, 'tanggal' => $this->today, 'alasan' => 'Salah'])->assertNotFound();
    $this->actingAs($settler)->get('/keuangan/potongan')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('rows', [])->where('waiting', []));

    // The same company's own settler cites only that company's evidence.
    $own = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'evidence.view', 'settlement.record']);
    $elsewhere = SettlementWorld::evidence($other->id, 'FEE_ADVICE', '30000');
    $this->actingAs($own)->post('/keuangan/potongan', ($this->body)(['bukti' => $elsewhere->public_id]))->assertSessionHasErrors('bukti');
});

it('has no PG-047 without evidence.view, settlement.record\'s dependency (PJ-08; PERMISSIONS_MATRIX §4; criterion 10)', function () {
    $blind = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'settlement.record']);

    $this->actingAs($blind)->get('/keuangan/potongan')->assertForbidden();
    $this->actingAs($blind)->post('/keuangan/potongan', ($this->body)(['jumlah' => '1000']))->assertForbidden();
});

it('keeps the fee\'s expense on the cost side, the page holding no cost figure (SM-25; row 78)', function () {
    SettlementWorld::fee($this->owner, $this->invoice, $this->payment->id, '30000', $this->evidence->id);
    $settler = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'evidence.view', 'settlement.record']);

    $this->actingAs($settler)->get('/keuangan/potongan')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->missing('rows.0.expense')->missing('rows.0.cost'));
});

it('states no expense figure to a holder of finance.contra without cost.view, refused or replayed (SM-25; row 78)', function () {
    $manual = SettlementWorld::manualExpense($this->company->id, null, '50000');
    $contra = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'finance.contra']);
    $body = ['command_id' => (string) Str::uuid(), 'jenis' => RecordContra::EXPENSE, 'catatan' => RowHandle::of('expenses', $manual), 'jumlah' => '50001', 'tanggal' => $this->today, 'alasan' => 'Salah catat'];

    foreach ([1, 2] as $send) {
        $this->actingAs($contra)->post('/keuangan/kontra', $body)->assertRedirect();
        $answer = session('inertia.flash_data')['answer'];
        expect($answer['code'])->toBe('C-45')->and($answer['state'])->not->toHaveKey('net')->and($answer['text'])->not->toContain('50.000');
    }

    $seer = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'finance.contra', 'cost.view']);
    $this->actingAs($seer)->post('/keuangan/kontra', [...$body, 'command_id' => (string) Str::uuid()])->assertRedirect();
    expect(session('inertia.flash_data')['answer']['state']['net'])->toBe('50000.00');
});
