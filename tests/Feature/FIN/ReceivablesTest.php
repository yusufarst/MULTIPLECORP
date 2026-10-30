<?php

use App\Modules\DOC\Services\MustacheTemplates;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceEvidence;
use App\Modules\FIN\Services\ReceivablePredicate;
use App\Modules\IAM\Models\User;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PTY\Models\ClientOrganization;
use App\Support\BusinessDate;
use App\Support\Finance\AgingCalculator;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use App\Support\RowHandle;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Database\Factories\OPS\CorrectionCaseFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-050; ROUTE-044; ROUTE-243; ROUTE-244; ROUTE-246–ROUTE-248; AX-17; AX-24; NX-09; WF-FIN-02; WF-FIN-05; J-FIN-02; J-FIN-05; CM-25; C-15; C-41; C-42; L-17; L-30; criteria 1–8, 10, 11): billing activates only the unpaid remainder and records no amount;
// one root act, a correction only with entry-error evidence in a linear chain, the superseded dates visible; ageing in its buckets; a hold keeps the receivable active and blocks completion, lapsing at zero; the Owner's write-off within the outstanding with credit
// applied first or a reason, its supersession applying the recovered money in one command; DOC-13 with no cash effect; PG-044's subtotal per company, never a total across them.

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
    $this->today = BusinessDate::today();
});

afterEach(fn () => @unlink($this->hotFile));

function receivableAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function receivableBalance(int $id): object
{
    return DB::table('invoice_balances')->where('invoice_id', $id)->sole();
}

function receivableDifferences(): array
{
    return (new Reconciler)->differences(DB::connection(), Families::named('invoice_balances') ?? throw new RuntimeException('invoice guard'));
}

/** @param  array<string, mixed>  $body */
function receivablePost(object $test, int $id, string $path, array $body, ?User $actor = null): TestResponse
{
    return $test->actingAs($actor ?? $test->actor)->post('/keuangan/invoice/'.InvoiceWorld::publicId($id)."/{$path}", ['command_id' => (string) Str::uuid(), ...$body]);
}

function billReceivable(object $test, int $id, ?string $due = '2099-12-31', array $overrides = []): TestResponse
{
    return receivablePost($test, $id, 'tagihkan', ['tanggal' => BusinessDate::today(), 'jatuh_tempo' => $due, ...$overrides]);
}

function writeOffReceivable(object $test, int $id, string $amount, array $overrides = []): TestResponse
{
    return receivablePost($test, $id, 'hapuskan', ['jumlah' => $amount, 'alasan' => 'Klien tidak dapat ditagih', 'tanggal' => BusinessDate::today(), ...$overrides], $test->owner);
}

it('bills Rp1,000,000 with its dates and activates it, recording no amount and no Cash-In (AC-10; annex 1; DATABASE §11.2; criterion 1)', function () {
    $id = $this->world->issued($this->project, '1000000');
    $this->actingAs($this->actor)->get('/keuangan/invoice/'.InvoiceWorld::publicId($id))->assertInertia(fn (AssertableInertia $page) => $page->where('invoice.state', 'ISSUED_NOT_BILLED')->where('can.bill', true)->where('billing.current', null));

    billReceivable($this, $id);
    $balance = receivableBalance($id);
    $act = DB::table('invoice_billing_acts')->where('invoice_id', $id)->sole();

    expect(receivableAnswer()['kind'])->toBe('COMMITTED')
        ->and($act->kind)->toBe('BILLING')->and($act->billed_at)->toBe($this->today)->and($act->due_date)->toBe('2099-12-31')->and($act->supersedes_act_id)->toBeNull()
        ->and(array_keys((array) $act))->not->toContain('amount')
        ->and($balance->current_billing_act_id)->toBe($act->id)->and($balance->current_due_date)->toBe('2099-12-31')->and($balance->outstanding)->toBe('1000000.00')
        ->and(DB::table('payments')->count())->toBe(0)
        ->and(DB::table('audit_events')->where('action', 'FIN.InvoiceBilled')->count())->toBe(1)
        ->and(receivableDifferences())->toBe([]);

    $this->actingAs($this->actor)->get('/keuangan/invoice/'.InvoiceWorld::publicId($id))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('invoice.state', 'BILLED_OUTSTANDING')->where('can.bill', false)->where('billing.current.due_date', '2099-12-31')->where('billing.bucket', AgingCalculator::CURRENT));
});

it('activates only the unpaid remainder after an advance, no second Cash-In (annex 2; BR-FIN-04; criterion 1)', function () {
    $id = $this->world->issued($this->project, '1000000');
    $payment = InvoiceWorld::payment($id, '400000');
    InvoiceWorld::apply($id, $payment, '400000');
    $payments = DB::table('payments')->count();

    billReceivable($this, $id);

    expect(receivableAnswer()['kind'])->toBe('COMMITTED')->and(receivableBalance($id)->outstanding)->toBe('600000.00')->and(DB::table('payments')->count())->toBe($payments)
        ->and(json_decode((string) DB::table('audit_events')->where('action', 'FIN.InvoiceBilled')->value('after'), true)['activated'] ?? null)->toBe('600000.00');
});

