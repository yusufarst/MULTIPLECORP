<?php

use App\Modules\IAM\Models\User;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-049; IG-01–IG-05; INT-241; INT-242; INT-245; INT-249; INT-250; CI-05; UXS-01; UXS-02; criterion 3): each command once for one `command_id` sent twice, nothing written twice, the same identifier with other content a CONFLICT; "Terbitkan Invoice" pressed
// twice or retried after a lost answer issues once with one number.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->actor = User::query()->findOrFail($this->world->actorId);
    $this->project = $this->world->project();
});

afterEach(fn () => @unlink($this->hotFile));

function invoiceInteractionAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

it('saves a draft once for one command_id sent twice, another content a CONFLICT (INT-241; CI-05)', function () {
    $body = ['command_id' => (string) Str::uuid(), 'proyek' => $this->project->public_id, 'isi' => ['jenis' => 'VALUE_ONLY', 'tanggal' => BusinessDate::today(), 'baris' => [['keterangan' => 'Termin', 'nilai' => '1000']]]];

    $this->actingAs($this->actor)->post('/keuangan/invoice', $body);
    $this->actingAs($this->actor)->post('/keuangan/invoice', $body);
    expect(invoiceInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('invoices')->count())->toBe(1);

    $this->actingAs($this->actor)->post('/keuangan/invoice', [...$body, 'isi' => ['jenis' => 'VALUE_ONLY', 'tanggal' => BusinessDate::today(), 'baris' => [['keterangan' => 'Termin', 'nilai' => '2000']]]]);
    expect(invoiceInteractionAnswer()['kind'])->toBe('CONFLICT')->and(DB::table('invoices')->count())->toBe(1);
});

it('issues once with one number for one command_id sent twice, its answer replayed; a second press CONFLICT with the number (INT-242; UXS-01; UXS-02; BD-06)', function () {
    $id = $this->world->draft($this->project, '1000000');
    $body = ['command_id' => (string) Str::uuid(), 'lock_version' => (int) DB::table('invoices')->where('id', $id)->value('lock_version'), 'tata' => 'DOC-06', 'tanggal' => BusinessDate::today()];
    $path = '/keuangan/invoice/'.InvoiceWorld::publicId($id).'/terbitkan';

    $this->actingAs($this->actor)->post($path, $body);
    $this->actingAs($this->actor)->post($path, $body);
    expect(invoiceInteractionAnswer()['kind'])->toBe('COMMITTED')
        ->and(DB::table('document_versions as v')->join('documents as d', 'd.id', '=', 'v.document_id')->where('d.invoice_id', $id)->where('v.state', 'ISSUED')->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'FIN.InvoiceIssued')->count())->toBe(1)
        ->and(DB::table('project_commercial_balances')->where('project_id', $this->project->id)->value('invoiced_sales_value'))->toBe('1000000.00');

    $this->actingAs($this->actor)->post($path, [...$body, 'command_id' => (string) Str::uuid()]);
    expect(invoiceInteractionAnswer()['code'])->toBe('BD-06');
});

it('corrects once for one command_id sent twice (INT-245; CI-05)', function () {
    $id = $this->world->issued($this->project, '1000000');
    $body = ['command_id' => (string) Str::uuid(), 'aksi' => 'BATAL', 'dasar' => 'UNBILLED_ENTRY_ERROR', 'lock_version' => (int) DB::table('invoices')->where('id', $id)->value('lock_version'), 'alasan' => 'Salah catat', 'tanggal' => BusinessDate::today()];
    $path = '/keuangan/invoice/'.InvoiceWorld::publicId($id).'/koreksi';

    $this->actingAs($this->actor)->post($path, $body);
    $this->actingAs($this->actor)->post($path, $body);
    expect(invoiceInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('audit_events')->where('action', 'FIN.InvoiceVoided')->count())->toBe(1);

    $this->actingAs($this->actor)->post($path, [...$body, 'alasan' => 'Alasan lain']);
    expect(invoiceInteractionAnswer()['kind'])->toBe('CONFLICT');
});

it('issues and links the Kuitansi once for one command_id each (INT-249; INT-250; CI-05)', function () {
    $id = $this->world->issued($this->project, '1000000');
    $public = InvoiceWorld::publicId($id);
    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-05', 'mode' => 'PRE_PAYMENT', 'subjek' => ['invoice_id' => $public], 'jumlah' => '400000']);
    $document = DB::table('documents')->where('invoice_id', $id)->where('document_type_code', 'DOC-05')->sole();
    $draft = DB::table('document_versions')->where('document_id', $document->id)->sole();
    $issue = ['command_id' => (string) Str::uuid(), 'dokumen' => $document->public_id, 'draf' => $draft->public_id, 'lock_version' => $draft->lock_version, 'tanggal' => BusinessDate::today(), 'alasan' => 'Proses pembayaran'];

    $this->actingAs($this->actor)->post("/keuangan/invoice/{$public}/kuitansi", $issue);
    $this->actingAs($this->actor)->post("/keuangan/invoice/{$public}/kuitansi", $issue);
    expect(invoiceInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('audit_events')->where('action', 'FIN.PrepaymentKuitansiIssued')->count())->toBe(1);

    $payment = InvoiceWorld::payment($id, '400000');
    InvoiceWorld::apply($id, $payment, '400000');
    $link = ['command_id' => (string) Str::uuid(), 'pembayaran' => [$payment->public_id]];
    $this->actingAs($this->actor)->post("/keuangan/kuitansi/{$document->public_id}/tautkan", $link);
    $this->actingAs($this->actor)->post("/keuangan/kuitansi/{$document->public_id}/tautkan", $link);
    expect(invoiceInteractionAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('prepayment_kuitansi_links')->count())->toBe(1);
});
