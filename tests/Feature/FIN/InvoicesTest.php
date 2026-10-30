<?php

use App\Modules\IAM\Models\User;
use App\Modules\ORG\Enums\SequenceType;
use App\Support\BusinessDate;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use App\Support\RowHandle;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Database\Factories\OPS\CorrectionCaseFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\Numbering;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-049; ROUTE-041–ROUTE-043; ROUTE-241; ROUTE-242; ROUTE-245; WF-FIN-01; J-FIN-01; CM-23; CM-24; annex 1, 5, 13; criteria 1, 2, 4–8, 13): the issue records the agreed value and nothing else, within
// the project's cap; a draft from confirmed lines or value-only; a correction disposes every reduction or does not commit, with its typed basis, its entry-error evidence, a replacement that inherits the billing act
// and a standing write-off the Owner's alone. Reductions are synthetic until TASK-050–TASK-052 exist.

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

function invoiceAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function invoiceBalance(int $id): object
{
    return DB::table('invoice_balances')->where('invoice_id', $id)->first();
}

/** @param  array<string, mixed>  $overrides */
function saveInvoice(object $test, array $overrides = []): TestResponse
{
    return $test->actingAs($test->actor)->post('/keuangan/invoice', ['command_id' => (string) Str::uuid(), 'proyek' => $test->project->public_id,
        'isi' => ['jenis' => 'VALUE_ONLY', 'tanggal' => BusinessDate::today(), 'baris' => [['keterangan' => 'Termin pertama', 'nilai' => '1000000']]], ...$overrides]);
}

function issueInvoice(object $test, int $id, array $overrides = []): TestResponse
{
    $lock = (int) DB::table('invoices')->where('id', $id)->value('lock_version');

    return $test->actingAs($test->actor)->post('/keuangan/invoice/'.InvoiceWorld::publicId($id).'/terbitkan', ['command_id' => (string) Str::uuid(), 'lock_version' => $lock, 'tata' => 'DOC-06', 'tanggal' => BusinessDate::today(), ...$overrides]);
}

function correctInvoice(object $test, int $id, array $body, ?User $actor = null): TestResponse
{
    $lock = (int) DB::table('invoices')->where('id', $id)->value('lock_version');

    return $test->actingAs($actor ?? $test->actor)->post('/keuangan/invoice/'.InvoiceWorld::publicId($id).'/koreksi', ['command_id' => (string) Str::uuid(), 'lock_version' => $lock, 'alasan' => 'Salah catat nilai', 'tanggal' => BusinessDate::today(), ...$body]);
}

function invoiceContent(string $value): array
{
    return ['jenis' => 'VALUE_ONLY', 'tanggal' => BusinessDate::today(), 'baris' => [['keterangan' => 'Nilai yang benar', 'nilai' => $value]]];
}

function invoiceEvidence(object $test): string
{
    return EvidenceDocumentFactory::new()->createOne(['company_id' => $test->world->company->id, 'evidence_type' => 'OTHER'])->public_id;
}

function invoiceDifferences(): array
{
    return [...(new Reconciler)->differences(DB::connection(), Families::named('invoice_balances') ?? throw new RuntimeException('invoice guard')),
        ...(new Reconciler)->differences(DB::connection(), Families::named('project_commercial_balances') ?? throw new RuntimeException('commercial guard'))];
}