it('answers a second billing CONFLICT with the dates it holds, a draft and a void refused, a date at its field (BD-07; C-41; C-50; ST-02; criterion 2)', function () {
    $id = $this->world->issued($this->project, '1000000');
    billReceivable($this, $id);

    billReceivable($this, $id, '2099-01-01');
    expect(receivableAnswer()['kind'])->toBe('CONFLICT')->and(receivableAnswer()['code'])->toBe('BD-07')->and(receivableAnswer()['state']['due_date'])->toBe('2099-12-31')
        ->and(DB::table('invoice_billing_acts')->where('invoice_id', $id)->count())->toBe(1);

    $draft = $this->world->draft($this->project, '1000');
    billReceivable($this, $draft);
    expect(receivableAnswer()['code'])->toBe('BR-FIN-02');

    $other = $this->world->issued($this->project, '1000');
    billReceivable($this, $other, '2099-12-31', ['tanggal' => '2999-01-01', 'jatuh_tempo' => '2999-01-02'])->assertSessionHasErrors('tanggal');
    billReceivable($this, $other, CarbonImmutable::parse($this->today)->subDay()->toDateString())->assertSessionHasErrors('jatuh_tempo');
    billReceivable($this, $other, null)->assertSessionHasErrors('jatuh_tempo');
    billReceivable($this, $other, '2099-12-31', ['tanggal' => CarbonImmutable::parse($this->today)->subDay()->toDateString(), 'tanggal_otomatis' => true]);
    expect(receivableAnswer()['code'])->toBe('ST-08')->and(receivableBalance($other)->current_billing_act_id)->toBeNull();

    $this->actingAs($this->actor)->post('/keuangan/invoice/'.InvoiceWorld::publicId($other).'/koreksi', ['command_id' => (string) Str::uuid(), 'aksi' => 'BATAL', 'dasar' => 'UNBILLED_ENTRY_ERROR', 'lock_version' => DB::table('invoices')->where('id', $other)->value('lock_version'), 'alasan' => 'Salah catat', 'tanggal' => $this->today]);
    billReceivable($this, $other);
    expect(receivableAnswer()['kind'])->toBe('CONFLICT')->and(receivableAnswer()['code'])->toBe('ST-02');
});

it('corrects a billing only with evidence linked to the invoice\'s records: a linear chain, the old dates visible, ageing following (CM-25; C-41; criterion 2)', function () {
    $id = $this->world->issued($this->project, '1000000');
    $first = CarbonImmutable::parse($this->today)->subDays(40)->toDateString();
    billReceivable($this, $id, '2099-12-31', ['tanggal' => $this->today]);
    $root = (int) DB::table('invoice_billing_acts')->where('invoice_id', $id)->value('id');
    $body = ['tanggal' => $first, 'jatuh_tempo' => $first, 'alasan' => 'Tanggal salah catat'];

    receivablePost($this, $id, 'koreksi-tagihan', $body)->assertSessionHasErrors('bukti');
    $unlinked = EvidenceDocumentFactory::new()->createOne(['company_id' => $this->world->company->id, 'evidence_type' => 'OTHER']);
    receivablePost($this, $id, 'koreksi-tagihan', [...$body, 'bukti' => $unlinked->public_id])->assertSessionHasErrors('bukti');
    expect(DB::table('invoice_billing_acts')->where('invoice_id', $id)->count())->toBe(1);

    $evidence = InvoiceWorld::linkedEvidence($this->world->company->id, $this->project->id);
    receivablePost($this, $id, 'koreksi-tagihan', [...$body, 'bukti' => $evidence->public_id]);
    expect(receivableAnswer()['kind'])->toBe('COMMITTED');
    $current = DB::table('invoice_billing_acts')->where('invoice_id', $id)->orderByDesc('id')->first();
    expect($current->kind)->toBe('CORRECTION')->and($current->supersedes_act_id)->toBe($root)->and($current->reason)->toBe('Tanggal salah catat')
        ->and(receivableBalance($id)->current_billing_act_id)->toBe($current->id)->and(receivableBalance($id)->current_due_date)->toBe($first)
        ->and(DB::table('audit_events')->where('action', 'FIN.BillingCorrected')->value('authority_class'))->toBe('ADM_PLUS');

    $this->actingAs($this->actor)->get('/keuangan/invoice/'.InvoiceWorld::publicId($id))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('billing.current.due_date', $first)->where('billing.history.0.due_date', '2099-12-31')->where('billing.days_past', 40)->where('billing.bucket', AgingCalculator::DAYS_31_60)->where('invoice.state', 'PAST_DUE_DATE'));

    // The same dates again change nothing; an unbilled invoice has no act to correct.
    receivablePost($this, $id, 'koreksi-tagihan', [...$body, 'bukti' => $evidence->public_id]);
    expect(receivableAnswer()['code'])->toBe('CM-25')->and(receivableAnswer()['state']['reason'])->toBe('unchanged');
    $unbilled = $this->world->issued($this->project, '1000');
    receivablePost($this, $unbilled, 'koreksi-tagihan', [...$body, 'bukti' => $evidence->public_id]);
    expect(receivableAnswer()['state']['reason'])->toBe('not_billed');
});

