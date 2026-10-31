<?php

use App\Modules\FIN\Data\ContraEntry;
use App\Modules\FIN\Models\Payment;
use App\Modules\FIN\Services\PaymentPredicate;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PTY\Models\ClientOrganization;
use App\Support\BusinessDate;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use App\Support\RowHandle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-051; ROUTE-251–ROUTE-254; ROUTE-261; ROUTE-262; AC-10; FX-01 1–5; FX-04; L-22; L-28; L-29; L-46; C-13–C-15; C-19; C-22; C-43–C-45): each route with each refusal its contract names, by the page's own requests; the reconciliation after each.

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
    $this->company = $this->world->company;
    $this->companyKey = $this->company->public_id;
    $this->client = $this->project->client_organization_id;
    $this->clientKey = DB::table('client_organizations')->where('id', $this->client)->value('public_id');
    $this->today = BusinessDate::today();
    $this->account = PaymentWorld::account($this->company->id);
    $this->invoice = $this->world->issued($this->project, '1000000');
    $this->invoiceKey = InvoiceWorld::publicId($this->invoice);
    InvoiceWorld::bill($this->invoice, $this->today, '2099-12-31');
});

afterEach(fn () => @unlink($this->hotFile));

function paymentAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function paymentDifferences(): array
{
    $found = [];

    foreach (['invoice_balances', 'application_source_balances', 'evidence_balances', 'money_fact_balances', 'correction_cases'] as $family) {
        $found = [...$found, ...(new Reconciler)->differences(DB::connection(), Families::named($family) ?? throw new RuntimeException($family))];
    }

    return $found;
}

function paymentBody(object $test, string $amount, array $fields = []): array
{
    return ['command_id' => (string) Str::uuid(), 'perusahaan' => $test->companyKey, 'klien' => $test->clientKey, 'tanggal' => $test->today, 'jumlah' => $amount, 'metode' => 'TRANSFER', 'rekening' => PaymentWorld::accountHandle($test->account), 'bukti' => [PaymentWorld::proof($test->company->id)->public_id], ...$fields];
}

function outstandingOf(int $invoice): string
{
    return (string) DB::table('invoice_balances')->where('invoice_id', $invoice)->value('outstanding');
}

function sourceOf(string $publicId): object
{
    return DB::table('application_source_balances as b')->join('payments as p', 'p.id', '=', 'b.payment_id')->where('p.public_id', $publicId)->select('b.*')->sole();
}

function paymentDay(int $days): string
{
    return CarbonImmutable::parse(BusinessDate::today())->addDays($days)->toDateString();
}

function lastPayment(): object
{
    return DB::table('payments')->orderByDesc('id')->first();
}

it('records Rp300,000 then Rp700,000 to a billed Rp1,000,000 invoice: Cash-In each, Rp700,000 then nothing outstanding (criterion 1; AC-10; annex 1)', function () {
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '300000', ['penerapan' => [['invoice' => $this->invoiceKey, 'jumlah' => '300000']]]))->assertRedirectContains('/keuangan/pembayaran?');
    expect(paymentAnswer()['kind'])->toBe('COMMITTED')->and(outstandingOf($this->invoice))->toBe('700000.00')
        ->and(DB::table('payments')->count())->toBe(1)->and(DB::table('payments')->value('amount'))->toBe('300000.00');

    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '700000', ['tanggal' => paymentDay(-9), 'penerapan' => [['invoice' => $this->invoiceKey, 'jumlah' => '700000']]]));
    expect(paymentAnswer()['kind'])->toBe('COMMITTED')->and(outstandingOf($this->invoice))->toBe('0.00')->and(paymentDifferences())->toBe([]);
});

it('keeps an overpayment\'s rest as credit, never profit (criterion 1; annex 3; BR-FIN-05; FS-08)', function () {
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '1200000', ['penerapan' => [['invoice' => $this->invoiceKey, 'jumlah' => '1000000']]]));
    $source = sourceOf(lastPayment()->public_id);
    expect($source->capacity_amount)->toBe('200000.00')->and($source->invoice_applied_amount)->toBe('1000000.00')->and(outstandingOf($this->invoice))->toBe('0.00')->and(paymentDifferences())->toBe([]);
});

it('refuses a zero or negative payment at the field (criterion 1; BR-FIN-06)', function (string $amount) {
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, $amount))->assertSessionHasErrors('jumlah');
    expect(DB::table('payments')->count())->toBe(0);
})->with(['zero' => '0', 'negative' => '-100']);

it('counts an advance on an unbilled invoice once: billing activates only the rest (annex 2; BR-FIN-04)', function () {
    $unbilled = $this->world->issued($this->project, '1000000');
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '400000', ['penerapan' => [['invoice' => InvoiceWorld::publicId($unbilled), 'jumlah' => '400000']]]));
    expect(paymentAnswer()['kind'])->toBe('COMMITTED')->and(outstandingOf($unbilled))->toBe('600000.00');

    InvoiceWorld::bill($unbilled, $this->today, '2099-12-31');
    expect(outstandingOf($unbilled))->toBe('600000.00');
});

it('refuses an application above the outstanding with the figure, and more than the payment (criterion 2; C-13; C-15)', function () {
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '2000000', ['penerapan' => [['invoice' => $this->invoiceKey, 'jumlah' => '1500000']]]));
    expect(paymentAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-15'])->and(paymentAnswer()['text'])->toContain('Rp1.000.000')->and(DB::table('payments')->count())->toBe(0);

    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '300000', ['penerapan' => [['invoice' => $this->invoiceKey, 'jumlah' => '400000']]]));
    expect(paymentAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-13'])->and(DB::table('payments')->count())->toBe(0);
});

