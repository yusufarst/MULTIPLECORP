<?php

use App\Modules\FIN\Models\Payment;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;

// TL-04, TL-06, TL-07 (TASK-053; ROUTE-048; ROUTE-256; ROUTE-264; NAV-048; INT-256; INT-264; SM-01; SM-04; SM-25; criteria 1, 2, 7, 8): PG-048 with credit per client and company traced to
// its source and per project; "Kembalikan Dana" and "Tahan Kredit" answering in place with each refusal; nothing without `finance.view`, the commands absent without `refund.record`,
// another company's credit unknown; one `command_id` replayed once.

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
    $this->payment = PaymentWorld::paid($this->world->actorId, $this->invoice, '1200000', '1000000');
    DB::table('evidence_links')->insert(['evidence_document_id' => PaymentWorld::proof($this->company->id)->id, 'evidence_scope' => 'COMPANY', 'company_id' => $this->company->id, 'role' => 'PRIMARY_PROOF', 'payment_id' => $this->payment->id,
        'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $this->world->actorId]);
    $this->actor = Authorization::admin([$this->company->id], ['finance.view', 'refund.record', 'payment.apply']);
    $this->account = PaymentWorld::accountHandle(PaymentWorld::account($this->company->id));
    $this->proof = PaymentWorld::proof($this->company->id, 'TRANSFER_PROOF')->public_id;
});

afterEach(fn () => @unlink($this->hotFile));

function creditBody(object $test, string $amount, array $more = []): array
{
    return ['command_id' => (string) Str::uuid(), 'jumlah' => $amount, 'rekening' => $test->account, 'bukti' => $test->proof, 'alasan' => 'Klien meminta dana kembali', 'tanggal' => BusinessDate::today(), ...$more];
}

function creditAnswer(object $test, object $actor, string $url, array $body): array
{
    $answer = $test->actingAs($actor)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer'];
    Auth::forgetGuards();

    return $answer;
}

it('shows PG-048 with the client\'s 200,000 credit traced to its payment and the project it counts for (NAV-048; CALC-06; DIR-104; criteria 1, 8)', function () {
    $this->actingAs($this->actor)->get('/keuangan/kredit-pelanggan')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('FIN/CustomerCredit', false)
        ->where('page.groups.0.credit', '200000.00')->where('page.groups.0.sources.0.id', $this->payment->public_id)->where('page.groups.0.sources.0.capacity', '200000.00')
        ->where('page.groups.0.projects.0.id', $this->project->public_id)->where('page.groups.0.projects.0.left', '200000.00')->where('page.groups.0.projects.0.held', '0.00')
        ->where('page.groups.0.can', ['apply' => true, 'refund' => true, 'hold' => true]));
});

it('refunds in place through ROUTE-256, each refusal named with its figure, one command_id replayed once (INT-256; C-13; CI-05; criterion 2)', function () {
    $url = "/keuangan/kredit-pelanggan/{$this->payment->public_id}/kembalikan";
    $over = creditAnswer($this, $this->actor, $url, creditBody($this, '200001'));
    $body = creditBody($this, '150000');

    expect($over['kind'])->toBe('REJECTED')->and($over['code'])->toBe('C-13')->and($over['text'])->toBe(__('credit.refusal.capacity', ['sisa' => '200000.00']))
        ->and(creditAnswer($this, $this->actor, $url, $body)['kind'])->toBe('COMMITTED')
        ->and(creditAnswer($this, $this->actor, $url, $body)['kind'])->toBe('COMMITTED')
        ->and(DB::table('disbursements')->where('kind', 'REFUND')->count())->toBe(1);

    $this->actingAs($this->actor)->post($url, creditBody($this, '1', ['bukti' => (string) Str::uuid()]))->assertSessionHasErrors('bukti');
    $this->actingAs($this->actor)->post($url, creditBody($this, '1', ['alasan' => 'x']))->assertSessionHasErrors('alasan');
    $this->actingAs($this->actor)->get('/keuangan/kredit-pelanggan')->assertInertia(fn (AssertableInertia $page) => $page->where('page.groups.0.credit', '50000.00')->where('page.groups.0.refunds.0.standing', true));
});

it('holds a project\'s credit in place through ROUTE-264 with its reason, a second hold refused as covered (INT-264; DIR-104; criterion 8)', function () {
    $url = "/keuangan/kredit-pelanggan/proyek/{$this->project->public_id}/tahan";

    expect(creditAnswer($this, $this->actor, $url, ['command_id' => (string) Str::uuid(), 'alasan' => 'Untuk proyek lanjutan'])['kind'])->toBe('COMMITTED')
        ->and(creditAnswer($this, $this->actor, $url, ['command_id' => (string) Str::uuid(), 'alasan' => 'Untuk proyek lanjutan'])['text'])->toBe(__('credit.refusal.covered'));

    $this->actingAs($this->actor)->get('/keuangan/kredit-pelanggan')->assertInertia(fn (AssertableInertia $page) => $page->where('page.groups.0.projects.0.held', '200000.00'));
});

it('shows nothing without finance.view and leaves the commands absent without refund.record; another company\'s credit unknown (AZ-01; SM-01; SM-04; SM-25; criterion 7)', function () {
    $outsider = Authorization::admin([$this->company->id], ['projects.view']);
    $viewer = Authorization::admin([$this->company->id], ['finance.view']);
    $stranger = Authorization::admin([InvoiceWorld::make()->company->id], ['finance.view', 'refund.record']);
    $refund = "/keuangan/kredit-pelanggan/{$this->payment->public_id}/kembalikan";
    $hold = "/keuangan/kredit-pelanggan/proyek/{$this->project->public_id}/tahan";

    $this->actingAs($outsider)->get('/keuangan/kredit-pelanggan')->assertForbidden();
    $this->actingAs($viewer)->get('/keuangan/kredit-pelanggan')->assertInertia(fn (AssertableInertia $page) => $page->where('page.groups.0.can', ['apply' => false, 'refund' => false, 'hold' => false]));
    $this->actingAs($viewer)->post($refund, creditBody($this, '1'))->assertForbidden();
    $this->actingAs($viewer)->post($hold, ['command_id' => (string) Str::uuid(), 'alasan' => 'Untuk proyek lanjutan'])->assertForbidden();
    $this->actingAs($stranger)->get('/keuangan/kredit-pelanggan')->assertInertia(fn (AssertableInertia $page) => $page->where('page.groups', []));
    $this->actingAs($stranger)->post($refund, creditBody($this, '1'))->assertNotFound();
    $this->actingAs($stranger)->post($hold, ['command_id' => (string) Str::uuid(), 'alasan' => 'Untuk proyek lanjutan'])->assertNotFound();

    expect(DB::table('disbursements')->where('kind', 'REFUND')->count())->toBe(0)->and(Payment::query()->count())->toBe(1);
});