it('hands a replacement the current act after a correction, its ageing continuing from that date (L-30; C-17; architecture challenge 5a; criterion 3)', function () {
    $id = $this->world->issued($this->project, '1000000');
    billReceivable($this, $id);
    $due = CarbonImmutable::parse($this->today)->subDays(10)->toDateString();
    $evidence = InvoiceWorld::linkedEvidence($this->world->company->id, $this->project->id);
    receivablePost($this, $id, 'koreksi-tagihan', ['tanggal' => $due, 'jatuh_tempo' => $due, 'alasan' => 'Tanggal salah catat', 'bukti' => $evidence->public_id]);
    $current = (int) DB::table('invoice_billing_acts')->where('invoice_id', $id)->orderByDesc('id')->value('id');

    $this->actingAs($this->actor)->post('/keuangan/invoice/'.InvoiceWorld::publicId($id).'/koreksi', ['command_id' => (string) Str::uuid(), 'aksi' => 'BATAL', 'dasar' => 'ENTRY_ERROR_REPLACED', 'bukti' => $evidence->public_id, 'lock_version' => DB::table('invoices')->where('id', $id)->value('lock_version'), 'alasan' => 'Nilai salah catat', 'tanggal' => $this->today,
        'isi' => ['jenis' => 'VALUE_ONLY', 'tanggal' => $this->today, 'baris' => [['keterangan' => 'Nilai benar', 'nilai' => '900000']]]]);
    expect(receivableAnswer()['kind'])->toBe('COMMITTED');
    $replacement = DB::table('invoices')->where('replaces_invoice_id', $id)->sole();
    $act = DB::table('invoice_billing_acts')->where('invoice_id', $replacement->id)->sole();

    expect($act->kind)->toBe('INHERITED')->and($act->inherited_from_act_id)->toBe($current)->and($act->due_date)->toBe($due);
    $this->actingAs($this->actor)->get("/keuangan/invoice/{$replacement->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('billing.days_past', 10)->where('billing.bucket', AgingCalculator::DAYS_1_30)->where('invoice.inherited', true));
});

it('ages PG-044\'s receivables at the WIB boundary, written-off amounts leaving, a disputed one keeping its indicator and age (BR-FIN-15; CALC-07; FX-08; criterion 4)', function () {
    $past = fn (int $days): string => CarbonImmutable::parse($this->today)->subDays($days)->toDateString();
    $current = $this->world->issued($this->project, '100000');
    $thirty = $this->world->issued($this->project, '200000');
    $sixty = $this->world->issued($this->project, '300000');
    $ninety = $this->world->issued($this->project, '400000');
    $older = $this->world->issued($this->project, '500000');

    foreach ([[$current, 0], [$thirty, 30], [$sixty, 60], [$ninety, 90], [$older, 91]] as [$id, $days]) {
        InvoiceWorld::bill($id, $past(max($days, 0)), $past($days));
    }

    receivablePost($this, $older, 'sengketa', ['aksi' => 'TANDAI', 'alasan' => 'Klien membantah']);
    writeOffReceivable($this, $ninety, '150000');
    expect(receivableAnswer()['kind'])->toBe('COMMITTED');

    $this->actingAs($this->actor)->get('/keuangan/piutang')->assertInertia(fn (AssertableInertia $page) => $page->component('FIN/Receivables', false)->where('tab', 'aktif')->has('rows', 5)
        ->where('rows.0.id', InvoiceWorld::publicId($older))->where('rows.0.held', true)->where('rows.0.state', 'DISPUTE_HOLD')->where('rows.0.days_past', 91)->where('rows.0.bucket', AgingCalculator::OVER_90)
        ->where('rows.1.bucket', AgingCalculator::DAYS_61_90)->where('rows.1.outstanding', '250000.00')
        ->where('rows.2.bucket', AgingCalculator::DAYS_31_60)->where('rows.3.bucket', AgingCalculator::DAYS_1_30)->where('rows.4.bucket', AgingCalculator::CURRENT)
        ->has('subtotals', 1)->where('subtotals.0.amount', '1350000.00')->where('subtotals.0.count', 5)
        ->where('subtotals.0.buckets.'.AgingCalculator::OVER_90, '500000.00')->where('subtotals.0.buckets.'.AgingCalculator::DAYS_61_90, '250000.00')->where('subtotals.0.buckets.'.AgingCalculator::CURRENT, '100000.00'));
});

it('sets a hold with its reason, the receivable active and ageing, completion blocked; one uncleared hold; clearing needs its reason (BR-FIN-08; C-42; L-12; criterion 5)', function () {
    $id = $this->world->issued($this->project, '1000000');
    receivablePost($this, $id, 'sengketa', ['aksi' => 'TANDAI', 'alasan' => 'Belum ditagihkan']);
    expect(receivableAnswer()['code'])->toBe('BR-FIN-08');

    billReceivable($this, $id);
    receivablePost($this, $id, 'sengketa', ['aksi' => 'TANDAI'])->assertSessionHasErrors('alasan');
    receivablePost($this, $id, 'sengketa', ['aksi' => 'TANDAI', 'alasan' => 'Klien membantah jumlahnya']);
    expect(receivableAnswer()['kind'])->toBe('COMMITTED')->and(receivableBalance($id)->outstanding)->toBe('1000000.00');

    $state = app(ReceivablePredicate::class)->states([$this->project->id], true)[$this->project->id][Predicates::RECEIVABLE];
    expect($state->met())->toBeFalse()->and($state->items[0]->key)->toBe('projects.item.receivables_disputed')->and($state->items[0]->blocker)->toBeTrue();
    $this->actingAs($this->actor)->get('/keuangan/invoice/'.InvoiceWorld::publicId($id))->assertInertia(fn (AssertableInertia $page) => $page->where('invoice.state', 'DISPUTE_HOLD')->where('hold.reason', 'Klien membantah jumlahnya')->where('hold.lapsed', false));
    $this->actingAs($this->actor)->get('/keuangan/piutang?tab=sengketa')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1)->where('rows.0.held', true));

    receivablePost($this, $id, 'sengketa', ['aksi' => 'TANDAI', 'alasan' => 'Sekali lagi']);
    expect(receivableAnswer()['code'])->toBe('C-42')->and(DB::table('receivable_dispute_holds')->where('invoice_id', $id)->count())->toBe(1);

    receivablePost($this, $id, 'sengketa', ['aksi' => 'CABUT'])->assertSessionHasErrors('alasan');
    receivablePost($this, $id, 'sengketa', ['aksi' => 'CABUT', 'alasan' => 'Klien sudah setuju']);
    $hold = DB::table('receivable_dispute_holds')->where('invoice_id', $id)->sole();
    expect(receivableAnswer()['kind'])->toBe('COMMITTED')->and($hold->clear_reason)->toBe('Klien sudah setuju')->and($hold->cleared_by)->toBe($this->actor->id);

    receivablePost($this, $id, 'sengketa', ['aksi' => 'CABUT', 'alasan' => 'Lagi']);
    expect(receivableAnswer()['code'])->toBe('C-42')->and(receivableAnswer()['state']['reason'])->toBe('no_hold');
    $this->actingAs($this->actor)->get('/keuangan/piutang?tab=sengketa')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0));
});

