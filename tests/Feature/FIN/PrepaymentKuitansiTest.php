<?php

use App\Modules\IAM\Models\User;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-049; ROUTE-249; ROUTE-250; ROUTE-221; AX-31; WF-FIN-08; J-FIN-08; CM-26; C-43; GAP-029; criteria 10, 11): the pre-payment Kuitansi's draft with the amount entered, its issue only through its own route with its reason, within Σ open ≤
// outstanding, creating no Cash-In, payment, settlement or paid state; its link only when the amounts agree, once per (payment, invoice), a later correction that lowers the total unlinking it with the cause shown.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->actor = User::query()->findOrFail($this->world->actorId);
    $this->project = $this->world->project();
    $this->invoice = $this->world->issued($this->project, '1000000');
    $this->public = InvoiceWorld::publicId($this->invoice);
});

afterEach(fn () => @unlink($this->hotFile));

function kuitansiAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

/** ROUTE-221's draft with the amount entered; the document. */
function kuitansiDraft(object $test, string $amount): object
{
    $test->actingAs($test->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-05', 'mode' => 'PRE_PAYMENT', 'subjek' => ['invoice_id' => $test->public], 'jumlah' => $amount]);

    return DB::table('documents')->where('invoice_id', $test->invoice)->where('document_type_code', 'DOC-05')->orderByDesc('id')->first();
}

function kuitansiIssue(object $test, object $document, array $overrides = []): void
{
    $draft = DB::table('document_versions')->where('document_id', $document->id)->where('state', 'DRAFT')->first();
    $test->actingAs($test->actor)->post("/keuangan/invoice/{$test->public}/kuitansi", ['command_id' => (string) Str::uuid(), 'dokumen' => $document->public_id, 'draf' => $draft?->public_id ?? (string) Str::uuid(), 'lock_version' => $draft?->lock_version ?? 0, 'tanggal' => BusinessDate::today(), 'alasan' => 'Klien meminta untuk proses pembayaran', ...$overrides]);
}

function kuitansiLink(object $test, object $document, array $payments): void
{
    $test->actingAs($test->actor)->post("/keuangan/kuitansi/{$document->public_id}/tautkan", ['command_id' => (string) Str::uuid(), 'pembayaran' => array_map(fn (object $payment): string => $payment->public_id, $payments)]);
}

it('prepares the draft with the amount entered and opens it on PG-036, saving it again on the next press (INT-249; INT-221; DIR-081 OB-02)', function () {
    $document = kuitansiDraft($this, '400000');
    $this->assertSame('400000.00', DB::table('document_versions')->where('document_id', $document->id)->value('kuitansi_amount'));

    $this->actingAs($this->actor)->get("/dokumen/{$document->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('document.invoice', $this->public)->where('document.mode', 'PRE_PAYMENT')
        ->where('draft.paper.amount', 'Rp400.000')->where('draft.paper.prepayment', true)->where('draft.paper.number', null));

    $again = kuitansiDraft($this, '300000');
    expect($again->id)->toBe($document->id)->and(DB::table('document_versions')->where('document_id', $document->id)->count())->toBe(1)
        ->and(DB::table('document_versions')->where('document_id', $document->id)->value('kuitansi_amount'))->toBe('300000.00');

    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-05', 'mode' => 'PRE_PAYMENT', 'subjek' => ['invoice_id' => $this->public]])->assertSessionHasErrors('jumlah');
});

it('issues only through its own route with its reason, creating no Cash-In, payment, settlement or paid state, the position unchanged (AX-31; GAP-029; FS-04; DIR-081 OB-02; criterion 10)', function () {
    $document = kuitansiDraft($this, '400000');
    $draft = DB::table('document_versions')->where('document_id', $document->id)->first();
    $before = ['payments' => DB::table('payments')->count(), 'applications' => DB::table('payment_applications')->count(), 'settlements' => DB::table('deduction_settlements')->count(), 'outstanding' => DB::table('invoice_balances')->where('invoice_id', $this->invoice)->value('outstanding')];

    // ROUTE-222 never issues it.
    $this->actingAs($this->actor)->post("/dokumen/{$document->public_id}/terbitkan", ['command_id' => (string) Str::uuid(), 'draf' => $draft->public_id, 'lock_version' => $draft->lock_version, 'tanggal' => BusinessDate::today()]);
    expect(kuitansiAnswer()['kind'])->toBe('REJECTED')->and(DB::table('document_versions')->where('id', $draft->id)->value('state'))->toBe('DRAFT');

    $this->actingAs($this->actor)->post("/keuangan/invoice/{$this->public}/kuitansi", ['command_id' => (string) Str::uuid(), 'dokumen' => $document->public_id, 'draf' => $draft->public_id, 'lock_version' => $draft->lock_version, 'tanggal' => BusinessDate::today()])->assertSessionHasErrors('alasan');

    kuitansiIssue($this, $document);
    $issued = DB::table('document_versions')->where('id', $draft->id)->first();
    $payload = json_decode($issued->payload, true);

    expect(kuitansiAnswer()['kind'])->toBe('COMMITTED')
        ->and($issued->state)->toBe('ISSUED')->and($issued->kuitansi_amount)->toBe('400000.00')->and($issued->formatted_number)->toStartWith('KW/')
        ->and($payload['paper']['prepayment'])->toBeTrue()->and($payload['paper']['amount'])->toBe('Rp400.000')
        ->and(['payments' => DB::table('payments')->count(), 'applications' => DB::table('payment_applications')->count(), 'settlements' => DB::table('deduction_settlements')->count(), 'outstanding' => DB::table('invoice_balances')->where('invoice_id', $this->invoice)->value('outstanding')])->toBe($before)
        ->and(DB::table('audit_events')->where('action', 'FIN.PrepaymentKuitansiIssued')->value('reason'))->toBe('Klien meminta untuk proses pembayaran');

    $this->actingAs($this->actor)->get("/keuangan/invoice/{$this->public}")->assertInertia(fn (AssertableInertia $page) => $page->where('kuitansi.0.state', 'ISSUED')->where('kuitansi.0.linked', false)->where('kuitansi.0.number', $issued->formatted_number)->where('position.outstanding', '1000000.00'));
    $this->actingAs($this->actor)->get("/dokumen/{$document->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('document.prepayment', 'open_not_linked'));
});

it('refuses a Kuitansi above what is outstanding counting the open ones, and on a draft invoice (C-43; WF-FIN-08; criterion 10)', function () {
    kuitansiIssue($this, kuitansiDraft($this, '700000'));
    expect(kuitansiAnswer()['kind'])->toBe('COMMITTED');

    kuitansiIssue($this, kuitansiDraft($this, '300001'));
    expect(kuitansiAnswer()['kind'])->toBe('REJECTED')->and(kuitansiAnswer()['code'])->toBe('C-43')->and(kuitansiAnswer()['state']['open'])->toBe('700000.00')->and(kuitansiAnswer()['state']['outstanding'])->toBe('1000000.00');

    $second = DB::table('documents')->where('invoice_id', $this->invoice)->where('document_type_code', 'DOC-05')->orderByDesc('id')->first();
    kuitansiDraft($this, '300000');
    kuitansiIssue($this, $second);
    expect(kuitansiAnswer()['kind'])->toBe('COMMITTED');

    $draftInvoice = $this->world->draft($this->project, '1000');
    $this->public = InvoiceWorld::publicId($draftInvoice);
    $this->invoice = $draftInvoice;
    kuitansiIssue($this, kuitansiDraft($this, '100'));
    expect(kuitansiAnswer()['code'])->toBe('WF-DOC-01');
});

it('links only when the amount equals the payments\' applications plus the invoice\'s settlements, else REJECTED with both figures and the path (AX-31; CM-26; criterion 11)', function () {
    $document = kuitansiDraft($this, '400000');
    kuitansiIssue($this, $document);
    $payment = InvoiceWorld::payment($this->invoice, '500000', '150000');
    InvoiceWorld::apply($this->invoice, $payment, '250000');

    kuitansiLink($this, $document, [$payment]);
    expect(kuitansiAnswer()['kind'])->toBe('REJECTED')->and(kuitansiAnswer()['code'])->toBe('C-43')->and(kuitansiAnswer()['state']['kuitansi'])->toBe('400000.00')->and(kuitansiAnswer()['state']['linked'])->toBe('250000.00')
        ->and(kuitansiAnswer()['text'])->toContain('Revisi Kuitansinya');

    InvoiceWorld::settle($this->invoice, $payment, '150000');
    kuitansiLink($this, $document, [$payment]);
    expect(kuitansiAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('prepayment_kuitansi_links')->where('document_id', $document->id)->value('state'))->toBe('LINKED');
    $this->actingAs($this->actor)->get("/keuangan/invoice/{$this->public}")->assertInertia(fn (AssertableInertia $page) => $page->where('kuitansi.0.linked', true)->has('payments', 0));
    $this->actingAs($this->actor)->get("/dokumen/{$document->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('document.prepayment', 'linked'));

    // Money already linked receives no second Kuitansi.
    $second = kuitansiDraft($this, '400000');
    kuitansiIssue($this, $second);
    kuitansiLink($this, $second, [$payment]);
    expect(kuitansiAnswer()['code'])->toBe('C-43')->and(kuitansiAnswer()['text'])->toContain('sudah punya Kuitansi');
});

it('unlinks when a later correction lowers the linked total, the cause shown; the void of the Kuitansi touches no payment and leaves it relinkable (QS-19; CM-26; criterion 11)', function () {
    $document = kuitansiDraft($this, '400000');
    kuitansiIssue($this, $document);
    $payment = InvoiceWorld::payment($this->invoice, '400000');
    $application = InvoiceWorld::apply($this->invoice, $payment, '400000');
    kuitansiLink($this, $document, [$payment]);
    expect(DB::table('prepayment_kuitansi_links')->where('document_id', $document->id)->value('state'))->toBe('LINKED');

    $lock = (int) DB::table('invoices')->where('id', $this->invoice)->value('lock_version');
    $this->actingAs($this->actor)->post("/keuangan/invoice/{$this->public}/koreksi", ['command_id' => (string) Str::uuid(), 'aksi' => 'BATAL', 'dasar' => 'UNBILLED_ENTRY_ERROR', 'lock_version' => $lock, 'alasan' => 'Salah catat', 'tanggal' => BusinessDate::today(),
        'disposisi' => ['aplikasi' => [['id' => RowHandle::of('payment_applications', $application), 'tindakan' => 'RELEASE']]]]);
    expect(kuitansiAnswer()['kind'])->toBe('COMMITTED');
    $link = DB::table('prepayment_kuitansi_links')->where('document_id', $document->id)->sole();
    expect($link->state)->toBe('UNLINKED')->and($link->unlink_reason)->toBe('invoices.unlink.corrected')->and(DB::table('payments')->where('id', $payment->id)->exists())->toBeTrue();

    $this->actingAs($this->actor)->get("/keuangan/invoice/{$this->public}")->assertInertia(fn (AssertableInertia $page) => $page->where('kuitansi.0.linked', false)->where('kuitansi.0.cause', 'invoices.unlink.corrected'));
});

it('ends the link when the Kuitansi itself is voided on PG-036, the payment untouched (CM-26; WF-FIN-08)', function () {
    $document = kuitansiDraft($this, '400000');
    kuitansiIssue($this, $document);
    $payment = InvoiceWorld::payment($this->invoice, '400000');
    InvoiceWorld::apply($this->invoice, $payment, '400000');
    kuitansiLink($this, $document, [$payment]);

    $this->actingAs($this->actor)->post("/dokumen/{$document->public_id}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Jumlahnya salah']);

    expect(kuitansiAnswer()['kind'])->toBe('COMMITTED')
        ->and(DB::table('prepayment_kuitansi_links')->where('document_id', $document->id)->value('state'))->toBe('UNLINKED')
        ->and(DB::table('payment_applications')->where('payment_id', $payment->id)->value('state'))->toBe('ACTIVE')
        ->and(DB::table('invoice_balances')->where('invoice_id', $this->invoice)->value('applied_amount'))->toBe('400000.00');
});

it('never recognizes the same money twice: a linked Kuitansi moves no balance (GAP-029; TEST_STRATEGY §5)', function () {
    $document = kuitansiDraft($this, '400000');
    kuitansiIssue($this, $document);
    $payment = InvoiceWorld::payment($this->invoice, '400000');
    InvoiceWorld::apply($this->invoice, $payment, '400000');
    $before = (array) DB::table('invoice_balances')->where('invoice_id', $this->invoice)->first();
    $source = (array) DB::table('application_source_balances')->where('payment_id', $payment->id)->first();

    kuitansiLink($this, $document, [$payment]);

    expect((array) DB::table('invoice_balances')->where('invoice_id', $this->invoice)->first())->toBe($before)
        ->and((array) DB::table('application_source_balances')->where('payment_id', $payment->id)->first())->toBe($source)
        ->and(DB::table('payments')->count())->toBe(1);
});