it('refuses an invoice of another client, and answers one of a company out of scope as unknown; such invoices are never offered (criterion 3; C-14; FS-10; SM-01)', function () {
    $otherClient = ClientOrganization::factory()->createOne();
    $elsewhere = InvoiceWorld::make();
    $foreign = $elsewhere->issued($elsewhere->project(), '500000');
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '100000', ['penerapan' => [['invoice' => DB::table('invoices')->where('id', $foreign)->value('public_id'), 'jumlah' => '100000']]]))->assertSessionHasErrors('penerapan.0.invoice');
    expect(DB::table('payments')->count())->toBe(0);

    $this->actingAs($this->actor)->get("/keuangan/pembayaran/baru?perusahaan={$this->companyKey}&klien={$this->clientKey}")
        ->assertInertia(fn (AssertableInertia $page) => $page->component('FIN/RecordPayment')->where('invoices', fn ($rows) => collect($rows)->pluck('id')->doesntContain(DB::table('invoices')->where('id', $foreign)->value('public_id'))));

    $person = PaymentWorld::record($this->world->actorId, $this->company->id, $otherClient->id, '100000', ['applications' => [$this->invoice => '100000']]);
    expect($person->kind)->toBe('REJECTED')->and($person->code)->toBe('C-14');
});

it('leaves the second of two payments citing one line beyond it REJECTED (criterion 4; C-19; BD-03)', function () {
    $line = PaymentWorld::line($this->company->id, $this->account, '1000000');
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '700000', ['mutasi' => RowHandle::of('bank_statement_lines', $line), 'bukti' => []]));
    expect(paymentAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('evidence_balances')->where('bank_statement_line_id', $line)->value('cited_amount'))->toBe('700000.00');

    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '400000', ['mutasi' => RowHandle::of('bank_statement_lines', $line), 'bukti' => []]));
    expect(paymentAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-19'])->and(paymentAnswer()['text'])->toContain('Rp300.000')->and(DB::table('payments')->count())->toBe(1)->and(paymentDifferences())->toBe([]);
});

it('warns on a likely duplicate whatever the reference until overridden naming the match (criterion 4; BD-02; IP-05; UXS-16; QS-21)', function () {
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '500000', ['referensi' => 'A', 'tanggal' => paymentDay(-3)]));
    $first = lastPayment()->public_id;

    $body = paymentBody($this, '500000', ['referensi' => 'B']);
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', $body);
    expect(paymentAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'L-22'])->and(paymentAnswer()['state']['matches'][0]['id'])->toBe($first)->and(paymentAnswer()['state']['override'])->toBeFalse()->and(paymentAnswer()['text'])->toContain('minta admin berwenang');
    expect(DB::table('payments')->count())->toBe(1);

    $admin = User::query()->findOrFail(Authorization::admin([$this->company->id], [...InvoiceWorld::CAPABILITIES, 'warnings.override'])->id);
    $this->actingAs($admin)->post('/keuangan/pembayaran', paymentBody($this, '500000', ['referensi' => 'C']));
    expect(paymentAnswer()['state']['override'])->toBeTrue();

    $other = (string) Str::uuid();
    $this->actingAs($admin)->post('/keuangan/pembayaran', paymentBody($this, '500000', ['referensi' => 'C', 'acknowledged' => [$other], 'reason' => 'Dua transfer berbeda']));
    expect(paymentAnswer()['code'])->toBe('L-22')->and(DB::table('payments')->count())->toBe(1);

    $this->actingAs($admin)->post('/keuangan/pembayaran', paymentBody($this, '500000', ['referensi' => 'C', 'acknowledged' => [$first], 'reason' => 'Dua transfer berbeda']));
    expect(paymentAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('payments')->count())->toBe(2)
        ->and(DB::table('audit_events')->where('action', 'FIN.PaymentDuplicateOverridden')->value('authority_class'))->toBe('ADM_PLUS');
});

it('does not warn outside seven days either side, nor on another account, nor on a cash payment (BD-02)', function () {
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '500000', ['tanggal' => paymentDay(-8)]));
    expect(paymentAnswer()['kind'])->toBe('COMMITTED');
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '500000'));
    expect(paymentAnswer()['kind'])->toBe('COMMITTED');
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '500000', ['metode' => 'CASH', 'rekening' => null]));
    expect(paymentAnswer()['kind'])->toBe('COMMITTED');
});

it('requires the account unless in cash, of the company and active (C-32)', function () {
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '500000', ['rekening' => null]))->assertSessionHasErrors('rekening');

    $foreign = PaymentWorld::account(Company::factory()->createOne()->id);
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '500000', ['rekening' => PaymentWorld::accountHandle($foreign)]))->assertSessionHasErrors('rekening');

    DB::table('company_bank_accounts')->where('id', $this->account)->update(['is_active' => false, 'deactivated_at' => now(), 'deactivated_by' => $this->world->actorId, 'deactivated_reason' => 'Tutup']);
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '500000'));
    expect(paymentAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-32']);
});

it('moves Rp400,000 from invoice A to B, superseding, the payment total unchanged, both steps audited (criterion 6; annex 4; CM-21)', function () {
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '700000', ['penerapan' => [['invoice' => $this->invoiceKey, 'jumlah' => '700000']]]));
    $payment = lastPayment();
    $application = DB::table('payment_applications')->where('payment_id', $payment->id)->value('id');
    $target = $this->world->issued($this->project, '500000');
    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/realokasi", ['command_id' => (string) Str::uuid(), 'penerapan' => RowHandle::of('payment_applications', $application), 'invoice' => InvoiceWorld::publicId($target), 'jumlah' => '400000', 'tanggal' => $this->today, 'alasan' => 'Salah invoice']);

    expect(paymentAnswer()['kind'])->toBe('COMMITTED')
        ->and(DB::table('payment_applications')->where('id', $application)->value('state'))->toBe('SUPERSEDED')->and(DB::table('payment_applications')->where('id', $application)->value('end_reason'))->toBe('REALLOCATED')
        ->and(outstandingOf($this->invoice))->toBe('700000.00')->and(outstandingOf($target))->toBe('100000.00')
        ->and(sourceOf($payment->public_id)->invoice_applied_amount)->toBe('700000.00')->and(sourceOf($payment->public_id)->capacity_amount)->toBe('0.00')
        ->and(DB::table('audit_events')->where('action', 'FIN.ApplicationReallocated')->value('authority_class'))->toBe('ADM_PLUS')->and(paymentDifferences())->toBe([]);
});