it('lets a hold lapse at zero outstanding: no longer blocking, no longer listed (L-17; criterion 5)', function () {
    $id = $this->world->issued($this->project, '1000000');
    billReceivable($this, $id);
    receivablePost($this, $id, 'sengketa', ['aksi' => 'TANDAI', 'alasan' => 'Klien membantah']);
    writeOffReceivable($this, $id, '1000000');
    expect(receivableAnswer()['kind'])->toBe('COMMITTED');

    expect(app(ReceivablePredicate::class)->states([$this->project->id], true)[$this->project->id][Predicates::RECEIVABLE]->met())->toBeTrue();
    $this->actingAs($this->actor)->get('/keuangan/piutang?tab=sengketa')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0));
    $this->actingAs($this->actor)->get('/keuangan/invoice/'.InvoiceWorld::publicId($id))->assertInertia(fn (AssertableInertia $page) => $page->where('hold.lapsed', true)->where('invoice.state', 'WRITTEN_OFF')->where('can.dispute', true));
});

it('writes off at most the outstanding with its reason and before/after: disposed history, no Cash-In, no profit (AX-24; DIR-019; BR-FIN-08; C-15; criterion 6)', function () {
    $id = $this->world->issued($this->project, '1000000');
    writeOffReceivable($this, $id, '100000');
    expect(receivableAnswer()['code'])->toBe('BR-FIN-08');

    billReceivable($this, $id);
    writeOffReceivable($this, $id, '1000001');
    expect(receivableAnswer()['kind'])->toBe('REJECTED')->and(receivableAnswer()['code'])->toBe('C-15')->and(receivableAnswer()['state']['outstanding'])->toBe('1000000.00')->and(receivableAnswer()['text'])->toContain('Rp1.000.000');

    writeOffReceivable($this, $id, '300000', ['alasan' => null])->assertSessionHasErrors('alasan');
    writeOffReceivable($this, $id, '300000');
    $row = DB::table('receivable_writeoffs')->where('invoice_id', $id)->sole();

    expect(receivableAnswer()['kind'])->toBe('COMMITTED')
        ->and($row->amount)->toBe('300000.00')->and($row->outstanding_before)->toBe('1000000.00')->and($row->outstanding_after)->toBe('700000.00')->and($row->decided_by)->toBe($this->owner->id)
        ->and(receivableBalance($id)->outstanding)->toBe('700000.00')->and(receivableBalance($id)->written_off_amount)->toBe('300000.00')
        ->and(DB::table('money_fact_balances')->where('receivable_writeoff_id', $row->id)->value('amount'))->toBe('300000.00')
        ->and(DB::table('payments')->count())->toBe(0)
        ->and(DB::table('audit_events')->where('action', 'FIN.ReceivableWrittenOff')->value('authority_class'))->toBe('OWNER_ONLY')
        ->and(receivableDifferences())->toBe([]);

    $this->actingAs($this->owner)->get('/keuangan/invoice/'.InvoiceWorld::publicId($id))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('writeoffs.0.amount', '300000.00')->where('writeoffs.0.standing', '300000.00')->where('writeoffs.0.before', '1000000.00')->where('writeoffs.0.after', '700000.00')->where('can.write_off', true));
});

it('asks for the same client and company\'s credit to be applied first or a reason recorded; another client\'s credit asks nothing (L-17; BR-FIN-05; criterion 6)', function () {
    $id = $this->world->issued($this->project, '1000000');
    billReceivable($this, $id);
    InvoiceWorld::payment($id, '250000');

    $this->actingAs($this->owner)->get('/keuangan/invoice/'.InvoiceWorld::publicId($id))->assertInertia(fn (AssertableInertia $page) => $page->where('credit', '250000.00'));
    $this->actingAs($this->actor)->get('/keuangan/invoice/'.InvoiceWorld::publicId($id))->assertInertia(fn (AssertableInertia $page) => $page->where('credit', null)->where('can.write_off', false));

    writeOffReceivable($this, $id, '100000');
    expect(receivableAnswer()['kind'])->toBe('REJECTED')->and(receivableAnswer()['code'])->toBe('L-17')->and(receivableAnswer()['state']['credit'])->toBe('250000.00')
        ->and(DB::table('receivable_writeoffs')->where('invoice_id', $id)->count())->toBe(0);

    writeOffReceivable($this, $id, '100000', ['alasan_kredit' => 'Kredit itu untuk proyek lain']);
    expect(receivableAnswer()['kind'])->toBe('COMMITTED')
        ->and(json_decode((string) DB::table('audit_events')->where('action', 'FIN.ReceivableWrittenOff')->value('after'), true)['credit_not_applied_because'] ?? null)->toBe('Kredit itu untuk proyek lain');
});