it('issues Rp1,000,000 as a sales value Belum Ditagihkan: no Cash-In, no active receivable, no stock, no other obligation (AC-10; annex 1; BR-FIN-02; BR-XD-04; criterion 1)', function () {
    saveInvoice($this)->assertRedirect();
    expect(invoiceAnswer()['kind'])->toBe('COMMITTED');
    $id = (int) DB::table('invoices')->where('project_id', $this->project->id)->value('id');
    expect(DB::table('invoices')->where('id', $id)->value('state'))->toBe('DRAFT');
    $before = ['payments' => DB::table('payments')->count(), 'applications' => DB::table('payment_applications')->count(), 'movements' => DB::table('stock_movements')->count(), 'acts' => DB::table('invoice_billing_acts')->count()];

    issueInvoice($this, $id)->assertRedirect('/keuangan/invoice/baru?'.http_build_query(['invoice' => InvoiceWorld::publicId($id)]));

    $balance = invoiceBalance($id);
    expect(invoiceAnswer()['kind'])->toBe('COMMITTED')
        ->and(invoiceAnswer()['text'])->toContain('Belum ada piutang aktif')
        ->and($balance->sales_value)->toBe('1000000.00')
        ->and($balance->gross_value)->toBe('1000000.00')
        ->and($balance->current_billing_act_id)->toBeNull()
        ->and(DB::table('project_commercial_balances')->where('project_id', $this->project->id)->value('invoiced_sales_value'))->toBe('1000000.00')
        ->and(['payments' => DB::table('payments')->count(), 'applications' => DB::table('payment_applications')->count(), 'movements' => DB::table('stock_movements')->count(), 'acts' => DB::table('invoice_billing_acts')->count()])->toBe($before)
        ->and(invoiceDifferences())->toBe([]);
    $number = DB::table('document_versions as v')->join('documents as d', 'd.id', '=', 'v.document_id')->where('d.invoice_id', $id)->value('v.formatted_number');
    expect($number)->toStartWith('INV/');
    $event = DB::table('audit_events')->where('action', 'FIN.InvoiceIssued')->sole();
    expect($event->actor_user_id)->toBe($this->world->actorId)->and($event->entity_type)->toBe('invoice');

    $this->actingAs($this->actor)->get('/keuangan/invoice/'.InvoiceWorld::publicId($id))->assertInertia(fn (AssertableInertia $page) => $page->component('FIN/Invoice', false)
        ->where('invoice.state', 'ISSUED_NOT_BILLED')->where('invoice.number', $number)->where('position.outstanding', '1000000.00')->where('position.billed', false));
});

it('keeps the output tax apart from the sales value, under the rule of the business date, and never as revenue (annex 13; BR-FIN-17; C-52; criterion 1)', function () {
    $code = $this->world->tax();
    $project = $this->world->project(['SERVICE'], '40');

    saveInvoice($this, ['proyek' => $project->public_id, 'isi' => ['jenis' => 'VALUE_ONLY', 'tanggal' => BusinessDate::today(), 'baris' => [['keterangan' => 'Pekerjaan', 'nilai' => '10000000']]]]);
    $id = (int) DB::table('invoices')->where('project_id', $project->id)->value('id');
    issueInvoice($this, $id);
    $component = DB::table('invoice_tax_components as t')->join('invoices as i', 'i.current_version_id', '=', 't.invoice_version_id')->where('i.id', $id)->sole();
    $balance = invoiceBalance($id);

    expect($component->amount)->toBe('1100000.00')->and($component->treatment)->toBe('OUTPUT_EXCLUDED_FROM_REVENUE')->and($component->treatment_rule_id)->not->toBeNull()
        ->and($balance->sales_value)->toBe('10000000.00')->and($balance->output_tax_value)->toBe('1100000.00')->and($balance->gross_value)->toBe('11100000.00')
        ->and(DB::table('project_commercial_balances')->where('project_id', $project->id)->value('invoiced_sales_value'))->toBe('10000000.00');

    $paper = json_decode((string) DB::table('document_versions as v')->join('documents as d', 'd.id', '=', 'v.document_id')->where('d.invoice_id', $id)->value('v.payload'), true);
    expect(collect($paper['paper']['totals'])->pluck('label')->last())->toBe('Total')->and(collect($paper['paper']['totals'])->pluck('label')->all())->toContain('Subtotal');
    unset($code);
});

it('takes the tax document\'s own amount where the actor states it (DATABASE §12)', function () {
    $code = $this->world->tax();
    saveInvoice($this, ['isi' => ['jenis' => 'VALUE_ONLY', 'tanggal' => BusinessDate::today(), 'baris' => [['keterangan' => 'Pekerjaan', 'nilai' => '1000000']], 'pajak' => [$code => '109999']]]);
    $id = (int) DB::table('invoices')->where('project_id', $this->project->id)->value('id');
    issueInvoice($this, $id);

    expect(invoiceBalance($id)->output_tax_value)->toBe('109999.00');
});

