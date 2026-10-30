<?php

use App\Modules\IAM\Models\User;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-049; AZ-01; AZ-11; IG-01; SM-01; SM-04; SM-25; PJ-15; PJ-23; OD-07; J-FIN-01; criterion 12): every invoice screen absent without `finance.view`; the draft and the issue with `invoice.issue`, the corrections with `invoice.correct`
// (ADM_PLUS), the Kuitansi with `documents.issue`, its link with `payment.record`; another company's invoice unknown, its number never shown; DOC-04–DOC-06's payload only to `finance.view` holders of the invoice's company.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->project = $this->world->project();
    $this->invoice = $this->world->issued($this->project, '1000000');
    $this->public = InvoiceWorld::publicId($this->invoice);
    $this->company = $this->world->company->id;
    $this->draftBody = ['command_id' => (string) Str::uuid(), 'proyek' => $this->project->public_id, 'isi' => ['jenis' => 'VALUE_ONLY', 'tanggal' => BusinessDate::today(), 'baris' => [['keterangan' => 'Termin', 'nilai' => '1000']]]];
});

afterEach(fn () => @unlink($this->hotFile));

it('has no invoice screen without finance.view (J-FIN-01; AZ-01; SM-04; criterion 12)', function () {
    $viewer = Authorization::admin([$this->company], ['projects.view', 'projects.manage']);

    $this->actingAs($viewer)->get('/keuangan/invoice')->assertForbidden();
    $this->actingAs($viewer)->get("/keuangan/invoice/{$this->public}")->assertForbidden();
    $this->actingAs($viewer)->get("/keuangan/invoice/baru?proyek={$this->project->public_id}")->assertForbidden();
});

it('refuses the draft and the issue without invoice.issue, and a draft holder without finance.view (AZ-01; IG-01; SM-04)', function () {
    $reader = Authorization::admin([$this->company], ['projects.view', 'finance.view']);
    $draft = $this->world->draft($this->project, '1000');

    $this->actingAs($reader)->get("/keuangan/invoice/baru?proyek={$this->project->public_id}")->assertForbidden();
    $this->actingAs($reader)->post('/keuangan/invoice', $this->draftBody)->assertForbidden();
    $this->actingAs($reader)->post('/keuangan/invoice/'.InvoiceWorld::publicId($draft).'/terbitkan', ['command_id' => (string) Str::uuid(), 'lock_version' => 0, 'tata' => 'DOC-06', 'tanggal' => BusinessDate::today()])->assertForbidden();

    $blind = Authorization::admin([$this->company], ['projects.view', 'invoice.issue']);
    $this->actingAs($blind)->get("/keuangan/invoice/baru?proyek={$this->project->public_id}")->assertForbidden();
    $this->actingAs($blind)->post('/keuangan/invoice', $this->draftBody)->assertForbidden();
    expect(DB::table('invoices')->where('project_id', $this->project->id)->count())->toBe(2);
});