it('applies money recovered after a write-off in the same command as the supersession, the original staying as history (AX-24; L-17; DATABASE §11.4; criterion 7)', function () {
    $id = $this->world->issued($this->project, '1000000');
    billReceivable($this, $id);
    writeOffReceivable($this, $id, '500000');
    $writeoff = DB::table('receivable_writeoffs')->where('invoice_id', $id)->sole();
    $payment = InvoiceWorld::recovery($id, '300000');
    $body = ['penghapusan' => RowHandle::of('receivable_writeoffs', $writeoff->id), 'pembayaran' => $payment->public_id, 'alasan' => 'Klien membayar kembali', 'tanggal' => $this->today];

    $this->actingAs($this->owner)->get('/keuangan/invoice/'.InvoiceWorld::publicId($id))->assertInertia(fn (AssertableInertia $page) => $page->where('recoveries.0.id', $payment->public_id)->where('recoveries.0.amount', '300000.00')->where('can.recover', true));
    $this->actingAs($this->actor)->get('/keuangan/invoice/'.InvoiceWorld::publicId($id))->assertInertia(fn (AssertableInertia $page) => $page->has('recoveries', 0)->where('can.recover', false));

    receivablePost($this, $id, 'cabut-penghapusan', [...$body, 'jumlah' => '300001'], $this->owner);
    expect(receivableAnswer()['code'])->toBe('C-15')->and(receivableAnswer()['state']['capacity'])->toBe('300000.00');

    receivablePost($this, $id, 'cabut-penghapusan', [...$body, 'jumlah' => '300000'], $this->owner);
    expect(receivableAnswer()['kind'])->toBe('COMMITTED');
    $balance = receivableBalance($id);
    $contra = DB::table('financial_contras')->where('receivable_writeoff_id', $writeoff->id)->sole();
    $application = DB::table('payment_applications')->where('payment_id', $payment->id)->where('invoice_id', $id)->sole();

    expect($contra->amount)->toBe('300000.00')->and($application->amount)->toBe('300000.00')->and($application->state)->toBe('ACTIVE')
        ->and($contra->command_id)->toBe($application->command_id)
        ->and($balance->written_off_amount)->toBe('200000.00')->and($balance->applied_amount)->toBe('300000.00')->and($balance->outstanding)->toBe('500000.00')
        ->and(DB::table('money_fact_balances')->where('receivable_writeoff_id', $writeoff->id)->value('contra_amount'))->toBe('300000.00')
        ->and(DB::table('application_source_balances')->where('payment_id', $payment->id)->value('capacity_amount'))->toBe('0.00')
        ->and(DB::table('receivable_writeoffs')->where('id', $writeoff->id)->value('amount'))->toBe('500000.00')
        ->and(receivableDifferences())->toBe([]);

    $this->actingAs($this->owner)->get('/keuangan/invoice/'.InvoiceWorld::publicId($id))->assertInertia(fn (AssertableInertia $page) => $page->where('writeoffs.0.amount', '500000.00')->where('writeoffs.0.standing', '200000.00')->has('recoveries', 0));

    // Money not recovered for this invoice supersedes nothing.
    $elsewhere = InvoiceWorld::payment($id, '100000');
    receivablePost($this, $id, 'cabut-penghapusan', [...$body, 'pembayaran' => $elsewhere->public_id, 'jumlah' => '100000'], $this->owner);
    expect(receivableAnswer()['code'])->toBe('L-17')->and(receivableAnswer()['state']['reason'])->toBe('not_recovery');
});

it('lets a void or revision over a write-off commit only when the Owner disposes of what still stands, superseded parts already gone (AX-22; AX-24; OD-07; C-16; architecture challenge 5b; criterion 8)', function () {
    $id = $this->world->issued($this->project, '1000000');
    billReceivable($this, $id);
    writeOffReceivable($this, $id, '500000');
    $writeoff = DB::table('receivable_writeoffs')->where('invoice_id', $id)->sole();
    $payment = InvoiceWorld::recovery($id, '200000');
    receivablePost($this, $id, 'cabut-penghapusan', ['penghapusan' => RowHandle::of('receivable_writeoffs', $writeoff->id), 'pembayaran' => $payment->public_id, 'jumlah' => '200000', 'alasan' => 'Klien membayar kembali', 'tanggal' => $this->today], $this->owner);
    $application = (int) DB::table('payment_applications')->where('payment_id', $payment->id)->value('id');
    $case = CorrectionCaseFactory::new()->createOne(['company_id' => $this->world->company->id, 'project_id' => $this->project->id, 'case_kind' => 'SALES_RETURN']);
    $void = fn (User $actor): TestResponse => $this->actingAs($actor)->post('/keuangan/invoice/'.InvoiceWorld::publicId($id).'/koreksi', ['command_id' => (string) Str::uuid(), 'aksi' => 'BATAL', 'dasar' => 'RETURN', 'kasus' => RowHandle::of('correction_cases', $case->id), 'lock_version' => DB::table('invoices')->where('id', $id)->value('lock_version'), 'alasan' => 'Barang kembali', 'tanggal' => $this->today,
        'disposisi' => ['aplikasi' => [['id' => RowHandle::of('payment_applications', $application), 'tindakan' => 'RELEASE']], 'hapus' => [['id' => RowHandle::of('receivable_writeoffs', $writeoff->id), 'tindakan' => 'SUPERSEDE']]]]);

    $void($this->actor);
    expect(receivableAnswer()['code'])->toBe('OD-07')->and(DB::table('invoices')->where('id', $id)->value('state'))->toBe('ISSUED');

    $void($this->owner);
    expect(receivableAnswer()['kind'])->toBe('COMMITTED')
        ->and(DB::table('financial_contras')->where('receivable_writeoff_id', $writeoff->id)->sum('amount'))->toEqual(500000)
        ->and(receivableBalance($id)->written_off_amount)->toBe('0.00')
        ->and(DB::table('receivable_writeoffs')->where('id', $writeoff->id)->exists())->toBeTrue()
        ->and(receivableDifferences())->toBe([]);
});