it('composes a draft from confirmed lines at their pinned price, exact on the pin\'s whole (WF-FIN-01; L-16; BR-SN-01)', function () {
    $line = InvoiceWorld::line($this->project);

    saveInvoice($this, ['isi' => ['jenis' => 'ITEMIZED', 'tanggal' => BusinessDate::today(), 'baris' => [['baris' => RowHandle::of('project_lines', $line), 'jumlah' => '4']]]]);
    $id = (int) DB::table('invoices')->where('project_id', $this->project->id)->value('id');
    $row = DB::table('invoice_version_lines as l')->join('invoices as i', 'i.current_version_id', '=', 'l.invoice_version_id')->where('i.id', $id)->sole();
    expect($row->line_sales_value)->toBe('1000000.00')->and($row->entered_qty)->toBe('4.000')->and($row->project_line_id)->toBe($line);

    $this->actingAs($this->actor)->get('/keuangan/invoice/baru?invoice='.InvoiceWorld::publicId($id))->assertInertia(fn (AssertableInertia $page) => $page->component('FIN/InvoiceComposer', false)
        ->where('draft.kind', 'ITEMIZED')->where('draft.sales', '1000000.00')->where('remaining', '2500000.00')->where('paper.number', null)->where('paper.draft', true));
    $this->actingAs($this->actor)->get('/keuangan/invoice/'.InvoiceWorld::publicId($id))->assertRedirect('/keuangan/invoice/baru?invoice='.InvoiceWorld::publicId($id));
});

it('refuses a draft of an unconfirmed project and of a closed one, and answers a future date at its field (L-16; L-11; C-50)', function () {
    $open = $this->world->project(['SERVICE'], '10', false);
    saveInvoice($this, ['proyek' => $open->public_id]);
    expect(invoiceAnswer()['code'])->toBe('L-16');

    saveInvoice($this, ['isi' => ['jenis' => 'VALUE_ONLY', 'tanggal' => '2999-01-01', 'baris' => [['keterangan' => 'Termin', 'nilai' => '1000']]]])->assertSessionHasErrors('tanggal');
    saveInvoice($this, ['isi' => ['jenis' => 'VALUE_ONLY', 'tanggal' => BusinessDate::today(), 'baris' => [['keterangan' => 'Termin', 'nilai' => '0']]]])->assertSessionHasErrors('isi.baris.0.nilai');

    DB::table('projects')->where('id', $this->project->id)->update(['state' => 'CANCELLED']);
    saveInvoice($this);
    expect(invoiceAnswer()['code'])->toBe('L-11')->and(DB::table('invoices')->count())->toBe(0);
});

it('refuses an issue above the cap with the remaining value; a value-only termin within it issues (C-31; L-16; criterion 2)', function () {
    $first = $this->world->issued($this->project, '2000000');
    $over = $this->world->draft($this->project, '600000');

    issueInvoice($this, $over);
    expect(invoiceAnswer()['kind'])->toBe('REJECTED')->and(invoiceAnswer()['code'])->toBe('C-31')->and(invoiceAnswer()['state']['remaining'])->toBe('500000.00')
        ->and(DB::table('invoices')->where('id', $over)->value('state'))->toBe('DRAFT');

    $within = $this->world->draft($this->project, '500000');
    issueInvoice($this, $within);
    expect(invoiceAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('project_commercial_balances')->where('project_id', $this->project->id)->value('invoiced_sales_value'))->toBe('2500000.00')
        ->and(invoiceDifferences())->toBe([]);
    unset($first);
});

it('issues above delivered value: flagged in QS-15, never blocked (PX-06; L-16; criterion 2)', function () {
    $id = $this->world->issued($this->project, '2500000');

    expect(DB::table('invoices')->where('id', $id)->value('state'))->toBe('ISSUED')
        ->and(DB::table('project_line_balances')->where('project_line_id', InvoiceWorld::line($this->project))->value('handed_over_qty_base'))->toBe('0.000');
});

it('answers a stale draft save CONFLICT with the input kept, and a second issue CONFLICT with the number (ST-01; M-38; BD-06; criterion 3)', function () {
    $id = $this->world->draft($this->project, '1000');
    $lock = (int) DB::table('invoices')->where('id', $id)->value('lock_version');
    $public = InvoiceWorld::publicId($id);
    saveInvoice($this, ['proyek' => null, 'invoice' => $public, 'lock_version' => $lock, 'isi' => invoiceContent('2000')]);
    expect(invoiceAnswer()['kind'])->toBe('COMMITTED');
    saveInvoice($this, ['proyek' => null, 'invoice' => $public, 'lock_version' => $lock, 'isi' => invoiceContent('3000')]);
    expect(invoiceAnswer()['kind'])->toBe('CONFLICT')->and(invoiceAnswer()['code'])->toBe('ST-01')
        ->and(DB::table('invoice_versions')->where('invoice_id', $id)->value('sales_value'))->toBe('2000.00');

    issueInvoice($this, $id);
    expect(invoiceAnswer()['kind'])->toBe('COMMITTED');
    $number = DB::table('document_versions as v')->join('documents as d', 'd.id', '=', 'v.document_id')->where('d.invoice_id', $id)->value('v.formatted_number');
    issueInvoice($this, $id, ['lock_version' => 0]);
    expect(invoiceAnswer()['kind'])->toBe('CONFLICT')->and(invoiceAnswer()['code'])->toBe('BD-06')->and(invoiceAnswer()['text'])->toContain($number);
});

