<?php

use App\Modules\DOC\Services\MustacheTemplates;
use App\Modules\FIN\Actions\SupersedeWriteOff;
use App\Modules\FIN\Models\Invoice;
use App\Modules\IAM\Models\User;
use App\Support\BusinessDate;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;

// TL-04, TL-10 (TASK-051; BR-DOC-04; C-43; WF-DOC-01; L-17; L-29; XL-5; BR-FIN-07 b; BR-ACC-03; CS-03; NAV-046; criterion 11): the receipt-mode DOC-05 and its DOC-07 through TASK-029's routes, in Indonesian; a recovery re-run on real money; money moved from another company; collection in a deactivated company; PG-046's company never prefilled for several.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = User::query()->findOrFail($this->world->actorId);
    $this->project = $this->world->project();
    $this->invoice = $this->world->issued($this->project, '1000000');
    $this->invoiceKey = InvoiceWorld::publicId($this->invoice);
    $this->client = $this->project->client_organization_id;
    $this->today = BusinessDate::today();
    InvoiceWorld::bill($this->invoice, $this->today, '2099-12-31');
    DocumentTypeFactory::ensure('DOC-07');
});

afterEach(fn () => @unlink($this->hotFile));

function paymentDocumentAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function receiptDraft(object $test, string $paymentKey): void
{
    $test->actingAs($test->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-05', 'mode' => 'RECEIPT', 'subjek' => ['payment_id' => $paymentKey, 'invoice_id' => $test->invoiceKey]]);
}

function issueDraft(object $test, object $document): void
{
    $draft = DB::table('document_versions')->where('document_id', $document->id)->where('state', 'DRAFT')->sole();
    $test->actingAs($test->actor)->post("/dokumen/{$document->public_id}/terbitkan", ['command_id' => (string) Str::uuid(), 'draf' => $draft->public_id, 'lock_version' => $draft->lock_version, 'tanggal' => $test->today]);
}

it('issues a receipt-mode Kuitansi for a recorded payment and its Lampiran, in Indonesian, changing no money; each voids with it (criterion 11; BR-DOC-04; WF-DOC-01; LOCALIZATION §3.4)', function () {
    $payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '400000', '400000');
    receiptDraft($this, $payment->public_id);
    $kuitansi = DB::table('documents')->where('payment_id', $payment->id)->where('document_type_code', 'DOC-05')->sole();
    expect($kuitansi->mode)->toBe('RECEIPT')->and(DB::table('document_versions')->where('document_id', $kuitansi->id)->value('kuitansi_amount'))->toBe('400000.00');

    // The Lampiran waits for an issued Kuitansi.
    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-07', 'subjek' => ['parent_document_id' => $kuitansi->public_id]]);
    $lampiran = DB::table('documents')->where('parent_document_id', $kuitansi->id)->first();

    if ($lampiran !== null) {
        issueDraft($this, $lampiran);
        expect(DB::table('document_versions')->where('document_id', $lampiran->id)->where('state', 'ISSUED')->count())->toBe(0);
    }

    issueDraft($this, $kuitansi);
    expect(DB::table('document_versions')->where('document_id', $kuitansi->id)->where('state', 'ISSUED')->count())->toBe(1);

    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-07', 'subjek' => ['parent_document_id' => $kuitansi->public_id]]);
    $lampiran = DB::table('documents')->where('parent_document_id', $kuitansi->id)->where('document_type_code', 'DOC-07')->sole();
    issueDraft($this, $lampiran);
    $issued = DB::table('document_versions')->where('document_id', $lampiran->id)->where('state', 'ISSUED')->sole();
    $paper = json_decode($issued->payload, true)['paper'];
    $receipt = json_decode((string) DB::table('document_versions')->where('document_id', $kuitansi->id)->where('state', 'ISSUED')->value('payload'), true)['paper'];
    app()->setLocale('en');
    expect(MustacheTemplates::render('DOC-07', $paper))->toContain('Lampiran Kuitansi')->toContain('Nilai Kuitansi')->and(MustacheTemplates::render('DOC-05', $receipt))->toContain('Sudah terima dari')->not->toContain('belum menyatakan')->and(DB::table('invoice_balances')->where('invoice_id', $this->invoice)->value('outstanding'))->toBe('600000.00')
        ->and(DB::table('application_source_balances')->where('payment_id', $payment->id)->value('net_amount'))->toBe('400000.00');

    $this->actingAs($this->actor)->post("/dokumen/{$kuitansi->public_id}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Salah cetak']);
    expect(DB::table('document_versions')->where('document_id', $kuitansi->id)->where('state', 'VOIDED')->count())->toBe(1)
        ->and(DB::table('document_versions')->where('document_id', $lampiran->id)->where('state', 'VOIDED')->count())->toBe(1)
        ->and(DB::table('application_source_balances')->where('payment_id', $payment->id)->value('net_amount'))->toBe('400000.00');
});

it('refuses a receipt-mode Kuitansi without money applied, while a pre-payment one is open, and for money already linked (criterion 11; C-43; WF-FIN-08)', function () {
    $unapplied = PaymentWorld::paid($this->world->actorId, $this->invoice, '100000');
    receiptDraft($this, $unapplied->public_id);
    expect(paymentDocumentAnswer())->toMatchArray(['kind' => 'REJECTED'])->and(DB::table('documents')->where('payment_id', $unapplied->id)->count())->toBe(0);

    $payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '400000', '400000');
    receiptDraft($this, $payment->public_id);
    $kuitansi = DB::table('documents')->where('payment_id', $payment->id)->where('document_type_code', 'DOC-05')->sole();

    // A pre-payment Kuitansi opens on the invoice before the receipt is issued.
    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-05', 'mode' => 'PRE_PAYMENT', 'subjek' => ['invoice_id' => $this->invoiceKey], 'jumlah' => '400000']);
    $open = DB::table('documents')->where('invoice_id', $this->invoice)->where('mode', 'PRE_PAYMENT')->sole();
    $draft = DB::table('document_versions')->where('document_id', $open->id)->where('state', 'DRAFT')->sole();
    $this->actingAs($this->actor)->post("/keuangan/invoice/{$this->invoiceKey}/kuitansi", ['command_id' => (string) Str::uuid(), 'dokumen' => $open->public_id, 'draf' => $draft->public_id, 'lock_version' => $draft->lock_version, 'tanggal' => $this->today, 'alasan' => 'Syarat pencairan']);
    expect(DB::table('document_versions')->where('document_id', $open->id)->where('state', 'ISSUED')->count())->toBe(1);

    issueDraft($this, $kuitansi);
    expect(paymentDocumentAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-43'])->and(paymentDocumentAnswer()['text'])->toContain('masih ada Kuitansi')->and(DB::table('document_versions')->where('document_id', $kuitansi->id)->where('state', 'ISSUED')->count())->toBe(0);

    // Linked: the same money receives no second Kuitansi.
    $this->actingAs($this->actor)->post("/keuangan/kuitansi/{$open->public_id}/tautkan", ['command_id' => (string) Str::uuid(), 'pembayaran' => [$payment->public_id]]);
    expect(DB::table('prepayment_kuitansi_links')->where('state', 'LINKED')->count())->toBe(1);
    issueDraft($this, $kuitansi);
    expect(paymentDocumentAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-43'])->and(paymentDocumentAnswer()['text'])->toContain('sudah tertaut');
});

it('unlinks a Kuitansi whose linked total a reallocation or a contra lowers (AX-31; AX-19; AX-20; QS-19)', function () {
    $payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '400000', '400000');
    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-05', 'mode' => 'PRE_PAYMENT', 'subjek' => ['invoice_id' => $this->invoiceKey], 'jumlah' => '400000']);
    $open = DB::table('documents')->where('invoice_id', $this->invoice)->where('mode', 'PRE_PAYMENT')->sole();
    $draft = DB::table('document_versions')->where('document_id', $open->id)->where('state', 'DRAFT')->sole();
    $this->actingAs($this->actor)->post("/keuangan/invoice/{$this->invoiceKey}/kuitansi", ['command_id' => (string) Str::uuid(), 'dokumen' => $open->public_id, 'draf' => $draft->public_id, 'lock_version' => $draft->lock_version, 'tanggal' => $this->today, 'alasan' => 'Syarat pencairan']);
    $this->actingAs($this->actor)->post("/keuangan/kuitansi/{$open->public_id}/tautkan", ['command_id' => (string) Str::uuid(), 'pembayaran' => [$payment->public_id]]);
    expect(DB::table('prepayment_kuitansi_links')->where('state', 'LINKED')->count())->toBe(1);

    $target = $this->world->issued($this->project, '500000');
    $application = (int) DB::table('payment_applications')->where('payment_id', $payment->id)->value('id');
    expect(PaymentWorld::reallocateInCommand($this->world->actorId, $payment->id, $application, $target, '100000'))->toBe('COMMITTED')
        ->and(DB::table('prepayment_kuitansi_links')->where('state', 'LINKED')->count())->toBe(0)
        ->and(DB::table('prepayment_kuitansi_links')->value('unlink_reason'))->toBe('invoices.unlink.corrected');
});

it('carries money recovered for a written-off invoice, listed for the Owner, and lifts the write-off on real money (WF-FIN-05; L-17; QS-14; FX-03)', function () {
    $owner = Authorization::owner();
    expect(InvoiceWorld::writeOffInCommand($owner->id, $this->invoice, '1000000', $this->today))->toBe('COMMITTED');
    $writeoff = (int) DB::table('receivable_writeoffs')->value('id');

    $elsewhere = $this->world->issued($this->project, '1000000');
    expect(PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '100000', ['recoveryFor' => $elsewhere])->code)->toBe('L-17');

    $recovered = PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '400000', ['recoveryFor' => $this->invoice]);
    expect($recovered->kind)->toBe('COMMITTED')->and(DB::table('audit_events')->where('action', 'FIN.PaymentRecorded')->orderByDesc('id')->value('metadata'))->toContain($this->invoiceKey);

    $this->actingAs($this->actor)->get('/keuangan/pembayaran')->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.recovery', true));

    $outcome = app(SupersedeWriteOff::class)->run((string) Str::uuid(), $owner->id, Invoice::query()->findOrFail($this->invoice), $writeoff, $recovered->resultId, '400000', 'Klien membayar kembali', $this->today, false);
    expect($outcome->kind)->toBe('COMMITTED')->and(DB::table('invoice_balances')->where('invoice_id', $this->invoice)->value('written_off_amount'))->toBe('600000.00')
        ->and(DB::table('invoice_balances')->where('invoice_id', $this->invoice)->value('applied_amount'))->toBe('400000.00')
        ->and(DB::table('application_source_balances')->where('payment_id', $recovered->resultId)->value('capacity_amount'))->toBe('0.00');
});

