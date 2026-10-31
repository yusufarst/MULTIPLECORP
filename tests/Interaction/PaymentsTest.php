<?php

use App\Modules\FIN\Data\ContraEntry;
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

// TL-08 (TASK-051; IG-05; INT-251–INT-254; INT-261; INT-262; INT-015; CI-05; AC-16; M-08–M-10): each command once for one `command_id` sent twice, nothing written twice, the recorded answer returned, also after the session ended and the user logged in again; the same identifier with other content a CONFLICT.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = User::query()->findOrFail(Authorization::admin([$this->company->id], [...InvoiceWorld::CAPABILITIES, 'bank_statement.record', 'cash.correct', 'warnings.override'])->id);
    $this->project = $this->world->project();
    $this->invoice = $this->world->issued($this->project, '1000000');
    $this->invoiceKey = InvoiceWorld::publicId($this->invoice);
    $this->today = BusinessDate::today();
    $this->account = PaymentWorld::account($this->company->id);
    $this->clientKey = DB::table('client_organizations')->where('id', $this->project->client_organization_id)->value('public_id');
    $this->body = fn (array $fields = []): array => ['command_id' => (string) Str::uuid(), 'perusahaan' => $this->company->public_id, 'klien' => $this->clientKey, 'tanggal' => $this->today, 'jumlah' => '300000', 'metode' => 'TRANSFER', 'rekening' => PaymentWorld::accountHandle($this->account), 'alasan_tanpa_bukti' => 'Bukti menyusul', ...$fields];
});

afterEach(fn () => @unlink($this->hotFile));

function paymentInteractionAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

it('records once for one command_id sent twice, the answer lost and the session gone; other content a CONFLICT (INT-251; CI-05; M-08–M-10; criterion 5)', function () {
    $body = ($this->body)(['penerapan' => [['invoice' => $this->invoiceKey, 'jumlah' => '300000']]]);

    $this->actingAs($this->actor)->post('/keuangan/pembayaran', $body)->assertRedirectContains('tercatat=');
    expect(paymentInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('payments')->count())->toBe(1);

    // The answer was lost and the session expired: logged in again, the retry returns the original result.
    auth()->logout();
    session()->flush();
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', $body)->assertRedirectContains('tercatat=');
    expect(paymentInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('payments')->count())->toBe(1)->and(DB::table('payment_applications')->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'FIN.PaymentRecorded')->count())->toBe(1)->and(DB::table('invoice_balances')->where('invoice_id', $this->invoice)->value('applied_amount'))->toBe('300000.00');

    $this->actingAs($this->actor)->post('/keuangan/pembayaran', [...$body, 'jumlah' => '300001']);
    expect(paymentInteractionAnswer()['kind'])->toBe('CONFLICT')->and(DB::table('payments')->count())->toBe(1);
});

it('applies once for one command_id sent twice (INT-252; CI-05)', function () {
    $payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '500000');
    $body = ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'penerapan' => [['invoice' => $this->invoiceKey, 'jumlah' => '200000']]];

    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/terapkan", $body);
    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/terapkan", $body);
    expect(paymentInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('payment_applications')->count())->toBe(1)->and(DB::table('application_source_balances')->where('payment_id', $payment->id)->value('capacity_amount'))->toBe('300000.00');

    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/terapkan", ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'penerapan' => [['invoice' => $this->invoiceKey, 'jumlah' => '900000']]]);
    expect(paymentInteractionAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-15'])->and(paymentInteractionAnswer()['state']['outstanding'])->toBe('800000.00');
    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/terapkan", ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'penerapan' => [['invoice' => $this->invoiceKey, 'jumlah' => '400000']]]);
    expect(paymentInteractionAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-13'])->and(paymentInteractionAnswer()['state']['capacity'])->toBe('300000.00');
    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/terapkan", ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today])->assertSessionHasErrors('penerapan');
});

it('reallocates once for one command_id sent twice; an ended application a CONFLICT (INT-253; CI-05; AX-19)', function () {
    $payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '500000', '500000');
    $application = (int) DB::table('payment_applications')->where('payment_id', $payment->id)->value('id');
    $target = $this->world->issued($this->project, '500000');
    $body = ['command_id' => (string) Str::uuid(), 'penerapan' => RowHandle::of('payment_applications', $application), 'invoice' => InvoiceWorld::publicId($target), 'jumlah' => '500000', 'tanggal' => $this->today, 'alasan' => 'Salah invoice'];

    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/realokasi", $body);
    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/realokasi", $body);
    expect(paymentInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('payment_applications')->count())->toBe(2)->and(DB::table('audit_events')->where('action', 'FIN.ApplicationReallocated')->count())->toBe(1);

    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/realokasi", [...$body, 'command_id' => (string) Str::uuid()]);
    expect(paymentInteractionAnswer()['kind'])->toBe('CONFLICT');
});

