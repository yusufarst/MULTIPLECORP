<?php

use App\Modules\FIN\Actions\RecordContra;
use App\Modules\IAM\Models\User;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\SettlementWorld;

// TL-08 (TASK-052; IG-05; INT-255; INT-260; INT-263; CI-05; INT-016): each command once for one `command_id` sent twice, nothing written twice; other content a CONFLICT; a refusal replayed with its current figure; validation at the fields, the input kept.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = User::query()->findOrFail(Authorization::admin([$this->company->id], array_values(array_unique([...InvoiceWorld::CAPABILITIES, 'settlement.record', 'finance.contra', 'evidence.view', 'payment.record', 'payment.apply'])))->id);
    $this->invoice = $this->world->issued($this->world->project(), '1000000');
    $this->today = BusinessDate::today();
    $this->payment = PaymentWorld::paid($this->actor->id, $this->invoice, '950000', '900000');
    $this->claim = SettlementWorld::claimFee($this->payment->id, '30000');
    $this->evidence = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '30000');
    $this->body = fn (array $fields = []): array => ['command_id' => (string) Str::uuid(), 'pembayaran' => $this->payment->public_id, 'klaim' => RowHandle::of('payment_claimed_deductions', $this->claim), 'invoice' => InvoiceWorld::publicId($this->invoice), 'jumlah' => '30000', 'bukti' => $this->evidence->public_id, 'tanggal' => $this->today, 'alasan' => 'Nota biaya', ...$fields];
});

afterEach(fn () => @unlink($this->hotFile));

function deductionFlash(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

it('records once for one command_id sent twice; other content a CONFLICT (INT-255; CI-05)', function () {
    $body = ($this->body)();

    $this->actingAs($this->actor)->post('/keuangan/potongan', $body)->assertRedirect('/keuangan/potongan');
    expect(deductionFlash()['kind'])->toBe('COMMITTED');
    $this->actingAs($this->actor)->post('/keuangan/potongan', $body)->assertRedirect('/keuangan/potongan');
    expect(deductionFlash()['kind'])->toBe('COMMITTED')->and(DB::table('deduction_settlements')->count())->toBe(1)->and(DB::table('expenses')->count())->toBe(1);

    $this->actingAs($this->actor)->post('/keuangan/potongan', [...$body, 'jumlah' => '20000'])->assertRedirect('/keuangan/potongan');
    expect(deductionFlash()['kind'])->toBe('CONFLICT')->and(DB::table('deduction_settlements')->count())->toBe(1);
});

it('answers a refusal in place with its figure, a replay with the figure as it stands now (INT-255; IP-03; CI-05; C-18)', function () {
    $short = SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '10000');
    $body = ($this->body)(['bukti' => $short->public_id]);

    $this->actingAs($this->actor)->post('/keuangan/potongan', $body)->assertRedirect('/keuangan/potongan');
    expect(deductionFlash()['kind'])->toBe('REJECTED')->and(deductionFlash()['code'])->toBe('C-18')->and(deductionFlash()['text'])->toContain('10.000')->and(deductionFlash()['state']['uncited'])->toBe('10000.00');

    $this->actingAs($this->actor)->post('/keuangan/potongan', $body)->assertRedirect('/keuangan/potongan');
    expect(deductionFlash()['code'])->toBe('C-18')->and(deductionFlash()['state']['uncited'])->toBe('10000.00');
});

it('answers a duplicate as already recorded, a future date and an empty reason at their fields (INT-255; BD-08; IP-14; IG-03)', function () {
    $this->actingAs($this->actor)->post('/keuangan/potongan', ($this->body)(['jumlah' => '10000']))->assertRedirect();
    $this->actingAs($this->actor)->post('/keuangan/potongan', ($this->body)(['jumlah' => '10000']))->assertRedirect();
    expect(deductionFlash()['kind'])->toBe('CONFLICT')->and(deductionFlash()['code'])->toBe('BD-08')->and(deductionFlash()['cause'])->toBe('recorded');

    $this->actingAs($this->actor)->post('/keuangan/potongan', ($this->body)(['tanggal' => '2999-01-01']))->assertSessionHasErrors('tanggal');
    $this->actingAs($this->actor)->post('/keuangan/potongan', ($this->body)(['alasan' => '']))->assertSessionHasErrors('alasan');
    $this->actingAs($this->actor)->post('/keuangan/potongan', ($this->body)(['klaim' => str_repeat('a', 32)]))->assertSessionHasErrors('klaim');
});