it('offers no move for a refund-consumed application, the row saying why (criterion 6; L-46)', function () {
    $payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '500000');
    $refund = PaymentWorld::refund($payment->id, '200000');
    $target = $this->world->issued($this->project, '500000');
    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/realokasi", ['command_id' => (string) Str::uuid(), 'penerapan' => RowHandle::of('payment_applications', $refund['application']), 'invoice' => InvoiceWorld::publicId($target), 'jumlah' => '200000', 'tanggal' => $this->today, 'alasan' => 'Salah invoice']);
    expect(paymentAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'L-46'])->and(DB::table('payment_applications')->where('id', $refund['application'])->value('state'))->toBe('ACTIVE');

    $this->actingAs($this->actor)->get('/keuangan/pembayaran')->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.applications.0.can_move', false)->where('rows.0.applications.0.target', 'REFUND'));
});

it('moves the settlements anchored to the old (payment, invoice) or keeps them, in the same command, a stale list a CONFLICT (criterion 6; L-28; ST-07)', function () {
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '700000', ['penerapan' => [['invoice' => $this->invoiceKey, 'jumlah' => '700000']]]));
    $payment = lastPayment();
    DB::table('application_source_balances')->where('payment_id', $payment->id)->update(['claimed_deductions_amount' => '50000']);
    DB::table('payment_claimed_deductions')->insert(['payment_id' => $payment->id, 'deduction_class' => 'FEE', 'fee_type' => 'BIAYA_TRANSFER', 'claimed_amount' => '50000', 'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $this->world->actorId]);
    $settlement = InvoiceWorld::settle($this->invoice, Payment::query()->findOrFail($payment->id), '50000');
    $application = DB::table('payment_applications')->where('payment_id', $payment->id)->value('id');
    $target = $this->world->issued($this->project, '500000');
    $body = ['command_id' => (string) Str::uuid(), 'penerapan' => RowHandle::of('payment_applications', $application), 'invoice' => InvoiceWorld::publicId($target), 'jumlah' => '400000', 'tanggal' => $this->today, 'alasan' => 'Salah invoice'];

    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/realokasi", $body);
    expect(paymentAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'ST-07']);

    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/realokasi", [...$body, 'command_id' => (string) Str::uuid(), 'potongan' => [['baris' => RowHandle::of('deduction_settlements', $settlement), 'aksi' => 'PINDAH']]]);
    expect(paymentAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('deduction_settlements')->where('id', $settlement)->value('state'))->toBe('CONTRAED')
        ->and(DB::table('deduction_settlements')->where('invoice_id', $target)->where('state', 'ACTIVE')->value('amount'))->toBe('50000.00')
        ->and(DB::table('invoice_balances')->where('invoice_id', $target)->value('fee_settled_amount'))->toBe('50000.00')->and(DB::table('invoice_balances')->where('invoice_id', $this->invoice)->value('fee_settled_amount'))->toBe('0.00')
        ->and(sourceOf($payment->public_id)->settled_amount)->toBe('50000.00')->and(paymentDifferences())->toBe([]);
});

it('commits a contra only with a disposition of everything active, a stale list a CONFLICT with the current one (criterion 7; AX-20; ST-07)', function () {
    $payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '500000', '500000');
    $application = DB::table('payment_applications')->where('payment_id', $payment->id)->value('id');
    $base = ['jumlah' => '500000', 'tanggal' => $this->today, 'alasan' => 'Salah catat'];

    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/kontra", ['command_id' => (string) Str::uuid(), ...$base]);
    expect(paymentAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'ST-07'])->and(paymentAnswer()['state']['applications'])->toBe([RowHandle::of('payment_applications', $application)]);

    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/kontra", ['command_id' => (string) Str::uuid(), ...$base, 'penerapan' => [['baris' => RowHandle::of('payment_applications', $application), 'aksi' => ContraEntry::KEEP]]]);
    expect(paymentAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-13']);

    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/kontra", ['command_id' => (string) Str::uuid(), ...$base, 'penerapan' => [['baris' => RowHandle::of('payment_applications', $application), 'aksi' => ContraEntry::RELEASE]]]);
    expect(paymentAnswer()['kind'])->toBe('COMMITTED')->and(sourceOf($payment->public_id)->net_amount)->toBe('0.00')->and(sourceOf($payment->public_id)->invoice_applied_amount)->toBe('0.00')
        ->and(outstandingOf($this->invoice))->toBe('1000000.00')
        ->and(DB::table('payments')->where('id', $payment->id)->exists())->toBeTrue()
        ->and(DB::table('correction_cases')->where('case_kind', 'PAYMENT_CORRECTION')->where('state', 'OPEN')->count())->toBe(1)
        ->and(DB::table('financial_contras')->where('payment_id', $payment->id)->value('correction_case_id'))->not->toBeNull()->and(paymentDifferences())->toBe([]);
});