it('answers MSG-17 for a backdated issue into a period with no counter, the input kept; the issue succeeds once the period is seeded (C-10; NM-03; UXS-29; criterion 4)', function () {
    $year = (int) substr(BusinessDate::today(), 0, 4);
    $id = $this->world->draft($this->project, '1000');
    $old = ($year - 1).'-06-15';

    issueInvoice($this, $id, ['tanggal' => $old]);
    expect(invoiceAnswer()['kind'])->toBe('REJECTED')->and(invoiceAnswer()['code'])->toBe('C-10')->and(DB::table('invoices')->where('id', $id)->value('state'))->toBe('DRAFT');

    Numbering::scheme($this->world->company, SequenceType::Doc06, ($year - 1).'-01-01', 'YEAR', 'LAMA/{tahun}/{nomor:4}');
    Numbering::seed($this->world->company, SequenceType::Doc06, (string) ($year - 1), 1);
    issueInvoice($this, $id, ['tanggal' => $old]);

    expect(invoiceAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('invoice_versions')->where('invoice_id', $id)->value('business_date'))->toBe($old);
});

it('voids a billed invoice only with every reduction disposed: Rp400,000 released to credit traceable to its payment (annex 5; C-16; BR-CR-03; BR-FIN-03; criterion 5)', function () {
    $id = $this->world->issued($this->project, '1000000');
    InvoiceWorld::bill($id, BusinessDate::today(), BusinessDate::today());
    $payment = InvoiceWorld::payment($id, '400000');
    $application = InvoiceWorld::apply($id, $payment, '400000');
    $case = CorrectionCaseFactory::new()->createOne(['company_id' => $this->world->company->id, 'project_id' => $this->project->id]);
    $void = ['aksi' => 'BATAL', 'dasar' => 'RETURN', 'kasus' => RowHandle::of('correction_cases', $case->id)];

    // The reduction not named: CONFLICT with the current list, nothing moved.
    correctInvoice($this, $id, $void);
    expect(invoiceAnswer()['kind'])->toBe('CONFLICT')->and(invoiceAnswer()['code'])->toBe('ST-07')->and(DB::table('invoices')->where('id', $id)->value('state'))->toBe('ISSUED');

    // Left on a voided invoice: the conservation CHECK, nothing moved.
    correctInvoice($this, $id, [...$void, 'disposisi' => ['aplikasi' => [['id' => RowHandle::of('payment_applications', $application), 'tindakan' => 'KEEP']]]]);
    expect(invoiceAnswer()['kind'])->not->toBe('COMMITTED')->and(DB::table('invoices')->where('id', $id)->value('state'))->toBe('ISSUED');

    correctInvoice($this, $id, [...$void, 'disposisi' => ['aplikasi' => [['id' => RowHandle::of('payment_applications', $application), 'tindakan' => 'RELEASE']]]]);
    expect(invoiceAnswer()['kind'])->toBe('COMMITTED');
    $balance = invoiceBalance($id);
    $row = DB::table('payment_applications')->where('id', $application)->sole();

    expect(DB::table('invoices')->where('id', $id)->value('state'))->toBe('VOIDED')
        ->and(DB::table('invoices')->where('id', $id)->value('void_correction_case_id'))->toBe($case->id)
        ->and($row->state)->toBe('RELEASED')->and($row->end_reason)->toBe('INVOICE_CORRECTED')
        ->and($balance->gross_value)->toBe('0.00')->and($balance->applied_amount)->toBe('0.00')->and($balance->outstanding)->toBe('0.00')
        ->and(DB::table('application_source_balances')->where('payment_id', $payment->id)->value('capacity_amount'))->toBe('400000.00')
        ->and(DB::table('project_commercial_balances')->where('project_id', $this->project->id)->value('invoiced_sales_value'))->toBe('0.00')
        ->and(DB::table('document_versions as v')->join('documents as d', 'd.id', '=', 'v.document_id')->where('d.invoice_id', $id)->value('v.state'))->toBe('VOIDED')
        ->and(DB::table('audit_events')->where('action', 'FIN.InvoiceVoided')->value('authority_class'))->toBe('ADM_PLUS')
        ->and(invoiceDifferences())->toBe([]);
});