it('contras once for one command_id sent twice, a stale second press a CONFLICT (INT-260; CI-05; C-60)', function () {
    $id = SettlementWorld::fee($this->actor->id, $this->invoice, $this->payment->id, '30000', $this->evidence->id)->resultId;
    $body = ['command_id' => (string) Str::uuid(), 'jenis' => RecordContra::SETTLEMENT, 'catatan' => RowHandle::of('deduction_settlements', $id), 'tanggal' => $this->today, 'alasan' => 'Salah bukti', 'kembali' => '/keuangan/potongan?tab=sisa'];

    $this->actingAs($this->actor)->post('/keuangan/kontra', $body)->assertRedirect('/keuangan/potongan?tab=sisa');
    expect(deductionFlash()['kind'])->toBe('COMMITTED');
    $this->actingAs($this->actor)->post('/keuangan/kontra', $body)->assertRedirect();
    expect(DB::table('financial_contras')->where('deduction_settlement_id', $id)->count())->toBe(1);

    $this->actingAs($this->actor)->post('/keuangan/kontra', [...$body, 'command_id' => (string) Str::uuid()])->assertRedirect();
    expect(deductionFlash()['kind'])->toBe('CONFLICT')->and(deductionFlash()['cause'])->toBe('stale');
    $this->actingAs($this->actor)->post('/keuangan/kontra', [...$body, 'command_id' => (string) Str::uuid(), 'alasan' => ''])->assertSessionHasErrors('alasan');
    $this->actingAs($this->actor)->post('/keuangan/kontra', [...$body, 'command_id' => (string) Str::uuid(), 'kembali' => 'https://contoh.test'])->assertSessionHasErrors('kembali');
});

it('resolves once for one command_id sent twice, its fields required by the choice (INT-263; CI-05; C-60)', function () {
    $id = SettlementWorld::fee($this->actor->id, $this->invoice, $this->payment->id, '30000', $this->evidence->id)->resultId;
    SettlementWorld::residual($this->actor->id, $this->payment, '1', $id);
    $handle = RowHandle::of('deduction_settlements', $id);
    $target = $this->world->issued($this->world->project(), '500000');
    $body = ['command_id' => (string) Str::uuid(), 'pilihan' => 'catat_ulang', 'invoice' => InvoiceWorld::publicId($target), 'pembayaran' => $this->payment->public_id, 'tanggal' => $this->today, 'alasan' => 'Bukti penerbit'];

    $this->actingAs($this->actor)->post("/keuangan/potongan/{$handle}/selesaikan-sisa", [...$body, 'command_id' => (string) Str::uuid(), 'invoice' => null])->assertSessionHasErrors('invoice');
    $this->actingAs($this->actor)->post("/keuangan/potongan/{$handle}/selesaikan-sisa", [...$body, 'command_id' => (string) Str::uuid(), 'pilihan' => 'kontra'])->assertSessionHasErrors('bukti');

    $this->actingAs($this->actor)->post("/keuangan/potongan/{$handle}/selesaikan-sisa", $body)->assertRedirect('/keuangan/potongan');
    expect(deductionFlash()['kind'])->toBe('COMMITTED');
    $this->actingAs($this->actor)->post("/keuangan/potongan/{$handle}/selesaikan-sisa", $body)->assertRedirect();
    expect(DB::table('deduction_settlements')->count())->toBe(2);

    $this->actingAs($this->actor)->post("/keuangan/potongan/{$handle}/selesaikan-sisa", [...$body, 'command_id' => (string) Str::uuid()])->assertRedirect();
    expect(deductionFlash()['kind'])->toBe('CONFLICT');
});

it('replays each refusal with its reason and figure as they stand now (CI-05; C-15; C-22; C-45)', function () {
    $body = ($this->body)(['jumlah' => '40000', 'bukti' => SettlementWorld::evidence($this->company->id, 'FEE_ADVICE', '50000')->public_id]);

    $this->actingAs($this->actor)->post('/keuangan/potongan', $body)->assertRedirect();
    expect(deductionFlash()['code'])->toBe('C-22')->and(deductionFlash()['state']['reason'])->toBe('above_claimed')->and(deductionFlash()['state']['claimed'])->toBe('30000.00');
    $this->actingAs($this->actor)->post('/keuangan/potongan', $body)->assertRedirect();
    expect(deductionFlash()['code'])->toBe('C-22')->and(deductionFlash()['state']['reason'])->toBe('above_claimed')->and(deductionFlash()['state']['claimed'])->toBe('30000.00')->and(deductionFlash()['text'])->toContain('30.000');
});