it('cannot free capacity with the Rp1 contra: refused whichever way the attacker disposes of R (criterion 7; DATABASE §11.3; C-44; FX-04)', function () {
    $payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '1200000', '1000000');
    $application = DB::table('payment_applications')->where('payment_id', $payment->id)->where('target_kind', 'INVOICE')->value('id');
    $refund = PaymentWorld::refund($payment->id, '200000');
    $keep = [$application => ['action' => ContraEntry::KEEP, 'amount' => null]];

    // End R by exit (b): P's capacity would be 199,999.
    expect(PaymentWorld::contraInCommand($this->world->actorId, $payment->id, '1', $keep + [$refund['application'] => ['action' => ContraEntry::SHORTFALL, 'amount' => null]]))->toBe('REJECTED C-44')
        // Superseding R by 199,999 from P with a Rp1 shortfall.
        ->and(PaymentWorld::contraInCommand($this->world->actorId, $payment->id, '1', $keep + [$refund['application'] => ['action' => ContraEntry::SHORTFALL, 'amount' => '1']]))->toBe('REJECTED C-44')
        // Nothing disposed: capacity would be negative.
        ->and(PaymentWorld::contraInCommand($this->world->actorId, $payment->id, '1', $keep + [$refund['application'] => ['action' => ContraEntry::KEEP, 'amount' => null]]))->toBe('REJECTED C-13');

    // The only committable disposition keeps the capacity at 0.
    expect(PaymentWorld::contraInCommand($this->world->actorId, $payment->id, '1', [$application => ['action' => ContraEntry::RELEASE, 'amount' => '1'], $refund['application'] => ['action' => ContraEntry::KEEP, 'amount' => null]]))->toBe('COMMITTED');
    $source = sourceOf($payment->public_id);
    expect($source->net_amount)->toBe('1199999.00')->and($source->invoice_applied_amount)->toBe('999999.00')->and($source->refund_backed_amount)->toBe('200000.00')->and($source->capacity_amount)->toBe('0.00')
        ->and(outstandingOf($this->invoice))->toBe('1.00')
        ->and(PaymentWorld::applyInCommand($this->world->actorId, $payment->id, $this->invoice, '1'))->toBe('REJECTED C-13')
        ->and(paymentDifferences())->toBe([]);
});

it('carries a legitimate shortfall on the case, blocking its closure until a real payment resolves it (criterion 7; L-46; C-46)', function () {
    $payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '1200000', '1000000');
    $application = DB::table('payment_applications')->where('payment_id', $payment->id)->where('target_kind', 'INVOICE')->value('id');
    $refund = PaymentWorld::refund($payment->id, '200000');

    // P was really 100,000: contra 1,100,000, A released entirely, R superseded by 100,000 from P, D's shortfall 100,000.
    expect(PaymentWorld::contraInCommand($this->world->actorId, $payment->id, '1100000', [$application => ['action' => ContraEntry::RELEASE, 'amount' => null], $refund['application'] => ['action' => ContraEntry::SHORTFALL, 'amount' => '100000']]))->toBe('COMMITTED');
    $source = sourceOf($payment->public_id);
    $fact = DB::table('money_fact_balances')->where('disbursement_id', $refund['refund'])->sole();
    $case = DB::table('correction_cases')->where('id', $fact->residual_case_id)->sole();
    expect($source->net_amount)->toBe('100000.00')->and($source->refund_backed_amount)->toBe('100000.00')->and($source->refund_shortfall_amount)->toBe('100000.00')->and($source->capacity_amount)->toBe('0.00')
        ->and($fact->backed_amount)->toBe('100000.00')->and($fact->shortfall_amount)->toBe('100000.00')->and($case->unbacked_refund_amount)->toBe('100000.00')
        ->and(outstandingOf($this->invoice))->toBe('1000000.00')->and(paymentDifferences())->toBe([]);

    // The client later pays 100,000: applied to the refund it resolves the shortfall, and the case can close.
    $later = PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '100000', ['resolves' => [$refund['application'] => '100000']]);
    expect($later->kind)->toBe('COMMITTED');
    $fact = DB::table('money_fact_balances')->where('disbursement_id', $refund['refund'])->sole();
    expect($fact->backed_amount)->toBe('200000.00')->and($fact->shortfall_amount)->toBe('0.00')->and(DB::table('correction_cases')->where('id', $case->id)->value('unbacked_refund_amount'))->toBe('0.00')
        ->and(sourceOf($payment->public_id)->refund_shortfall_amount)->toBe('0.00')->and(paymentDifferences())->toBe([]);
});

it('moves a refund-consumed application only to the re-recorded payment of the same client and company (criterion 7; CM-19; L-46)', function () {
    $payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '200000');
    $refund = PaymentWorld::refund($payment->id, '200000');
    $move = [$refund['application'] => ['action' => ContraEntry::MOVE, 'amount' => null]];

    expect(PaymentWorld::contraInCommand($this->world->actorId, $payment->id, '200000', $move))->toBe('REJECTED L-46');

    $rerecorded = PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '250000', ['rerecords' => $payment->id])->resultId;
    expect(PaymentWorld::contraInCommand($this->world->actorId, $payment->id, '200000', $move, [], $rerecorded))->toBe('COMMITTED')
        ->and(DB::table('payment_applications')->where('id', $refund['application'])->value('end_reason'))->toBe('RE_RECORDED')
        ->and(DB::table('application_source_balances')->where('payment_id', $rerecorded)->value('refund_backed_amount'))->toBe('200000.00')
        ->and(DB::table('application_source_balances')->where('payment_id', $rerecorded)->value('capacity_amount'))->toBe('50000.00')
        ->and(paymentDifferences())->toBe([]);
});

it('lowers the cited line by a contra, and revalidates a completed project whose outstanding rises (C-19; SF-REVAL; M-26)', function () {
    $line = PaymentWorld::line($this->company->id, $this->account, '1000000');
    $payment = PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '1000000', ['accountId' => $this->account, 'lineId' => $line, 'applications' => [$this->invoice => '1000000']]);
    $application = DB::table('payment_applications')->where('payment_id', $payment->resultId)->value('id');
    InvoiceWorld::close($this->project->id, $this->world->actorId);

    expect(PaymentWorld::contraInCommand($this->world->actorId, $payment->resultId, '1000000', [$application => ['action' => ContraEntry::RELEASE, 'amount' => null]]))->toBe('COMMITTED')
        ->and(DB::table('evidence_balances')->where('bank_statement_line_id', $line)->value('cited_amount'))->toBe('0.00')
        ->and(DB::table('projects')->where('id', $this->project->id)->value('state'))->toBe('ACTIVE')
        ->and(paymentDifferences())->toBe([]);
});