it('refuses the correction without invoice.correct, its controls absent (AZ-01; SM-04; criterion 12)', function () {
    $reader = Authorization::admin([$this->company], ['projects.view', 'finance.view']);

    $this->actingAs($reader)->post("/keuangan/invoice/{$this->public}/koreksi", ['command_id' => (string) Str::uuid(), 'aksi' => 'BATAL', 'dasar' => 'UNBILLED_ENTRY_ERROR', 'lock_version' => 1, 'alasan' => 'Salah catat', 'tanggal' => BusinessDate::today()])->assertForbidden();
    $this->actingAs($reader)->get("/keuangan/invoice/{$this->public}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.correct', false)->where('can.kuitansi', false)->where('can.link', false)->has('evidence', 0));
    $this->actingAs(User::query()->findOrFail($this->world->actorId))->get("/keuangan/invoice/{$this->public}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.correct', true)->where('can.supersede', false)->where('can.kuitansi', true)->where('can.link', true));
    $this->actingAs(Authorization::owner())->get("/keuangan/invoice/{$this->public}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.supersede', true));

    expect(DB::table('invoices')->where('id', $this->invoice)->value('state'))->toBe('ISSUED');
});

it('refuses the Kuitansi without documents.issue and its link without payment.record (AX-31; SM-04)', function () {
    $reader = Authorization::admin([$this->company], ['projects.view', 'finance.view']);

    $this->actingAs($reader)->post("/keuangan/invoice/{$this->public}/kuitansi", ['command_id' => (string) Str::uuid(), 'dokumen' => (string) Str::uuid(), 'draf' => (string) Str::uuid(), 'lock_version' => 0, 'tanggal' => BusinessDate::today(), 'alasan' => 'Proses pembayaran'])->assertForbidden();
    $this->actingAs($reader)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-05', 'mode' => 'PRE_PAYMENT', 'subjek' => ['invoice_id' => $this->public], 'jumlah' => '1000'])->assertForbidden();
    $this->actingAs($reader)->post('/keuangan/kuitansi/'.Str::uuid().'/tautkan', ['command_id' => (string) Str::uuid(), 'pembayaran' => [(string) Str::uuid()]])->assertForbidden();
});

it('answers another company\'s invoice as unknown, each command writing nothing, and never lists its number (SM-01; CS-03; CS-04; PJ-23; AZ-11)', function () {
    $stranger = InvoiceWorld::make();
    $actor = User::query()->findOrFail($stranger->actorId);
    $number = (string) DB::table('document_versions as v')->join('documents as d', 'd.id', '=', 'v.document_id')->where('d.invoice_id', $this->invoice)->value('v.formatted_number');

    $this->actingAs($actor)->get("/keuangan/invoice/{$this->public}")->assertNotFound();
    $this->actingAs($actor)->get("/keuangan/invoice/baru?invoice={$this->public}")->assertNotFound();
    $this->actingAs($actor)->get("/keuangan/invoice/baru?proyek={$this->project->public_id}")->assertNotFound();
    $this->actingAs($actor)->post("/keuangan/invoice/{$this->public}/terbitkan", ['command_id' => (string) Str::uuid(), 'lock_version' => 0, 'tata' => 'DOC-06', 'tanggal' => BusinessDate::today()])->assertNotFound();
    $this->actingAs($actor)->post("/keuangan/invoice/{$this->public}/koreksi", ['command_id' => (string) Str::uuid(), 'aksi' => 'BATAL', 'dasar' => 'UNBILLED_ENTRY_ERROR', 'lock_version' => 1, 'alasan' => 'Salah catat', 'tanggal' => BusinessDate::today()])->assertNotFound();
    $this->actingAs($actor)->post('/keuangan/invoice', $this->draftBody)->assertNotFound();
    $this->actingAs($actor)->get('/keuangan/invoice?nomor='.urlencode($number))->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0));

    expect(DB::table('invoices')->where('id', $this->invoice)->value('state'))->toBe('ISSUED')->and(DB::table('invoices')->where('company_id', $stranger->company->id)->count())->toBe(0);
});

it('gives DOC-04–DOC-06\'s payload only to finance.view holders of the invoice\'s company, metadata to projects.view (PJ-15; SM-25; criterion 12)', function () {
    $document = DB::table('documents')->where('invoice_id', $this->invoice)->sole();
    $viewer = Authorization::admin([$this->company], ['projects.view']);

    $this->actingAs($viewer)->get("/dokumen/{$document->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('content', false));
    $this->actingAs(User::query()->findOrFail($this->world->actorId))->get("/dokumen/{$document->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('content', true));
});

it('offers the journey\'s Invoice link only to a holder of invoice.issue with finance.view (AZ-01; PL-3)', function () {
    $line = InvoiceWorld::line($this->project);
    DB::table('project_line_balances')->where('project_line_id', $line)->update(['handed_over_qty_base' => '10.000']);
    $viewer = Authorization::admin([$this->company], ['projects.view', 'finance.view']);

    $this->actingAs($viewer)->get("/proyek/{$this->project->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('journey.next.key', 'projects.next.invoice')->where('journey.next.href', null));
});

it('lets a second layout of an invoice issue only through invoice.issue (PERMISSIONS_MATRIX §4; AZ-01)', function () {
    $holder = Authorization::admin([$this->company], ['projects.view', 'finance.view', 'documents.issue']);

    $this->actingAs($holder)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-04', 'subjek' => ['invoice_id' => $this->public]])->assertForbidden();
    $this->actingAs($holder)->get("/keuangan/invoice/{$this->public}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.nota', false)->where('can.kuitansi', true));
    expect(DB::table('documents')->where('invoice_id', $this->invoice)->where('document_type_code', 'DOC-04')->exists())->toBeFalse();
});