it('issues DOC-13 from the invoice and its billing data with no cash effect, refused with nothing outstanding, voided with its invoice (BR-DOC-04; WF-DOC-01; criterion 10)', function () {
    $id = $this->world->issued($this->project, '1000000');
    billReceivable($this, $id);
    $payment = InvoiceWorld::payment($id, '400000');
    InvoiceWorld::apply($id, $payment, '400000');
    $before = ['payments' => DB::table('payments')->count(), 'outstanding' => receivableBalance($id)->outstanding];

    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-13', 'subjek' => ['invoice_id' => InvoiceWorld::publicId($id)]]);
    $document = DB::table('documents')->where('invoice_id', $id)->where('document_type_code', 'DOC-13')->sole();
    $draft = DB::table('document_versions')->where('document_id', $document->id)->sole();
    $this->actingAs($this->actor)->post("/dokumen/{$document->public_id}/terbitkan", ['command_id' => (string) Str::uuid(), 'draf' => $draft->public_id, 'lock_version' => $draft->lock_version, 'tanggal' => $this->today]);
    $issued = DB::table('document_versions')->where('document_id', $document->id)->sole();
    $paper = json_decode((string) $issued->payload, true)['paper'];

    expect(session('inertia.flash_data')['answer']['kind'] ?? null)->toBe('COMMITTED')->and($issued->state)->toBe('ISSUED')->and($issued->formatted_number)->not->toBeNull()
        ->and($paper['amount'])->toBe('Rp600.000')
        ->and(collect($paper['totals'])->pluck('value')->last())->toBe('Rp600.000')
        ->and(collect($paper['references'])->pluck('label')->all())->toContain('No. Invoice', 'Tanggal Tagihan', 'Jatuh Tempo')
        ->and(['payments' => DB::table('payments')->count(), 'outstanding' => receivableBalance($id)->outstanding])->toBe($before);

    $this->actingAs($this->actor)->post('/keuangan/invoice/'.InvoiceWorld::publicId($id).'/koreksi', ['command_id' => (string) Str::uuid(), 'aksi' => 'BATAL', 'dasar' => 'ENTRY_ERROR_REPLACED', 'bukti' => InvoiceWorld::linkedEvidence($this->world->company->id, $this->project->id)->public_id, 'lock_version' => DB::table('invoices')->where('id', $id)->value('lock_version'), 'alasan' => 'Nilai salah catat', 'tanggal' => $this->today,
        'isi' => ['jenis' => 'VALUE_ONLY', 'tanggal' => $this->today, 'baris' => [['keterangan' => 'Nilai benar', 'nilai' => '900000']]],
        'disposisi' => ['aplikasi' => [['id' => RowHandle::of('payment_applications', (int) DB::table('payment_applications')->where('invoice_id', $id)->value('id')), 'tindakan' => 'RETARGET']]]]);
    expect(session('inertia.flash_data')['answer']['kind'] ?? null)->toBe('COMMITTED')->and(DB::table('document_versions')->where('document_id', $document->id)->value('state'))->toBe('VOIDED');

    $paid = $this->world->issued($this->project, '1000');
    InvoiceWorld::apply($paid, InvoiceWorld::payment($paid, '1000'), '1000');
    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-13', 'subjek' => ['invoice_id' => InvoiceWorld::publicId($paid)]]);
    $request = DB::table('documents')->where('invoice_id', $paid)->where('document_type_code', 'DOC-13')->first();
    $requestDraft = DB::table('document_versions')->where('document_id', $request?->id)->first();
    $this->actingAs($this->actor)->post("/dokumen/{$request?->public_id}/terbitkan", ['command_id' => (string) Str::uuid(), 'draf' => $requestDraft?->public_id, 'lock_version' => $requestDraft?->lock_version, 'tanggal' => $this->today]);
    expect(session('inertia.flash_data')['answer']['code'] ?? null)->toBe('WF-DOC-01');
});

it('shows PG-044 with a subtotal per company and no total across them, its tabs, branch and client filters and its keyset (CS-08; D-IA-15; J-FIN-02; criterion 11)', function () {
    $own = $this->world->issued($this->project, '1000000');
    billReceivable($this, $own);
    $unbilled = $this->world->issued($this->project, '100000');
    $other = InvoiceWorld::make();
    $otherProject = $other->project();
    $theirs = $other->issued($otherProject, '700000');
    InvoiceWorld::bill($theirs, $this->today, '2099-12-31');
    $both = Authorization::admin([$this->world->company->id, $other->company->id], ['finance.view', 'billing.record', 'payment.record']);

    $this->actingAs($both)->get('/keuangan/piutang')->assertInertia(fn (AssertableInertia $page) => $page->has('branches', 2)->has('rows', 2)->has('subtotals', 2)
        ->where('subtotals', fn ($rows) => collect($rows)->pluck('amount')->sort()->values()->all() === ['1000000.00', '700000.00'] || collect($rows)->pluck('amount')->sort()->values()->all() === ['700000.00', '1000000.00'])
        ->missing('total')->missing('grand_total'));
    $this->actingAs($both)->get('/keuangan/piutang?perusahaan='.$other->company->public_id)->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1)->where('rows.0.id', InvoiceWorld::publicId($theirs))->has('subtotals', 1)->where('subtotals.0.amount', '700000.00'));
    $this->actingAs($both)->get('/keuangan/piutang?tab=belum')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1)->where('rows.0.id', InvoiceWorld::publicId($unbilled))->where('rows.0.can.bill', true)->where('rows.0.due_date', null)->where('rows.0.state', 'ISSUED_NOT_BILLED'));

    $client = DB::table('client_organizations')->where('id', DB::table('invoices')->where('id', $theirs)->value('client_organization_id'))->value('public_id');
    $this->actingAs($both)->get("/keuangan/piutang?klien={$client}")->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1)->where('rows.0.id', InvoiceWorld::publicId($theirs))->has('clients', 2));

    // A holder of one company sees that company alone.
    $this->actingAs($this->actor)->get('/keuangan/piutang')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1)->where('rows.0.id', InvoiceWorld::publicId($own))->has('subtotals', 1));
});

it('continues PG-044 after its cursor, oldest due first, nothing twice (D-PF-02; IX-11; INT-008)', function () {
    $ids = [];

    foreach (range(1, 3) as $n) {
        $id = $this->world->issued($this->project, '1000');
        InvoiceWorld::bill($id, $this->today, CarbonImmutable::parse('2099-01-01')->addDays(3 - $n)->toDateString());
        $ids[$n] = InvoiceWorld::publicId($id);
    }

    $this->actingAs($this->actor)->get("/keuangan/piutang?setelah={$ids[3]}")->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 2)->where('rows.0.id', $ids[2])->where('rows.1.id', $ids[1])->where('after', $ids[3])->where('cursor_failed', false));
});