it('never reopens a completed project for a payment or an application: residual commands (WF-PRJ-03; L-11)', function () {
    InvoiceWorld::close($this->project->id, $this->world->actorId);
    expect(PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '100000', ['applications' => [$this->invoice => '100000']])->kind)->toBe('COMMITTED')
        ->and(DB::table('projects')->where('id', $this->project->id)->value('state'))->toBe('COMPLETED_NORMAL');
});

it('voids a billed invoice with Rp400,000 of a real payment applied only with that application re-targeted or released in the void\'s own command; no money is orphaned (criterion 8; annex 5; C-16; AX-22)', function () {
    $payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '400000', '400000');
    $application = DB::table('payment_applications')->where('payment_id', $payment->id)->value('id');
    $handle = RowHandle::of('payment_applications', $application);
    $void = fn (int $id, array $body) => $this->actingAs($this->actor)->post('/keuangan/invoice/'.InvoiceWorld::publicId($id).'/koreksi', ['command_id' => (string) Str::uuid(), 'lock_version' => DB::table('invoices')->where('id', $id)->value('lock_version'), 'alasan' => 'Salah catat nilai', 'tanggal' => $this->today, ...$body]);
    $replace = ['aksi' => 'BATAL', 'dasar' => 'ENTRY_ERROR_REPLACED', 'bukti' => InvoiceWorld::linkedEvidence($this->company->id, $this->project->id)->public_id, 'isi' => ['jenis' => 'VALUE_ONLY', 'tanggal' => $this->today, 'baris' => [['keterangan' => 'Nilai yang benar', 'nilai' => '1000000']]]];

    // The list left out: a stale one, the current list in the answer (ST-07).
    $void($this->invoice, $replace);
    expect(paymentAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'ST-07'])->and(DB::table('invoices')->where('id', $this->invoice)->value('state'))->toBe('ISSUED');

    $void($this->invoice, [...$replace, 'disposisi' => ['aplikasi' => [['id' => $handle, 'tindakan' => 'RETARGET']]]]);
    $replacement = (int) DB::table('invoices')->where('replaces_invoice_id', $this->invoice)->value('id');
    expect(paymentAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('invoices')->where('id', $this->invoice)->value('state'))->toBe('VOIDED')
        ->and(outstandingOf($replacement))->toBe('600000.00')
        ->and(sourceOf($payment->public_id)->invoice_applied_amount)->toBe('400000.00')->and(sourceOf($payment->public_id)->capacity_amount)->toBe('0.00')->and(sourceOf($payment->public_id)->net_amount)->toBe('400000.00')
        ->and(paymentDifferences())->toBe([]);

    // Released from an unbilled invoice instead: the money is credit traceable to its payment.
    $unbilled = $this->world->issued($this->project, '500000');
    $second = PaymentWorld::paid($this->world->actorId, $unbilled, '300000', '300000');
    $void($unbilled, ['aksi' => 'BATAL', 'dasar' => 'UNBILLED_ENTRY_ERROR', 'disposisi' => ['aplikasi' => [['id' => RowHandle::of('payment_applications', (int) DB::table('payment_applications')->where('payment_id', $second->id)->value('id')), 'tindakan' => 'RELEASE']]]]);
    expect(paymentAnswer()['kind'])->toBe('COMMITTED')->and(sourceOf($second->public_id)->capacity_amount)->toBe('300000.00')->and(sourceOf($second->public_id)->net_amount)->toBe('300000.00')->and(paymentDifferences())->toBe([]);
});

it('stands a payment without proof on its reason, flagged, its proof never a document the company issued (criterion 9; L-29)', function () {
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '500000', ['bukti' => []]))->assertSessionHasErrors('alasan_tanpa_bukti');
    expect(DB::table('payments')->count())->toBe(0);

    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '500000', ['bukti' => [], 'alasan_tanpa_bukti' => 'Bukti menyusul dari bendahara']));
    expect(DB::table('payments')->value('unevidenced_reason'))->toBe('Bukti menyusul dari bendahara');
    $this->actingAs($this->actor)->get('/keuangan/pembayaran?tab=tanpa_bukti')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1)->where('rows.0.unevidenced', 'Bukti menyusul dari bendahara'));

    // A Kuitansi is no evidence document: its identifier resolves to nothing.
    $kuitansi = PaymentWorld::proof($this->company->id, 'OTHER');
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '600000', ['bukti' => [$kuitansi->public_id]]))->assertSessionHasErrors('bukti');
});

it('voids a mistyped line only while uncited; its identity is one per real line (criterion 10; C-19; L-45)', function () {
    $statement = PaymentWorld::proof($this->company->id, 'BANK_STATEMENT');
    $admin = User::query()->findOrFail(Authorization::admin([$this->company->id], [...InvoiceWorld::CAPABILITIES, 'bank_statement.record', 'cash.correct'])->id);
    $body = ['command_id' => (string) Str::uuid(), 'perusahaan' => $this->companyKey, 'rekening' => PaymentWorld::accountHandle($this->account), 'bukti' => $statement->public_id, 'tanggal' => $this->today, 'jumlah' => '1000000', 'referensi' => 'TRX-9'];
    $this->actingAs($admin)->post('/keuangan/mutasi-bank', $body);
    expect(paymentAnswer()['kind'])->toBe('COMMITTED');
    $line = (int) DB::table('bank_statement_lines')->value('id');
    expect(DB::table('evidence_balances')->where('bank_statement_line_id', $line)->value('proven_amount'))->toBe('1000000.00');

    $this->actingAs($admin)->post('/keuangan/mutasi-bank', [...$body, 'command_id' => (string) Str::uuid()]);
    expect(paymentAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'C-19'])->and(DB::table('bank_statement_lines')->count())->toBe(1);

    $handle = RowHandle::of('bank_statement_lines', $line);
    PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '300000', ['accountId' => $this->account, 'lineId' => $line]);
    $this->actingAs($admin)->post("/keuangan/mutasi-bank/{$handle}/koreksi", ['command_id' => (string) Str::uuid(), 'aksi' => 'BATAL', 'alasan' => 'Salah ketik']);
    expect(paymentAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-19'])->and(paymentAnswer()['text'])->toContain('Rp300.000');

    $empty = PaymentWorld::line($this->company->id, $this->account, '500000', 'TRX-10');
    $this->actingAs($admin)->post('/keuangan/mutasi-bank/'.RowHandle::of('bank_statement_lines', $empty).'/koreksi', ['command_id' => (string) Str::uuid(), 'aksi' => 'BATAL', 'alasan' => 'Salah ketik']);
    expect(paymentAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('bank_statement_lines')->where('id', $empty)->value('state'))->toBe('VOIDED')
        ->and(DB::table('evidence_balances')->where('bank_statement_line_id', $empty)->value('proven_amount'))->toBe('0.00')->and(paymentDifferences())->toBe([]);
});

