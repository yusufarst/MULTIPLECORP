<?php

use App\Modules\FIN\Models\Payment;
use App\Modules\IAM\Models\User;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-051; AZ-01; AZ-11; SM-01; SM-04; SM-12; SM-13; SM-25; M-13; DP-16; criterion 12): `payment.record`, `payment.apply`, `payment.reallocate`, `finance.contra`, `bank_statement.record`, `cash.correct`, `warnings.override` by class; the corrections absent without their capability, each asking its reason; payment pages showing
// nothing without `finance.view`; a replay after a revocation answered as AZ-11, another account's reuse a CONFLICT disclosing nothing; another company's payment, invoice and statement line unknown; an evidence duplicate's oracle closed.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->project = $this->world->project();
    $this->invoice = $this->world->issued($this->project, '1000000');
    $this->today = BusinessDate::today();
    $this->client = $this->project->client_organization_id;
    $this->clientKey = DB::table('client_organizations')->where('id', $this->client)->value('public_id');
    $this->account = PaymentWorld::account($this->company->id);
    $this->payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '400000', '400000');
    $this->application = (int) DB::table('payment_applications')->where('payment_id', $this->payment->id)->value('id');
});

afterEach(fn () => @unlink($this->hotFile));

function paymentAccessAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function recordBody(object $test, array $fields = []): array
{
    return ['command_id' => (string) Str::uuid(), 'perusahaan' => $test->company->public_id, 'klien' => $test->clientKey, 'tanggal' => $test->today, 'jumlah' => '100000', 'metode' => 'CASH', 'alasan_tanpa_bukti' => 'Bukti menyusul', ...$fields];
}

it('has no payment page without finance.view (SM-25; AZ-01; criterion 12)', function () {
    $blind = Authorization::admin([$this->company->id], ['projects.view']);

    $this->actingAs($blind)->get('/keuangan/pembayaran')->assertForbidden();
    $this->actingAs($blind)->get('/keuangan/pembayaran/baru')->assertForbidden();
    $this->actingAs($blind)->get('/keuangan/mutasi-bank')->assertForbidden();
});

it('needs each command\'s own capability (AZ-01; SM-04)', function () {
    $reader = Authorization::admin([$this->company->id], ['projects.view', 'finance.view']);
    $id = $this->payment->public_id;

    $this->actingAs($reader)->get('/keuangan/pembayaran/baru')->assertForbidden();
    $this->actingAs($reader)->get('/keuangan/mutasi-bank')->assertForbidden();
    $this->actingAs($reader)->post('/keuangan/pembayaran', recordBody($this))->assertForbidden();
    $this->actingAs($reader)->post("/keuangan/pembayaran/{$id}/terapkan", ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'penerapan' => [['invoice' => InvoiceWorld::publicId($this->invoice), 'jumlah' => '1']]])->assertForbidden();
    $this->actingAs($reader)->post("/keuangan/pembayaran/{$id}/realokasi", ['command_id' => (string) Str::uuid()])->assertForbidden();
    $this->actingAs($reader)->post("/keuangan/pembayaran/{$id}/kontra", ['command_id' => (string) Str::uuid()])->assertForbidden();
    $this->actingAs($reader)->post('/keuangan/mutasi-bank', ['command_id' => (string) Str::uuid()])->assertForbidden();
    $this->actingAs($reader)->post("/keuangan/mutasi-bank/{$id}/koreksi", ['command_id' => (string) Str::uuid()])->assertForbidden();

    $this->actingAs($reader)->get('/keuangan/pembayaran')->assertInertia(fn (AssertableInertia $page) => $page->where('can.record', false)->where('rows.0.can.apply', false)->where('rows.0.can.reallocate', false)->where('rows.0.can.contra', false)->where('rows.0.applications.0.can_move', false));
});