it('tells the journey what billing and the receivable still need, without counts for a viewer without finance.view (BR-PRJ-02; PJ-13)', function () {
    $id = $this->world->issued($this->project, '1000000');
    $predicate = app(ReceivablePredicate::class);
    $states = $predicate->states([$this->project->id], true)[$this->project->id];
    expect($states[Predicates::BILLING]->items[0]->key)->toBe('projects.item.invoices_unbilled')->and($states[Predicates::BILLING]->items[0]->money)->toBeTrue()
        ->and($states[Predicates::RECEIVABLE]->met())->toBeTrue();
    expect($predicate->states([$this->project->id], false)[$this->project->id][Predicates::BILLING]->items[0]->key)->toBe('projects.next.billing');

    billReceivable($this, $id);
    $states = $predicate->states([$this->project->id], true)[$this->project->id];
    expect($states[Predicates::BILLING]->met())->toBeTrue()->and($states[Predicates::RECEIVABLE]->items[0]->key)->toBe('projects.item.receivables_open');
    expect($predicate->states([$this->project->id], false)[$this->project->id][Predicates::RECEIVABLE]->items[0]->key)->toBe('projects.next.payment')
        ->and($predicate->states([], true))->toBe([]);
});

it('renders DOC-13 in Indonesian whatever the interface language (LOCALIZATION §3.4; DOD-16; TL-10)', function () {
    $id = $this->world->issued($this->project, '1000000');
    billReceivable($this, $id);
    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-13', 'subjek' => ['invoice_id' => InvoiceWorld::publicId($id)]]);
    $document = DB::table('documents')->where('invoice_id', $id)->where('document_type_code', 'DOC-13')->sole();
    $draft = DB::table('document_versions')->where('document_id', $document->id)->sole();
    $this->actingAs($this->actor)->post("/dokumen/{$document->public_id}/terbitkan", ['command_id' => (string) Str::uuid(), 'draf' => $draft->public_id, 'lock_version' => $draft->lock_version, 'tanggal' => $this->today]);
    $paper = json_decode((string) DB::table('document_versions')->where('document_id', $document->id)->value('payload'), true)['paper'];

    app()->setLocale('en');

    expect(MustacheTemplates::render('DOC-13', $paper))->toContain('Surat Permintaan Pembayaran')->toContain('Dengan hormat')->toContain('Sisa Tagihan');
});

it('asks nothing of another client\'s credit (L-17; BR-FIN-05)', function () {
    $id = $this->world->issued($this->project, '1000000');
    billReceivable($this, $id);
    InvoiceWorld::payment($id, '250000', '0.00', null, ClientOrganization::factory()->create()->id);

    writeOffReceivable($this, $id, '100000');
    expect(receivableAnswer()['kind'])->toBe('COMMITTED');
});

it('accepts the residual commands on a completed project without reopening it, a correction revalidating it (WF-PRJ-03; L-11; SF-REVAL; BR-CR-05; M-26)', function () {
    $id = $this->world->issued($this->project, '1000000');
    $unbilled = $this->world->issued($this->project, '1000');
    InvoiceWorld::bill($id, $this->today, '2099-12-31');
    InvoiceWorld::close($this->project->id, $this->world->actorId);
    $state = fn (): string => (string) DB::table('projects')->where('id', $this->project->id)->value('state');

    billReceivable($this, $unbilled);
    expect(receivableAnswer()['kind'])->toBe('COMMITTED')->and($state())->toBe('COMPLETED_NORMAL');
    writeOffReceivable($this, $id, '300000');
    expect(receivableAnswer()['kind'])->toBe('COMMITTED')->and($state())->toBe('COMPLETED_NORMAL');
    $writeoff = (int) DB::table('receivable_writeoffs')->where('invoice_id', $id)->value('id');
    receivablePost($this, $id, 'cabut-penghapusan', ['penghapusan' => RowHandle::of('receivable_writeoffs', $writeoff), 'pembayaran' => InvoiceWorld::recovery($id, '1000')->public_id, 'jumlah' => '1000', 'alasan' => 'Klien membayar kembali', 'tanggal' => $this->today], $this->owner);
    expect(receivableAnswer()['kind'])->toBe('COMMITTED')->and($state())->toBe('COMPLETED_NORMAL');

    receivablePost($this, $id, 'sengketa', ['aksi' => 'TANDAI', 'alasan' => 'Klien membantah']);
    expect(receivableAnswer()['kind'])->toBe('COMMITTED')->and($state())->toBe('ACTIVE')
        ->and(DB::table('project_state_transitions')->where('project_id', $this->project->id)->where('from_state', 'COMPLETED_NORMAL')->value('correction_reference'))->toStartWith('FIN.DisputeHoldSet:');
});

it('revalidates a completed project on a void or a revision of its invoice (SF-REVAL; CM-23; M-26)', function () {
    $id = $this->world->issued($this->project, '1000000');
    InvoiceWorld::close($this->project->id, $this->world->actorId);

    $this->actingAs($this->actor)->post('/keuangan/invoice/'.InvoiceWorld::publicId($id).'/koreksi', ['command_id' => (string) Str::uuid(), 'aksi' => 'BATAL', 'dasar' => 'UNBILLED_ENTRY_ERROR', 'lock_version' => DB::table('invoices')->where('id', $id)->value('lock_version'), 'alasan' => 'Salah catat', 'tanggal' => $this->today]);

    expect(receivableAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('projects')->where('id', $this->project->id)->value('state'))->toBe('ACTIVE')
        ->and(DB::table('project_state_transitions')->where('project_id', $this->project->id)->where('from_state', 'COMPLETED_NORMAL')->value('correction_reference'))->toStartWith('FIN.InvoiceVoided:');
});

