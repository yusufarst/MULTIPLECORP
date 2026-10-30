<?php

use App\Modules\IAM\Models\User;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-050; IG-05; INT-243; INT-244; INT-246–INT-248; CI-05; AC-16; criterion 2): each command once for one `command_id` sent twice, nothing written twice, the recorded answer returned; the same identifier with other content a CONFLICT.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->actor = User::query()->findOrFail($this->world->actorId);
    $this->owner = Authorization::owner();
    $this->project = $this->world->project();
    $this->invoice = $this->world->issued($this->project, '1000000');
    $this->public = InvoiceWorld::publicId($this->invoice);
    $this->today = BusinessDate::today();
});

afterEach(fn () => @unlink($this->hotFile));

function receivableInteractionAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

it('bills once for one command_id sent twice, other content a CONFLICT (INT-243; CI-05; criterion 2)', function () {
    $body = ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'jatuh_tempo' => '2099-12-31'];

    $this->actingAs($this->actor)->post("/keuangan/invoice/{$this->public}/tagihkan", $body);
    $this->actingAs($this->actor)->post("/keuangan/invoice/{$this->public}/tagihkan", $body);
    expect(receivableInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('invoice_billing_acts')->count())->toBe(1)->and(DB::table('audit_events')->where('action', 'FIN.InvoiceBilled')->count())->toBe(1);

    $this->actingAs($this->actor)->post("/keuangan/invoice/{$this->public}/tagihkan", [...$body, 'jatuh_tempo' => '2099-11-30']);
    expect(receivableInteractionAnswer()['kind'])->toBe('CONFLICT')->and(DB::table('invoice_billing_acts')->count())->toBe(1);
});

it('corrects a billing once for one command_id sent twice (INT-244; CI-05)', function () {
    InvoiceWorld::bill($this->invoice, $this->today, '2099-12-31');
    $body = ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'jatuh_tempo' => '2099-11-30', 'bukti' => InvoiceWorld::linkedEvidence($this->world->company->id, $this->project->id)->public_id, 'alasan' => 'Tanggal salah catat'];

    $this->actingAs($this->actor)->post("/keuangan/invoice/{$this->public}/koreksi-tagihan", $body);
    $this->actingAs($this->actor)->post("/keuangan/invoice/{$this->public}/koreksi-tagihan", $body);
    expect(receivableInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('invoice_billing_acts')->count())->toBe(2)->and(DB::table('audit_events')->where('action', 'FIN.BillingCorrected')->count())->toBe(1);
});

it('sets and clears a hold once for one command_id each (INT-246; CI-05)', function () {
    InvoiceWorld::bill($this->invoice, $this->today, '2099-12-31');
    $set = ['command_id' => (string) Str::uuid(), 'aksi' => 'TANDAI', 'alasan' => 'Klien membantah'];

    $this->actingAs($this->actor)->post("/keuangan/invoice/{$this->public}/sengketa", $set);
    $this->actingAs($this->actor)->post("/keuangan/invoice/{$this->public}/sengketa", $set);
    expect(receivableInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('receivable_dispute_holds')->count())->toBe(1);

    $clear = ['command_id' => (string) Str::uuid(), 'aksi' => 'CABUT', 'alasan' => 'Klien setuju'];
    $this->actingAs($this->actor)->post("/keuangan/invoice/{$this->public}/sengketa", $clear);
    $this->actingAs($this->actor)->post("/keuangan/invoice/{$this->public}/sengketa", $clear);
    expect(receivableInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('audit_events')->where('action', 'FIN.DisputeHoldCleared')->count())->toBe(1);
});

it('writes off and supersedes once for one command_id each (INT-247; INT-248; CI-05)', function () {
    InvoiceWorld::bill($this->invoice, $this->today, '2099-12-31');
    $write = ['command_id' => (string) Str::uuid(), 'jumlah' => '300000', 'alasan' => 'Klien tidak dapat ditagih', 'tanggal' => $this->today];

    $this->actingAs($this->owner)->post("/keuangan/invoice/{$this->public}/hapuskan", $write);
    $this->actingAs($this->owner)->post("/keuangan/invoice/{$this->public}/hapuskan", $write);
    expect(receivableInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('receivable_writeoffs')->count())->toBe(1)->and(DB::table('invoice_balances')->where('invoice_id', $this->invoice)->value('written_off_amount'))->toBe('300000.00');

    $payment = InvoiceWorld::recovery($this->invoice, '100000');
    $supersede = ['command_id' => (string) Str::uuid(), 'penghapusan' => RowHandle::of('receivable_writeoffs', (int) DB::table('receivable_writeoffs')->value('id')), 'pembayaran' => $payment->public_id, 'jumlah' => '100000', 'alasan' => 'Klien membayar kembali', 'tanggal' => $this->today];
    $this->actingAs($this->owner)->post("/keuangan/invoice/{$this->public}/cabut-penghapusan", $supersede);
    $this->actingAs($this->owner)->post("/keuangan/invoice/{$this->public}/cabut-penghapusan", $supersede);
    expect(receivableInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('financial_contras')->count())->toBe(1)->and(DB::table('payment_applications')->where('payment_id', $payment->id)->count())->toBe(1);
});
