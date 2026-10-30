<?php

use App\Modules\IAM\Models\User;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-050; AZ-01; AZ-11; SM-01; SM-04; SM-25; OD-07; OD-11; RG-02; criteria 9, 11): `billing.record`, `billing.correct` (ADM_PLUS), `receivables.dispute`; `receivables.write_off` and `writeoff.supersede` OWNER_ONLY, denied to an Admin, never grantable and through any replay;
// PG-044 and every figure absent without `finance.view`; another company's invoice unknown.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company->id;
    $this->project = $this->world->project();
    $this->invoice = $this->world->issued($this->project, '1000000');
    $this->public = InvoiceWorld::publicId($this->invoice);
    $this->today = BusinessDate::today();
    $this->bill = ['tanggal' => $this->today, 'jatuh_tempo' => '2099-12-31'];
});

afterEach(fn () => @unlink($this->hotFile));

function receivableCommand(object $test, User $actor, string $path, array $body = []): TestResponse
{
    return $test->actingAs($actor)->post("/keuangan/invoice/{$test->public}/{$path}", ['command_id' => (string) Str::uuid(), ...$body]);
}

it('has no PG-044 without finance.view (SM-25; AZ-01; criterion 11)', function () {
    $this->actingAs(Authorization::admin([$this->company], ['projects.view', 'billing.record']))->get('/keuangan/piutang')->assertForbidden();
});

it('needs each command\'s own capability, finance.view with it (AZ-01; SM-04)', function () {
    $reader = Authorization::admin([$this->company], ['projects.view', 'finance.view']);

    receivableCommand($this, $reader, 'tagihkan', $this->bill)->assertForbidden();
    receivableCommand($this, $reader, 'koreksi-tagihan', [...$this->bill, 'bukti' => (string) Str::uuid(), 'alasan' => 'Salah catat'])->assertForbidden();
    receivableCommand($this, $reader, 'sengketa', ['aksi' => 'TANDAI', 'alasan' => 'Klien membantah'])->assertForbidden();

    $blind = Authorization::admin([$this->company], ['projects.view', 'billing.record', 'billing.correct', 'receivables.dispute']);
    receivableCommand($this, $blind, 'tagihkan', $this->bill)->assertForbidden();
    expect(DB::table('invoice_billing_acts')->where('invoice_id', $this->invoice)->count())->toBe(0);

    $this->actingAs($reader)->get("/keuangan/invoice/{$this->public}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.bill', false)->where('can.correct_billing', false)->where('can.dispute', false)->where('can.write_off', false)->where('can.recover', false));
});

it('offers each control by capability, the correction ADM_PLUS (AZ-10; INT-243; INT-244; INT-246)', function () {
    $biller = Authorization::admin([$this->company], ['finance.view', 'billing.record']);
    $this->actingAs($biller)->get("/keuangan/invoice/{$this->public}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.bill', true)->where('can.correct_billing', false));

    receivableCommand($this, $biller, 'tagihkan', $this->bill);
    $this->actingAs($biller)->get("/keuangan/invoice/{$this->public}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.bill', false)->where('can.correct_billing', false)->where('can.dispute', false)->has('evidence', 0));

    $corrector = Authorization::admin([$this->company], ['finance.view', 'billing.correct', 'receivables.dispute']);
    $this->actingAs($corrector)->get("/keuangan/invoice/{$this->public}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.correct_billing', true)->where('can.dispute', true)->where('can.write_off', false));
});

it('never gives an Admin a write-off or its supersession, however asked, a replay included: the controls absent, the routes refused (OD-11; RG-02; AZ-11; criterion 9)', function () {
    InvoiceWorld::bill($this->invoice, $this->today, '2099-12-31');
    $admin = User::query()->findOrFail($this->world->actorId);
    $owner = Authorization::owner();
    $commandId = (string) Str::uuid();
    $writeOff = ['command_id' => $commandId, 'jumlah' => '100000', 'alasan' => 'Klien tidak dapat ditagih', 'tanggal' => $this->today];

    // A row granting it, however it got there, gives an Admin nothing: the capability is the Owner role's alone (RG-02).
    $granted = Authorization::admin([$this->company], ['finance.view', 'receivables.write_off', 'writeoff.supersede']);
    expect($this->actingAs($granted)->post("/keuangan/invoice/{$this->public}/hapuskan", [...$writeOff, 'command_id' => (string) Str::uuid()])->status())->toBeIn([403, 404]);

    expect($this->actingAs($admin)->post("/keuangan/invoice/{$this->public}/hapuskan", $writeOff)->status())->toBeIn([403, 404]);
    expect(DB::table('receivable_writeoffs')->count())->toBe(0);

    // The Owner's own command replayed by an Admin answers nothing but the refusal.
    $this->actingAs($owner)->post("/keuangan/invoice/{$this->public}/hapuskan", $writeOff);
    expect(DB::table('receivable_writeoffs')->count())->toBe(1);
    expect($this->actingAs($admin)->post("/keuangan/invoice/{$this->public}/hapuskan", $writeOff)->status())->toBeIn([403, 404]);
    expect(DB::table('receivable_writeoffs')->count())->toBe(1);

    $writeoff = (int) DB::table('receivable_writeoffs')->value('id');
    expect($this->actingAs($admin)->post("/keuangan/invoice/{$this->public}/cabut-penghapusan", ['command_id' => (string) Str::uuid(), 'penghapusan' => RowHandle::of('receivable_writeoffs', $writeoff), 'pembayaran' => InvoiceWorld::recovery($this->invoice, '1000')->public_id, 'jumlah' => '1000', 'alasan' => 'Uang kembali', 'tanggal' => $this->today])->status())->toBeIn([403, 404]);
    $this->actingAs($admin)->get("/keuangan/invoice/{$this->public}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.write_off', false)->where('can.recover', false)->where('credit', null)->has('recoveries', 0));
    $this->actingAs($owner)->get("/keuangan/invoice/{$this->public}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.write_off', true)->where('can.recover', true));
});

it('answers another company\'s invoice as unknown on every command, writing nothing, its row never listed (SM-01; CS-03; PJ-23; AZ-11)', function () {
    $stranger = InvoiceWorld::make();
    $actor = Authorization::admin([$stranger->company->id], ['finance.view', 'billing.record', 'billing.correct', 'receivables.dispute']);

    receivableCommand($this, $actor, 'tagihkan', $this->bill)->assertNotFound();
    receivableCommand($this, $actor, 'koreksi-tagihan', [...$this->bill, 'bukti' => (string) Str::uuid(), 'alasan' => 'Salah catat'])->assertNotFound();
    receivableCommand($this, $actor, 'sengketa', ['aksi' => 'TANDAI', 'alasan' => 'Klien membantah'])->assertNotFound();
    $this->actingAs($actor)->get('/keuangan/piutang?tab=belum')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0)->has('subtotals', 0)->has('clients', 0));
    $this->actingAs($actor)->get('/keuangan/piutang?perusahaan='.$this->world->company->public_id.'&tab=belum')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0));

    expect(DB::table('invoice_billing_acts')->count())->toBe(0)->and(DB::table('receivable_dispute_holds')->count())->toBe(0);
});