it('replaces a billed invoice in one action, the replacement inheriting the act with the same dates, the application re-targeted (L-30; C-17; C-56; architecture challenge 5a; criterion 6)', function () {
    $id = $this->world->issued($this->project, '1000000');
    $act = InvoiceWorld::bill($id, BusinessDate::today(), '2099-12-31');
    $payment = InvoiceWorld::payment($id, '400000');
    $application = InvoiceWorld::apply($id, $payment, '400000');

    // Without its evidence, or without its replacement: refused.
    correctInvoice($this, $id, ['aksi' => 'BATAL', 'dasar' => 'ENTRY_ERROR_REPLACED', 'isi' => invoiceContent('900000'), 'disposisi' => ['aplikasi' => [['id' => RowHandle::of('payment_applications', $application), 'tindakan' => 'RETARGET']]]]);
    expect(invoiceAnswer()['code'])->toBe('CM-24');
    correctInvoice($this, $id, ['aksi' => 'BATAL', 'dasar' => 'ENTRY_ERROR_REPLACED', 'bukti' => invoiceEvidence($this), 'disposisi' => ['aplikasi' => [['id' => RowHandle::of('payment_applications', $application), 'tindakan' => 'RELEASE']]]]);
    expect(invoiceAnswer()['code'])->toBe('C-17');
    correctInvoice($this, $id, ['aksi' => 'BATAL', 'dasar' => 'UNBILLED_ENTRY_ERROR', 'disposisi' => ['aplikasi' => [['id' => RowHandle::of('payment_applications', $application), 'tindakan' => 'RELEASE']]]]);
    expect(invoiceAnswer()['code'])->toBe('C-56');

    correctInvoice($this, $id, ['aksi' => 'BATAL', 'dasar' => 'ENTRY_ERROR_REPLACED', 'bukti' => invoiceEvidence($this), 'isi' => invoiceContent('900000'), 'disposisi' => ['aplikasi' => [['id' => RowHandle::of('payment_applications', $application), 'tindakan' => 'RETARGET']]]]);
    expect(invoiceAnswer()['kind'])->toBe('COMMITTED');
    $replacement = DB::table('invoices')->where('replaces_invoice_id', $id)->sole();
    $inherited = DB::table('invoice_billing_acts')->where('invoice_id', $replacement->id)->sole();
    $balance = invoiceBalance($replacement->id);

    expect($replacement->state)->toBe('ISSUED')
        ->and($inherited->kind)->toBe('INHERITED')->and($inherited->inherited_from_act_id)->toBe($act)->and($inherited->billed_at)->toBe(BusinessDate::today())->and($inherited->due_date)->toBe('2099-12-31')
        ->and($balance->current_due_date)->toBe('2099-12-31')->and($balance->sales_value)->toBe('900000.00')->and($balance->applied_amount)->toBe('400000.00')->and($balance->outstanding)->toBe('500000.00')
        ->and(DB::table('payment_applications')->where('id', $application)->value('state'))->toBe('SUPERSEDED')
        ->and(DB::table('payment_applications')->where('supersedes_application_id', $application)->value('invoice_id'))->toBe($replacement->id)
        ->and(DB::table('application_source_balances')->where('payment_id', $payment->id)->value('invoice_applied_amount'))->toBe('400000.00')
        ->and(DB::table('evidence_links')->where('project_id', $this->project->id)->where('role', 'SUPPORTING')->exists())->toBeTrue()
        ->and(DB::table('project_commercial_balances')->where('project_id', $this->project->id)->value('invoiced_sales_value'))->toBe('900000.00')
        ->and(DB::table('document_versions as v')->join('documents as d', 'd.id', '=', 'v.document_id')->where('d.invoice_id', $replacement->id)->value('v.state'))->toBe('ISSUED')
        ->and(invoiceDifferences())->toBe([]);
});

it('checks the replacement against the cap on the net, the replaced value released (C-31; CM-24)', function () {
    $id = $this->world->issued($this->project, '2500000');
    InvoiceWorld::bill($id, BusinessDate::today(), BusinessDate::today());

    correctInvoice($this, $id, ['aksi' => 'BATAL', 'dasar' => 'ENTRY_ERROR_REPLACED', 'bukti' => invoiceEvidence($this), 'isi' => invoiceContent('2600000')]);
    expect(invoiceAnswer()['code'])->toBe('C-31')->and(invoiceAnswer()['state']['remaining'])->toBe('2500000.00');
    correctInvoice($this, $id, ['aksi' => 'BATAL', 'dasar' => 'ENTRY_ERROR_REPLACED', 'bukti' => invoiceEvidence($this), 'isi' => invoiceContent('2500000')]);
    expect(invoiceAnswer()['kind'])->toBe('COMMITTED');
});