it('re-records several standing write-offs of a void as one, nothing vanishing (AX-22; OD-07; DATABASE §4.12; criterion 8)', function () {
    $id = $this->world->issued($this->project, '1000000');
    billReceivable($this, $id);
    writeOffReceivable($this, $id, '100000');
    writeOffReceivable($this, $id, '200000');
    $writeoffs = DB::table('receivable_writeoffs')->where('invoice_id', $id)->pluck('id')->all();
    expect($writeoffs)->toHaveCount(2);

    receivablePost($this, $id, 'koreksi', ['aksi' => 'BATAL', 'dasar' => 'ENTRY_ERROR_REPLACED', 'bukti' => InvoiceWorld::linkedEvidence($this->world->company->id, $this->project->id)->public_id, 'lock_version' => DB::table('invoices')->where('id', $id)->value('lock_version'), 'alasan' => 'Nilai salah catat', 'tanggal' => $this->today,
        'isi' => ['jenis' => 'VALUE_ONLY', 'tanggal' => $this->today, 'baris' => [['keterangan' => 'Nilai benar', 'nilai' => '900000']]],
        'disposisi' => ['hapus' => array_map(fn ($writeoff): array => ['id' => RowHandle::of('receivable_writeoffs', (int) $writeoff), 'tindakan' => 'RERECORD'], $writeoffs)]], $this->owner);

    expect(receivableAnswer()['kind'])->toBe('COMMITTED');
    $replacement = (int) DB::table('invoices')->where('replaces_invoice_id', $id)->value('id');
    expect(DB::table('receivable_writeoffs')->where('invoice_id', $replacement)->sole()->amount)->toBe('300000.00')
        ->and(receivableBalance($replacement)->written_off_amount)->toBe('300000.00')
        ->and(receivableDifferences())->toBe([]);
});

it('lets money recovered for a replaced invoice lift the write-off its replacement now carries (L-17; L-30; criterion 7)', function () {
    $id = $this->world->issued($this->project, '1000000');
    billReceivable($this, $id);
    writeOffReceivable($this, $id, '100000');
    $payment = InvoiceWorld::recovery($id, '50000');
    $writeoff = (int) DB::table('receivable_writeoffs')->where('invoice_id', $id)->value('id');
    receivablePost($this, $id, 'koreksi', ['aksi' => 'BATAL', 'dasar' => 'ENTRY_ERROR_REPLACED', 'bukti' => InvoiceWorld::linkedEvidence($this->world->company->id, $this->project->id)->public_id, 'lock_version' => DB::table('invoices')->where('id', $id)->value('lock_version'), 'alasan' => 'Nilai salah catat', 'tanggal' => $this->today,
        'isi' => ['jenis' => 'VALUE_ONLY', 'tanggal' => $this->today, 'baris' => [['keterangan' => 'Nilai benar', 'nilai' => '900000']]],
        'disposisi' => ['hapus' => [['id' => RowHandle::of('receivable_writeoffs', $writeoff), 'tindakan' => 'RERECORD']]]], $this->owner);
    $replacement = (int) DB::table('invoices')->where('replaces_invoice_id', $id)->value('id');
    $carried = (int) DB::table('receivable_writeoffs')->where('invoice_id', $replacement)->value('id');

    $this->actingAs($this->owner)->get('/keuangan/invoice/'.InvoiceWorld::publicId($replacement))->assertInertia(fn (AssertableInertia $page) => $page->where('recoveries.0.id', $payment->public_id));
    receivablePost($this, $replacement, 'cabut-penghapusan', ['penghapusan' => RowHandle::of('receivable_writeoffs', $carried), 'pembayaran' => $payment->public_id, 'jumlah' => '50000', 'alasan' => 'Klien membayar kembali', 'tanggal' => $this->today], $this->owner);

    expect(receivableAnswer()['kind'])->toBe('COMMITTED')->and(receivableBalance($replacement)->applied_amount)->toBe('50000.00')->and(receivableBalance($replacement)->written_off_amount)->toBe('50000.00')->and(receivableDifferences())->toBe([]);
});

it('clears a hold that lapsed at zero, so it does not return should the outstanding come back (L-17; C-42)', function () {
    $id = $this->world->issued($this->project, '1000000');
    billReceivable($this, $id);
    receivablePost($this, $id, 'sengketa', ['aksi' => 'TANDAI', 'alasan' => 'Klien membantah']);
    writeOffReceivable($this, $id, '1000000');
    receivablePost($this, $id, 'sengketa', ['aksi' => 'CABUT', 'alasan' => 'Sudah selesai']);

    expect(receivableAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('receivable_dispute_holds')->where('invoice_id', $id)->whereNull('cleared_at')->exists())->toBeFalse();
});

it('reads a cursor only within the viewer\'s companies: another company\'s invoice positions nothing (CS-07; AZ-11)', function () {
    $id = $this->world->issued($this->project, '1000');
    InvoiceWorld::bill($id, $this->today, '2099-12-31');
    $other = InvoiceWorld::make();
    $theirs = $other->issued($other->project(), '1000');
    InvoiceWorld::bill($theirs, $this->today, '2100-12-31');

    $this->actingAs($this->actor)->get('/keuangan/piutang?setelah='.InvoiceWorld::publicId($theirs))->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0)->where('cursor_failed', true)->where('after', null));
    $this->actingAs($this->actor)->get('/keuangan/piutang?setelah=bukan-kursor')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0)->where('cursor_failed', true));
    $this->actingAs($this->actor)->get('/keuangan/piutang')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1)->where('cursor_failed', false)->where('after', null));
});

it('asks for evidence tied to the records of an invoice that has them: none without a project (DIR-092 (2); CM-25)', function () {
    $invoice = new Invoice;
    $invoice->forceFill(['project_id' => null]);
    $evidence = EvidenceDocumentFactory::new()->createOne(['company_id' => $this->world->company->id, 'evidence_type' => 'OTHER']);

    expect(InvoiceEvidence::linked($invoice, $evidence->id))->toBeFalse();
});