it('contras once for one command_id sent twice (INT-254; CI-05)', function () {
    $payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '500000', '500000');
    $application = (int) DB::table('payment_applications')->where('payment_id', $payment->id)->value('id');
    $body = ['command_id' => (string) Str::uuid(), 'jumlah' => '500000', 'tanggal' => $this->today, 'alasan' => 'Salah catat', 'penerapan' => [['baris' => RowHandle::of('payment_applications', $application), 'aksi' => ContraEntry::RELEASE]]];

    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/kontra", $body);
    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/kontra", $body);
    expect(paymentInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('financial_contras')->count())->toBe(1)->and(DB::table('correction_cases')->count())->toBe(1);
});

it('records a statement line once for one command_id sent twice (INT-261; CI-05)', function () {
    $statement = PaymentWorld::proof($this->company->id, 'BANK_STATEMENT');
    $body = ['command_id' => (string) Str::uuid(), 'perusahaan' => $this->company->public_id, 'rekening' => PaymentWorld::accountHandle($this->account), 'bukti' => $statement->public_id, 'tanggal' => $this->today, 'jumlah' => '500000', 'referensi' => 'TRX-1'];

    $this->actingAs($this->actor)->post('/keuangan/mutasi-bank', $body);
    $this->actingAs($this->actor)->post('/keuangan/mutasi-bank', $body);
    expect(paymentInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('bank_statement_lines')->count())->toBe(1);

    $this->actingAs($this->actor)->post('/keuangan/mutasi-bank', [...$body, 'jumlah' => '1']);
    expect(paymentInteractionAnswer()['kind'])->toBe('CONFLICT');
});

it('voids, corrects a citation and supersedes a claim once for one command_id each (INT-262; CI-05)', function () {
    $line = PaymentWorld::line($this->company->id, $this->account, '500000');
    $handle = RowHandle::of('bank_statement_lines', $line);
    $void = ['command_id' => (string) Str::uuid(), 'aksi' => 'BATAL', 'alasan' => 'Salah ketik'];
    $this->actingAs($this->actor)->post("/keuangan/mutasi-bank/{$handle}/koreksi", $void);
    $this->actingAs($this->actor)->post("/keuangan/mutasi-bank/{$handle}/koreksi", $void);
    expect(paymentInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('bank_statement_lines')->where('id', $line)->value('state'))->toBe('VOIDED')->and(DB::table('audit_events')->where('action', 'DOC.BankLineVoided')->count())->toBe(1);

    // A stale page's second void: a CONFLICT, never a second void.
    $this->actingAs($this->actor)->post("/keuangan/mutasi-bank/{$handle}/koreksi", [...$void, 'command_id' => (string) Str::uuid()]);
    expect(paymentInteractionAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'ST-02']);

    $other = PaymentWorld::record($this->world->actorId, $this->company->id, $this->project->client_organization_id, '300000', ['accountId' => $this->account]);
    $publicId = DB::table('payments')->where('id', $other->resultId)->value('public_id');
    $target = PaymentWorld::line($this->company->id, $this->account, '300000', 'TRX-2');
    $cite = ['command_id' => (string) Str::uuid(), 'aksi' => 'KUTIPAN', 'mutasi' => RowHandle::of('bank_statement_lines', $target), 'alasan' => 'Baris yang benar'];
    $this->actingAs($this->actor)->post("/keuangan/mutasi-bank/{$publicId}/koreksi", $cite);
    $this->actingAs($this->actor)->post("/keuangan/mutasi-bank/{$publicId}/koreksi", $cite);
    expect(paymentInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('evidence_balances')->where('bank_statement_line_id', $target)->value('cited_amount'))->toBe('300000.00')->and(DB::table('audit_events')->where('action', 'FIN.BankCitationCorrected')->count())->toBe(1);
});

it('asks at the fields what a form lacks (IP-14; INT-251)', function () {
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', ['command_id' => (string) Str::uuid()])->assertSessionHasErrors(['perusahaan', 'klien', 'tanggal', 'jumlah', 'metode']);
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', ($this->body)(['jumlah' => 'abc']))->assertSessionHasErrors('jumlah');
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', ($this->body)(['tanggal' => '2999-01-01']))->assertSessionHasErrors('tanggal');
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', ($this->body)(['penerapan' => [['invoice' => $this->invoiceKey, 'jumlah' => '1'], ['invoice' => $this->invoiceKey, 'jumlah' => '2']]]))->assertSessionHasErrors('penerapan.1.invoice');
    $this->actingAs($this->actor)->post('/keuangan/mutasi-bank', ['command_id' => (string) Str::uuid()])->assertSessionHasErrors(['perusahaan', 'rekening', 'bukti', 'tanggal', 'jumlah', 'referensi']);
    expect(DB::table('payments')->count())->toBe(0);
});