it('cites another company\'s transfer-out within its cap, both in scope, otherwise the Owner records it (XL-5; BR-FIN-07 b; DATABASE §11.5)', function () {
    $sender = InvoiceWorld::make();
    $account = PaymentWorld::account($sender->company->id);
    $transfer = DB::table('disbursements')->insertGetId(['public_id' => (string) Str::uuid(), 'company_id' => $sender->company->id, 'business_date' => $this->today, 'amount' => '500000', 'kind' => 'INTERCOMPANY_TRANSFER_OUT', 'company_bank_account_id' => $account, 'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $sender->actorId]);
    DB::table('money_fact_balances')->insert(['disbursement_id' => $transfer, 'company_id' => $sender->company->id, 'fact_kind' => 'TRANSFER_OUT', 'amount' => '500000', 'recorded_at' => now()]);

    // One company out of scope: the Admin cannot, the Owner can.
    expect(PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '300000', ['transferFrom' => $transfer])->code)->toBe('XL-5');

    $owner = Authorization::owner();
    $first = PaymentWorld::record($owner->id, $this->company->id, $this->client, '300000', ['transferFrom' => $transfer]);
    expect($first->kind)->toBe('COMMITTED')->and(DB::table('money_fact_balances')->where('disbursement_id', $transfer)->value('transfer_cited_amount'))->toBe('300000.00')
        ->and(PaymentWorld::record($owner->id, $this->company->id, $this->client, '300000', ['transferFrom' => $transfer])->code)->toBe('BR-FIN-07');

    // A contra frees what it no longer carries.
    expect(PaymentWorld::contraInCommand($owner->id, $first->resultId, '300000'))->toBe('COMMITTED')->and(DB::table('money_fact_balances')->where('disbursement_id', $transfer)->value('transfer_cited_amount'))->toBe('0.00');
});

it('keeps collection open on an existing receivable of a deactivated company, through its own invoice (BR-ACC-03)', function () {
    DB::table('companies')->where('id', $this->company->id)->update(['is_active' => false, 'deactivated_at' => now(), 'deactivated_by' => $this->world->actorId, 'deactivated_reason' => 'Tutup']);

    $this->actingAs($this->actor)->get('/keuangan/pembayaran/baru')->assertInertia(fn (AssertableInertia $page) => $page->has('companies', 0));
    $this->actingAs($this->actor)->get("/keuangan/pembayaran/baru?invoice={$this->invoiceKey}")->assertInertia(fn (AssertableInertia $page) => $page->where('company', $this->company->public_id)->where('origin', $this->invoiceKey)->has('invoices', 1));
    expect(PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '100000', ['applications' => [$this->invoice => '100000']])->kind)->toBe('COMMITTED');
});

it('prefills PG-046 from its origin record, one company being no choice, several never from a filter (CS-03; NAV-046; J-FIN-03)', function () {
    $this->actingAs($this->actor)->get("/keuangan/pembayaran/baru?invoice={$this->invoiceKey}")->assertInertia(fn (AssertableInertia $page) => $page->component('FIN/RecordPayment', false)->where('company', $this->company->public_id)->where('client.name', fn (string $name) => $name !== '')->where('invoices.0.id', $this->invoiceKey));
    $this->actingAs($this->actor)->get("/keuangan/pembayaran/baru?proyek={$this->project->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('company', $this->company->public_id)->has('invoices', 1)->where('origin', null));
    $this->actingAs($this->actor)->get('/keuangan/pembayaran/baru')->assertInertia(fn (AssertableInertia $page) => $page->where('company', $this->company->public_id)->where('client', null)->has('invoices', 0));

    $other = InvoiceWorld::make();
    $both = Authorization::admin([$this->company->id, $other->company->id], ['finance.view', 'payment.record']);
    $this->actingAs($both)->get('/keuangan/pembayaran/baru')->assertInertia(fn (AssertableInertia $page) => $page->has('companies', 2)->where('company', null)->has('accounts', 0));
});