it('offers the corrections only with their ADM_PLUS capabilities, each asking its reason (AZ-10; IP-09; MSG-40; criterion 12)', function () {
    $mover = Authorization::admin([$this->company->id], ['finance.view', 'payment.reallocate', 'finance.contra', 'cash.correct', 'bank_statement.record', 'evidence.upload', 'evidence.view']);
    $this->actingAs($mover)->get('/keuangan/pembayaran')->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.can.reallocate', true)->where('rows.0.can.contra', true)->where('rows.0.applications.0.can_move', true));
    $this->actingAs($mover)->get('/keuangan/mutasi-bank')->assertInertia(fn (AssertableInertia $page) => $page->where('can.correct', true));

    $id = $this->payment->public_id;
    $this->actingAs($mover)->post("/keuangan/pembayaran/{$id}/realokasi", ['command_id' => (string) Str::uuid(), 'penerapan' => RowHandle::of('payment_applications', $this->application), 'invoice' => InvoiceWorld::publicId($this->invoice), 'jumlah' => '1', 'tanggal' => $this->today])->assertSessionHasErrors('alasan');
    $this->actingAs($mover)->post("/keuangan/pembayaran/{$id}/kontra", ['command_id' => (string) Str::uuid(), 'jumlah' => '1', 'tanggal' => $this->today])->assertSessionHasErrors('alasan');
    $this->actingAs($mover)->post("/keuangan/mutasi-bank/{$id}/koreksi", ['command_id' => (string) Str::uuid(), 'aksi' => 'KUTIPAN'])->assertSessionHasErrors('alasan');

    $recorder = Authorization::admin([$this->company->id], ['finance.view', 'bank_statement.record', 'evidence.upload', 'evidence.view']);
    $this->actingAs($recorder)->get('/keuangan/mutasi-bank')->assertInertia(fn (AssertableInertia $page) => $page->where('can.correct', false)->has('open_lines', 0));
});

it('answers the override of a likely duplicate only with warnings.override (IP-05; AZ-10; INT-015; M-12)', function () {
    $first = PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '500000', ['accountId' => $this->account]);
    $match = (string) DB::table('payments')->where('id', $first->resultId)->value('public_id');
    $body = fn (): array => recordBody($this, ['jumlah' => '500000', 'metode' => 'TRANSFER', 'rekening' => PaymentWorld::accountHandle($this->account), 'acknowledged' => [$match], 'reason' => 'Dua transfer berbeda']);

    $plain = Authorization::admin([$this->company->id], ['finance.view', 'payment.record']);
    $this->actingAs($plain)->post('/keuangan/pembayaran', $body());
    expect(paymentAccessAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'L-22'])->and(paymentAccessAnswer()['state']['override'])->toBeFalse()->and(DB::table('payments')->where('amount', '500000')->count())->toBe(1);

    $override = Authorization::admin([$this->company->id], ['finance.view', 'payment.record', 'warnings.override']);
    $this->actingAs($override)->post('/keuangan/pembayaran', $body());
    expect(paymentAccessAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('payments')->where('amount', '500000')->count())->toBe(2);
});

it('answers a replay after a revocation as AZ-11, another account\'s reuse a CONFLICT disclosing nothing (DP-16; CI-05; M-13)', function () {
    $commandId = (string) Str::uuid();
    $recorder = Authorization::admin([$this->company->id], ['finance.view', 'payment.record']);
    $body = recordBody($this, ['command_id' => $commandId]);
    $this->actingAs($recorder)->post('/keuangan/pembayaran', $body);
    expect(paymentAccessAnswer()['kind'])->toBe('COMMITTED');
    $count = DB::table('payments')->count();

    $other = Authorization::admin([$this->company->id], ['finance.view', 'payment.record']);
    $this->actingAs($other)->post('/keuangan/pembayaran', $body);
    expect(paymentAccessAnswer())->toMatchArray(['kind' => 'CONFLICT'])->and(paymentAccessAnswer()['state'])->toBe([])->and(DB::table('payments')->count())->toBe($count);

    Authorization::revoke($recorder, 'payment.record');
    expect($this->actingAs(User::query()->findOrFail($recorder->id))->post('/keuangan/pembayaran', $body)->status())->toBeIn([403, 404])->and(DB::table('payments')->count())->toBe($count);
});