it('sets a flagged payment\'s citation when its line arrives, and corrects a wrong citation moving both guards, never by a contra (criterion 10; DATABASE §11.3; QS-12)', function () {
    $admin = User::query()->findOrFail(Authorization::admin([$this->company->id], [...InvoiceWorld::CAPABILITIES, 'bank_statement.record', 'cash.correct'])->id);
    $flagged = PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '300000', ['accountId' => $this->account])->resultId;
    $publicId = DB::table('payments')->where('id', $flagged)->value('public_id');
    $this->actingAs($admin)->get('/keuangan/mutasi-bank?tab=belum')->assertInertia(fn (AssertableInertia $page) => $page->component('FIN/BankStatement')->has('rows', 1)->where('rows.0.id', $publicId));

    $statement = PaymentWorld::proof($this->company->id, 'BANK_STATEMENT');
    $this->actingAs($admin)->post('/keuangan/mutasi-bank', ['command_id' => (string) Str::uuid(), 'perusahaan' => $this->companyKey, 'rekening' => PaymentWorld::accountHandle($this->account), 'bukti' => $statement->public_id, 'tanggal' => $this->today, 'jumlah' => '300000', 'referensi' => 'TRX-1', 'pembayaran' => [$publicId]]);
    expect(paymentAnswer()['kind'])->toBe('COMMITTED');
    $first = (int) DB::table('payments')->where('id', $flagged)->value('bank_statement_line_id');
    expect(DB::table('evidence_balances')->where('bank_statement_line_id', $first)->value('cited_amount'))->toBe('300000.00');

    $second = PaymentWorld::line($this->company->id, $this->account, '300000', 'TRX-2');
    $this->actingAs($admin)->post("/keuangan/mutasi-bank/{$publicId}/koreksi", ['command_id' => (string) Str::uuid(), 'aksi' => 'KUTIPAN', 'mutasi' => RowHandle::of('bank_statement_lines', $second), 'alasan' => 'Baris salah']);
    $payment = DB::table('payments')->where('id', $flagged)->sole();
    expect(paymentAnswer()['kind'])->toBe('COMMITTED')->and((int) $payment->bank_statement_line_id)->toBe($second)->and($payment->bank_citation_corrected_reason)->toBe('Baris salah')
        ->and(DB::table('evidence_balances')->where('bank_statement_line_id', $first)->value('cited_amount'))->toBe('0.00')->and(DB::table('evidence_balances')->where('bank_statement_line_id', $second)->value('cited_amount'))->toBe('300000.00')
        ->and(DB::table('financial_contras')->count())->toBe(0)->and(paymentDifferences())->toBe([]);
});

it('supersedes a claimed deduction by a new row, never below what is settled (criterion 10; DATABASE §11.4; C-22)', function () {
    $admin = User::query()->findOrFail(Authorization::admin([$this->company->id], [...InvoiceWorld::CAPABILITIES, 'bank_statement.record', 'cash.correct'])->id);
    $this->actingAs($this->actor)->post('/keuangan/pembayaran', paymentBody($this, '950000', ['penerapan' => [['invoice' => $this->invoiceKey, 'jumlah' => '900000']], 'potongan' => [['kelas' => 'FEE', 'biaya' => 'BIAYA_TRANSFER', 'jumlah' => '50000']]]));
    $payment = lastPayment();
    expect(sourceOf($payment->public_id)->claimed_deductions_amount)->toBe('50000.00');
    $deduction = DB::table('payment_claimed_deductions')->where('payment_id', $payment->id)->value('id');
    $settled = InvoiceWorld::settle($this->invoice, Payment::query()->findOrFail($payment->id), '30000');
    expect($settled)->toBeInt();
    $handle = RowHandle::of('payment_claimed_deductions', $deduction);

    $this->actingAs($admin)->post("/keuangan/mutasi-bank/{$payment->public_id}/koreksi", ['command_id' => (string) Str::uuid(), 'aksi' => 'POTONGAN', 'potongan' => $handle, 'jumlah' => '20000', 'alasan' => 'Salah angka']);
    expect(paymentAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-22'])->and(paymentAnswer()['text'])->toContain('Rp30.000');

    $this->actingAs($admin)->post("/keuangan/mutasi-bank/{$payment->public_id}/koreksi", ['command_id' => (string) Str::uuid(), 'aksi' => 'POTONGAN', 'potongan' => $handle, 'jumlah' => '40000', 'alasan' => 'Salah angka']);
    expect(paymentAnswer()['kind'])->toBe('COMMITTED')->and(sourceOf($payment->public_id)->claimed_deductions_amount)->toBe('40000.00')
        ->and(DB::table('payment_claimed_deductions')->where('supersedes_claimed_deduction_id', $deduction)->value('claimed_amount'))->toBe('40000.00');

    $this->actingAs($admin)->post("/keuangan/mutasi-bank/{$payment->public_id}/koreksi", ['command_id' => (string) Str::uuid(), 'aksi' => 'POTONGAN', 'potongan' => $handle, 'jumlah' => '45000', 'alasan' => 'Salah angka']);
    expect(paymentAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'ST-02']);
});