it('revises down with its typed basis, keeping what still fits, never below what stays applied (CM-23; CM-24; C-16; criteria 5, 13)', function () {
    $id = $this->world->issued($this->project, '1000000');
    $payment = InvoiceWorld::payment($id, '400000');
    $application = InvoiceWorld::apply($id, $payment, '400000');
    $keep = ['aplikasi' => [['id' => RowHandle::of('payment_applications', $application), 'tindakan' => 'KEEP']]];

    // DIR-092 (2): no evidence, no entry-error revision.
    correctInvoice($this, $id, ['aksi' => 'REVISI', 'dasar' => 'ENTRY_ERROR', 'isi' => invoiceContent('800000'), 'disposisi' => $keep]);
    expect(invoiceAnswer()['code'])->toBe('CM-24')->and(DB::table('invoice_versions')->where('invoice_id', $id)->count())->toBe(1);

    // Not downward.
    correctInvoice($this, $id, ['aksi' => 'REVISI', 'dasar' => 'ENTRY_ERROR', 'bukti' => invoiceEvidence($this), 'isi' => invoiceContent('1000000'), 'disposisi' => $keep]);
    expect(invoiceAnswer()['code'])->toBe('CM-23');

    // Below what stays applied: the CHECK, nothing moved.
    correctInvoice($this, $id, ['aksi' => 'REVISI', 'dasar' => 'ENTRY_ERROR', 'bukti' => invoiceEvidence($this), 'isi' => invoiceContent('300000'), 'disposisi' => $keep]);
    expect(invoiceAnswer()['kind'])->not->toBe('COMMITTED')->and(invoiceBalance($id)->gross_value)->toBe('1000000.00');

    correctInvoice($this, $id, ['aksi' => 'REVISI', 'dasar' => 'ENTRY_ERROR', 'bukti' => invoiceEvidence($this), 'isi' => invoiceContent('800000'), 'disposisi' => $keep]);
    expect(invoiceAnswer()['kind'])->toBe('COMMITTED');
    $versions = DB::table('invoice_versions')->where('invoice_id', $id)->orderBy('version_no')->get();
    $issued = DB::table('document_versions as v')->join('documents as d', 'd.id', '=', 'v.document_id')->where('d.invoice_id', $id)->orderBy('v.version_no')->get(['v.state', 'v.formatted_number']);

    expect($versions->pluck('state')->all())->toBe(['SUPERSEDED', 'ISSUED'])->and($versions[1]->revision_basis)->toBe('ENTRY_ERROR')->and($versions[1]->revision_reason)->toBe('Salah catat nilai')
        ->and(invoiceBalance($id)->gross_value)->toBe('800000.00')->and(invoiceBalance($id)->applied_amount)->toBe('400000.00')->and(invoiceBalance($id)->outstanding)->toBe('400000.00')
        ->and($issued->pluck('state')->all())->toBe(['SUPERSEDED', 'ISSUED'])
        ->and(DB::table('project_commercial_balances')->where('project_id', $this->project->id)->value('invoiced_sales_value'))->toBe('800000.00')
        ->and(invoiceDifferences())->toBe([]);
});

it('voids an unbilled invoice with its reason, no evidence asked; the same void of a billed one is refused (CM-23; C-56)', function () {
    $id = $this->world->issued($this->project, '1000000');
    correctInvoice($this, $id, ['aksi' => 'BATAL', 'dasar' => 'UNBILLED_ENTRY_ERROR']);

    expect(invoiceAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('invoices')->where('id', $id)->value('void_basis'))->toBe('UNBILLED_ENTRY_ERROR')
        ->and(DB::table('project_commercial_balances')->where('project_id', $this->project->id)->value('invoiced_sales_value'))->toBe('0.00');
});