it('answers another company\'s payment, invoice and statement line as unknown, writing nothing, its rows never listed (SM-01; CS-03; CS-07; AZ-11)', function () {
    $stranger = InvoiceWorld::make();
    $actor = Authorization::admin([$stranger->company->id], ['finance.view', 'payment.record', 'payment.apply', 'payment.reallocate', 'finance.contra', 'bank_statement.record', 'evidence.upload', 'evidence.view', 'cash.correct']);
    $id = $this->payment->public_id;
    $line = PaymentWorld::line($this->company->id, $this->account, '1000000');

    $this->actingAs($actor)->post("/keuangan/pembayaran/{$id}/terapkan", ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'penerapan' => [['invoice' => InvoiceWorld::publicId($this->invoice), 'jumlah' => '1']]])->assertNotFound();
    $this->actingAs($actor)->post("/keuangan/pembayaran/{$id}/realokasi", ['command_id' => (string) Str::uuid()])->assertNotFound();
    $this->actingAs($actor)->post("/keuangan/pembayaran/{$id}/kontra", ['command_id' => (string) Str::uuid()])->assertNotFound();
    $this->actingAs($actor)->post("/keuangan/mutasi-bank/{$id}/koreksi", ['command_id' => (string) Str::uuid(), 'aksi' => 'KUTIPAN', 'alasan' => 'Salah baris', 'mutasi' => RowHandle::of('bank_statement_lines', $line)])->assertNotFound();
    $this->actingAs($actor)->post('/keuangan/mutasi-bank/'.RowHandle::of('bank_statement_lines', $line).'/koreksi', ['command_id' => (string) Str::uuid(), 'aksi' => 'BATAL', 'alasan' => 'Salah ketik'])->assertNotFound();

    // Naming the other company in a form answers as a field unknown, never as its contents.
    $this->actingAs($actor)->post('/keuangan/pembayaran', recordBody($this))->assertSessionHasErrors('perusahaan');
    $this->actingAs($actor)->post('/keuangan/mutasi-bank', ['command_id' => (string) Str::uuid(), 'perusahaan' => $this->company->public_id, 'rekening' => PaymentWorld::accountHandle($this->account), 'bukti' => (string) Str::uuid(), 'tanggal' => $this->today, 'jumlah' => '1', 'referensi' => 'X'])->assertSessionHasErrors('perusahaan');

    $this->actingAs($actor)->get('/keuangan/pembayaran')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0)->has('clients', 0));
    $this->actingAs($actor)->get('/keuangan/pembayaran?perusahaan='.$this->company->public_id)->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0));
    $this->actingAs($actor)->get('/keuangan/pembayaran?setelah='.$id)->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0)->where('cursor_failed', true));
    $this->actingAs($actor)->get('/keuangan/mutasi-bank?perusahaan='.$this->company->public_id)->assertInertia(fn (AssertableInertia $page) => $page->where('branch', $stranger->company->public_id)->has('rows', 0));
    $this->actingAs($actor)->get('/keuangan/pembayaran/baru?invoice='.InvoiceWorld::publicId($this->invoice))->assertInertia(fn (AssertableInertia $page) => $page->where('origin', null)->where('client', null)->has('invoices', 0));

    expect(DB::table('payment_applications')->count())->toBe(1)->and(DB::table('financial_contras')->count())->toBe(0);
});

it('gives nothing away about evidence outside scope or family: an unknown proof answers alike (DP-13; SM-12; PJ-08)', function () {
    $recorder = User::query()->findOrFail($this->world->actorId);
    $elsewhere = InvoiceWorld::make();
    $hidden = PaymentWorld::proof($elsewhere->company->id);

    $outside = $this->actingAs($recorder)->post('/keuangan/pembayaran', recordBody($this, ['bukti' => [$hidden->public_id], 'alasan_tanpa_bukti' => null]));
    $outside->assertSessionHasErrors('bukti');
    $first = session('errors')?->getBag('default')->first('bukti');

    $this->actingAs($recorder)->post('/keuangan/pembayaran', recordBody($this, ['bukti' => [(string) Str::uuid()], 'alasan_tanpa_bukti' => null]))->assertSessionHasErrors('bukti');
    expect(session('errors')?->getBag('default')->first('bukti'))->toBe($first);

    $blind = Authorization::admin([$this->company->id], ['finance.view', 'payment.record']);
    $this->actingAs($blind)->get('/keuangan/pembayaran/baru?perusahaan='.$this->company->public_id)->assertInertia(fn (AssertableInertia $page) => $page->has('evidence', 0)->where('can.upload', false));
    $this->actingAs($blind)->get('/keuangan/pembayaran')->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.proof', null));
    expect(Payment::query()->where('id', $this->payment->id)->exists())->toBeTrue();
});