it('issues a receipt-mode Kuitansi only for a recorded payment, refused while a pre-payment one is open, linked money getting no second (criterion 11; BR-DOC-04; C-43)', function () {
    $payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '400000', '400000');
    $subject = ['payment_id' => $payment->public_id, 'invoice_id' => $this->invoiceKey];

    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-05', 'mode' => 'RECEIPT', 'subjek' => $subject]);
    $document = DB::table('documents')->where('payment_id', $payment->id)->where('document_type_code', 'DOC-05')->sole();
    $draft = DB::table('document_versions')->where('document_id', $document->id)->where('state', 'DRAFT')->sole();
    expect($draft->kuitansi_amount)->toBe('400000.00');

    $this->actingAs($this->actor)->post("/dokumen/{$document->public_id}/terbitkan", ['command_id' => (string) Str::uuid(), 'draf' => $draft->public_id, 'lock_version' => $draft->lock_version, 'tanggal' => $this->today]);
    expect(DB::table('document_versions')->where('document_id', $document->id)->where('state', 'ISSUED')->count())->toBe(1)->and(sourceOf($payment->public_id)->net_amount)->toBe('400000.00');
    $invoices = DB::table('invoice_balances')->where('invoice_id', $this->invoice)->value('outstanding');
    expect($invoices)->toBe('600000.00');
});

it('shows the pages by branch, the payment just recorded first, nothing without finance.view (criterion 12; NAV-046; S2-J06; SM-25)', function () {
    $older = PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '100000', ['businessDate' => $this->today]);
    PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '100001', ['businessDate' => $this->today]);
    $first = DB::table('payments')->where('id', $older->resultId)->value('public_id');

    $this->actingAs($this->actor)->get("/keuangan/pembayaran?tercatat={$first}")->assertInertia(fn (AssertableInertia $page) => $page->component('FIN/Payments')->where('rows.0.id', $first)->has('rows', 2));

    $stranger = User::query()->findOrFail(Authorization::admin([Company::factory()->createOne()->id], ['finance.view'])->id);
    $this->actingAs($stranger)->get('/keuangan/pembayaran')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0));
    $this->actingAs(User::query()->findOrFail(Authorization::admin([$this->company->id], ['projects.view'])->id))->get('/keuangan/pembayaran')->assertForbidden();
});

it('keeps the cursor in the address and answers a failed one as MSG-26 (NV-05; IP-18)', function () {
    foreach (range(1, 3) as $step) {
        PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, (string) (100000 + $step));
    }

    $last = DB::table('payments')->orderBy('id')->value('public_id');
    $this->actingAs($this->actor)->get("/keuangan/pembayaran?setelah={$last}")->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0)->where('cursor_failed', true));
    $middle = DB::table('payments')->orderByDesc('id')->value('public_id');
    $this->actingAs($this->actor)->get("/keuangan/pembayaran?setelah={$middle}")->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 2)->where('after', $middle)->where('cursor_failed', false));
    $this->actingAs($this->actor)->get('/keuangan/pembayaran?setelah=bukan-cursor')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0)->where('cursor_failed', true));
});

it('disposes a contra\'s settlements: moved to the re-recorded payment, or a residual on the case of their own project (criterion 7; AX-20; C-45; C-46; ER-06)', function () {
    $payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '500000', '500000');
    $model = Payment::query()->findOrFail($payment->id);
    PaymentWorld::claim($payment->id, '60000');
    $moved = InvoiceWorld::settle($this->invoice, $model, '50000');
    $application = (int) DB::table('payment_applications')->where('payment_id', $payment->id)->value('id');

    // Fully contra'd, the settlement left standing: refused (C-45).
    expect(PaymentWorld::contraInCommand($this->world->actorId, $payment->id, '500000', [$application => ['action' => ContraEntry::RELEASE, 'amount' => null]], [$moved => ContraEntry::KEEP]))->toBe('REJECTED C-45');

    $rerecorded = PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '500000', ['rerecords' => $payment->id, 'claimed' => [['class' => 'FEE', 'tax_type' => null, 'fee_type' => 'BIAYA_TRANSFER', 'amount' => '50000']]])->resultId;
    expect(PaymentWorld::contraInCommand($this->world->actorId, $payment->id, '500000', [$application => ['action' => ContraEntry::RELEASE, 'amount' => null]], [$moved => ContraEntry::MOVE], $rerecorded))->toBe('COMMITTED')
        ->and(DB::table('deduction_settlements')->where('id', $moved)->value('state'))->toBe('CONTRAED')
        ->and(DB::table('deduction_settlements')->where('payment_id', $rerecorded)->where('state', 'ACTIVE')->value('amount'))->toBe('50000.00')
        ->and(DB::table('application_source_balances')->where('payment_id', $rerecorded)->value('settled_amount'))->toBe('50000.00')
        ->and(DB::table('invoice_balances')->where('invoice_id', $this->invoice)->value('fee_settled_amount'))->toBe('50000.00')
        ->and(paymentDifferences())->toBe([]);

    $second = PaymentWorld::paid($this->world->actorId, $this->invoice, '400000', '400000');
    PaymentWorld::claim($second->id, '30000');
    $residual = InvoiceWorld::settle($this->invoice, Payment::query()->findOrFail($second->id), '30000');
    $secondApplication = (int) DB::table('payment_applications')->where('payment_id', $second->id)->value('id');
    expect(PaymentWorld::contraInCommand($this->world->actorId, $second->id, '400000', [$secondApplication => ['action' => ContraEntry::RELEASE, 'amount' => null]], [$residual => ContraEntry::RESIDUAL]))->toBe('COMMITTED');
    $case = (int) DB::table('deduction_settlements')->where('id', $residual)->value('correction_case_id');
    expect(DB::table('deduction_settlements')->where('id', $residual)->value('state'))->toBe('RESIDUAL')
        ->and(DB::table('correction_cases')->where('id', $case)->value('residual_settlement_amount'))->toBe('30000.00')
        ->and((int) DB::table('correction_cases')->where('id', $case)->value('project_id'))->toBe($this->project->id)
        ->and(paymentDifferences())->toBe([]);
});