it('cites a return\'s case only when the correction runs: nothing in the invoice changes when the goods come back until it does (BR-XD-01; CM-12; architecture challenge 4a; criterion 7)', function () {
    $id = $this->world->issued($this->project, '1000000');
    $case = CorrectionCaseFactory::new()->createOne(['company_id' => $this->world->company->id, 'project_id' => $this->project->id]);

    expect(invoiceBalance($id)->gross_value)->toBe('1000000.00')->and(DB::table('invoice_versions')->where('invoice_id', $id)->count())->toBe(1);

    correctInvoice($this, $id, ['aksi' => 'REVISI', 'dasar' => 'RETURN', 'isi' => invoiceContent('700000')]);
    expect(invoiceAnswer()['code'])->toBe('C-56');
    correctInvoice($this, $id, ['aksi' => 'REVISI', 'dasar' => 'RETURN', 'kasus' => RowHandle::of('correction_cases', $case->id), 'isi' => invoiceContent('700000')]);
    expect(invoiceAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('invoice_versions')->where('invoice_id', $id)->where('state', 'ISSUED')->value('basis_correction_case_id'))->toBe($case->id);
});

it('leaves a settlement the replacement cannot carry as a residual on the case, blocking its closure; its evidence kept (L-06; CM-24; C-22; C-46; criterion 8)', function () {
    $code = $this->world->tax();
    $type = (int) DB::table('tax_types')->where('code', $code)->value('id');
    $project = $this->world->project(['SERVICE'], '40');
    $id = $this->world->issued($project, '1000000');
    InvoiceWorld::bill($id, BusinessDate::today(), BusinessDate::today());
    $payment = InvoiceWorld::payment($id, '500000', '110000');
    $settlement = InvoiceWorld::settle($id, $payment, '110000', 'TAX', 'COLLECTED', $type);
    $content = ['jenis' => 'VALUE_ONLY', 'tanggal' => BusinessDate::today(), 'baris' => [['keterangan' => 'Nilai benar', 'nilai' => '100000']]];

    // The replacement's tax (11,000) cannot carry 110,000: refused as a re-record.
    correctInvoice($this, $id, ['aksi' => 'BATAL', 'dasar' => 'ENTRY_ERROR_REPLACED', 'bukti' => invoiceEvidence($this), 'isi' => $content, 'disposisi' => ['potongan' => [['id' => RowHandle::of('deduction_settlements', $settlement), 'tindakan' => 'RERECORD']]]]);
    expect(invoiceAnswer()['code'])->toBe('C-22')->and(DB::table('deduction_settlements')->where('id', $settlement)->value('state'))->toBe('ACTIVE');

    correctInvoice($this, $id, ['aksi' => 'BATAL', 'dasar' => 'ENTRY_ERROR_REPLACED', 'bukti' => invoiceEvidence($this), 'isi' => $content, 'disposisi' => ['potongan' => [['id' => RowHandle::of('deduction_settlements', $settlement), 'tindakan' => 'RESIDUAL']]]]);
    expect(invoiceAnswer()['kind'])->toBe('COMMITTED');
    $row = DB::table('deduction_settlements')->where('id', $settlement)->sole();
    $case = DB::table('correction_cases')->where('id', $row->correction_case_id)->sole();

    expect($row->state)->toBe('RESIDUAL')->and($case->residual_settlement_amount)->toBe('110000.00')->and($case->state)->toBe('OPEN')
        ->and(invoiceBalance($id)->tax_settled_amount)->toBe('0.00')
        ->and(DB::table('application_source_balances')->where('payment_id', $payment->id)->value('settled_amount'))->toBe('0.00')
        ->and(DB::table('evidence_balances')->where('evidence_document_id', $row->evidence_document_id)->value('cited_amount'))->toBe('110000.00');
    expect(fn () => DB::table('correction_cases')->where('id', $case->id)->update(['state' => 'CLOSED', 'closed_at' => now(), 'closed_by' => $this->world->actorId, 'resolution' => 'Selesai']))->toThrow(QueryException::class);
});

it('re-records a settlement on the replacement, the old one contra\'d and the evidence net of the move (L-28; DATABASE §11.4; BR-FIN-16)', function () {
    $id = $this->world->issued($this->project, '1000000');
    InvoiceWorld::bill($id, BusinessDate::today(), BusinessDate::today());
    $payment = InvoiceWorld::payment($id, '500000', '15000');
    $settlement = InvoiceWorld::settle($id, $payment, '15000');
    $evidence = (int) DB::table('deduction_settlements')->where('id', $settlement)->value('evidence_document_id');

    correctInvoice($this, $id, ['aksi' => 'BATAL', 'dasar' => 'ENTRY_ERROR_REPLACED', 'bukti' => invoiceEvidence($this), 'isi' => invoiceContent('900000'), 'disposisi' => ['potongan' => [['id' => RowHandle::of('deduction_settlements', $settlement), 'tindakan' => 'RERECORD']]]]);
    expect(invoiceAnswer()['kind'])->toBe('COMMITTED');
    $replacement = (int) DB::table('invoices')->where('replaces_invoice_id', $id)->value('id');

    expect(DB::table('deduction_settlements')->where('id', $settlement)->value('state'))->toBe('CONTRAED')
        ->and(DB::table('deduction_settlements')->where('invoice_id', $replacement)->where('state', 'ACTIVE')->value('amount'))->toBe('15000.00')
        ->and(DB::table('financial_contras')->where('deduction_settlement_id', $settlement)->value('amount'))->toBe('15000.00')
        ->and(invoiceBalance($replacement)->fee_settled_amount)->toBe('15000.00')->and(invoiceBalance($id)->fee_settled_amount)->toBe('0.00')
        ->and(DB::table('application_source_balances')->where('payment_id', $payment->id)->value('settled_amount'))->toBe('15000.00')
        ->and(DB::table('evidence_balances')->where('evidence_document_id', $evidence)->value('cited_amount'))->toBe('15000.00')
        ->and(invoiceDifferences())->toBe([]);
});

it('refuses an Admin\'s correction over a standing write-off; the Owner supersedes it in the same command (OD-07; CM-24; AZ-10; criterion 8)', function () {
    $id = $this->world->issued($this->project, '1000000');
    InvoiceWorld::bill($id, BusinessDate::today(), BusinessDate::today());
    $owner = Authorization::owner();
    $writeoff = InvoiceWorld::writeOff($id, '100000', $owner->id);
    $case = CorrectionCaseFactory::new()->createOne(['company_id' => $this->world->company->id, 'project_id' => $this->project->id]);
    $body = ['aksi' => 'BATAL', 'dasar' => 'RETURN', 'kasus' => RowHandle::of('correction_cases', $case->id), 'disposisi' => ['hapus' => [['id' => RowHandle::of('receivable_writeoffs', $writeoff), 'tindakan' => 'SUPERSEDE']]]];

    correctInvoice($this, $id, $body);
    expect(invoiceAnswer()['kind'])->toBe('REJECTED')->and(invoiceAnswer()['code'])->toBe('OD-07')->and(DB::table('invoices')->where('id', $id)->value('state'))->toBe('ISSUED');

    correctInvoice($this, $id, $body, $owner);
    expect(invoiceAnswer()['kind'])->toBe('COMMITTED')
        ->and(DB::table('financial_contras')->where('receivable_writeoff_id', $writeoff)->value('amount'))->toBe('100000.00')
        ->and(DB::table('money_fact_balances')->where('receivable_writeoff_id', $writeoff)->value('contra_amount'))->toBe('100000.00')
        ->and(invoiceBalance($id)->written_off_amount)->toBe('0.00')
        ->and(DB::table('audit_events')->where('action', 'FIN.InvoiceVoided')->value('authority_class'))->toBe('OWNER_ONLY');
});

it('lists a branch\'s invoices with the exact number lookup, and shows a voided one as void (ROUTE-041; SCR-38; CS-07)', function () {
    $id = $this->world->issued($this->project, '1000000');
    $number = (string) DB::table('document_versions as v')->join('documents as d', 'd.id', '=', 'v.document_id')->where('d.invoice_id', $id)->value('v.formatted_number');
    $this->world->draft($this->project, '1000');

    $this->actingAs($this->actor)->get('/keuangan/invoice')->assertInertia(fn (AssertableInertia $page) => $page->component('FIN/Invoices', false)->has('rows', 2)->where('rows.0.state', 'DRAFT')->where('rows.1.number', $number)->where('rows.1.state', 'ISSUED_NOT_BILLED')->where('can.create', true));
    $this->actingAs($this->actor)->get('/keuangan/invoice?nomor='.urlencode($number))->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1)->where('rows.0.number', $number));
    $this->actingAs($this->actor)->get('/keuangan/invoice?nomor=TIDAK-ADA')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0));
});

it('leads the journey\'s Invoice stage to PG-042 for a holder of invoice.issue (J-FIN-01; PL-3)', function () {
    $line = InvoiceWorld::line($this->project);
    DB::table('project_line_balances')->where('project_line_id', $line)->update(['handed_over_qty_base' => '10.000']);

    $this->actingAs($this->actor)->get("/proyek/{$this->project->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('journey.current', 'invoice')->where('journey.next.href', '/keuangan/invoice/baru?'.http_build_query(['proyek' => $this->project->public_id])));
});