it('still warns on a re-recording of a payment that stands, not on one wholly contra\'d (BD-02; L-22; ER-06)', function () {
    $first = PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '500000', ['accountId' => $this->account]);
    expect(PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '500000', ['accountId' => $this->account, 'rerecords' => $first->resultId])->code)->toBe('L-22');

    expect(PaymentWorld::contraInCommand($this->world->actorId, $first->resultId, '500000'))->toBe('COMMITTED');
    expect(PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '500000', ['accountId' => $this->account, 'rerecords' => $first->resultId])->kind)->toBe('COMMITTED');
});

it('fails the audit predicate until a flagged payment is evidenced, by a proof linked or a line cited, and shows a payment correction open (criterion 9; L-29; BR-PRJ-02; ER-06)', function () {
    $predicate = app(PaymentPredicate::class);
    $flagged = PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '100000', ['accountId' => $this->account, 'applications' => [$this->invoice => '100000']]);
    $key = DB::table('payments')->where('id', $flagged->resultId)->value('public_id');
    expect($predicate->states([$this->project->id], true)[$this->project->id][Predicates::AUDIT]->met())->toBeFalse();
    $this->actingAs($this->actor)->get('/keuangan/pembayaran')->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.without_proof', true));

    // ROUTE-231 links a proof to the payment; a document of no proving type answers as unknown.
    $other = PaymentWorld::proof($this->company->id, 'OTHER');
    expect($this->actingAs($this->actor)->post("/bukti/{$other->public_id}/tautan", ['command_id' => (string) Str::uuid(), 'action' => 'link', 'payment' => $key, 'role' => 'PRIMARY_PROOF'])->status())->toBe(404);
    $proof = PaymentWorld::proof($this->company->id);
    $this->actingAs($this->actor)->post("/bukti/{$proof->public_id}/tautan", ['command_id' => (string) Str::uuid(), 'action' => 'link', 'payment' => $key, 'role' => 'PRIMARY_PROOF', 'kembali' => '/keuangan/pembayaran']);
    expect(paymentAnswer()['kind'])->toBe('COMMITTED')->and($predicate->states([$this->project->id], true)[$this->project->id][Predicates::AUDIT]->met())->toBeTrue();
    $this->actingAs($this->actor)->get('/keuangan/pembayaran')->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.without_proof', false));

    expect(PaymentWorld::contraInCommand($this->world->actorId, $flagged->resultId, '100000', [(int) DB::table('payment_applications')->where('payment_id', $flagged->resultId)->value('id') => ['action' => ContraEntry::RELEASE, 'amount' => null]]))->toBe('COMMITTED');
});

it('takes new credit in a deactivated company only behind an existing receivable (BR-ACC-03; CS-03; ER-06)', function () {
    DB::table('companies')->where('id', $this->company->id)->update(['is_active' => false, 'deactivated_at' => now(), 'deactivated_by' => $this->world->actorId, 'deactivated_reason' => 'Tutup']);

    expect(PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '100000')->code)->toBe('C-54')
        ->and(PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '100000', ['applications' => [$this->invoice => '100000']])->kind)->toBe('COMMITTED');
});

it('asks for an override only of a payment that would otherwise commit (IP-05; ER-06)', function () {
    PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '2000000', ['accountId' => $this->account]);
    expect(PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '2000000', ['accountId' => $this->account, 'applications' => [$this->invoice => '1500000']])->code)->toBe('C-15')
        ->and(PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '2000000', ['accountId' => $this->account])->code)->toBe('L-22');
});

it('cites each flagged payment once however often it is named (C-19; HO-12; ER-06)', function () {
    $admin = User::query()->findOrFail(Authorization::admin([$this->company->id], [...InvoiceWorld::CAPABILITIES, 'bank_statement.record', 'cash.correct'])->id);
    $flagged = PaymentWorld::record($this->world->actorId, $this->company->id, $this->client, '400000', ['accountId' => $this->account])->resultId;
    $key = DB::table('payments')->where('id', $flagged)->value('public_id');
    $statement = PaymentWorld::proof($this->company->id, 'BANK_STATEMENT');
    $this->actingAs($admin)->post('/keuangan/mutasi-bank', ['command_id' => (string) Str::uuid(), 'perusahaan' => $this->companyKey, 'rekening' => PaymentWorld::accountHandle($this->account), 'bukti' => $statement->public_id, 'tanggal' => $this->today, 'jumlah' => '1000000', 'referensi' => 'TRX-3', 'pembayaran' => [$key, $key]]);
    $line = (int) DB::table('payments')->where('id', $flagged)->value('bank_statement_line_id');
    expect(DB::table('evidence_balances')->where('bank_statement_line_id', $line)->value('cited_amount'))->toBe('400000.00')->and(paymentDifferences())->toBe([]);
});

it('answers a replayed refusal with the current figure (CI-05; M-10; ER-06)', function () {
    $payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '300000');
    $body = ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'penerapan' => [['invoice' => $this->invoiceKey, 'jumlah' => '400000']]];
    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/terapkan", $body);
    expect(paymentAnswer()['state']['capacity'])->toBe('300000.00');
    $this->actingAs($this->actor)->post("/keuangan/pembayaran/{$payment->public_id}/terapkan", $body);
    expect(paymentAnswer()['code'])->toBe('C-13')->and(paymentAnswer()['state']['capacity'])->toBe('300000.00')->and(paymentAnswer()['text'])->toContain('Rp300.000');
});
