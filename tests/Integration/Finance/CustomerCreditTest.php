<?php

use App\Modules\FIN\Actions\HoldCredit;
use App\Modules\FIN\Actions\RefundCredit;
use App\Modules\FIN\Models\Payment;
use App\Modules\FIN\Queries\CustomerCredit;
use App\Modules\FIN\Services\CreditProjects;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-053; WF-FIN-06; AX-23; L-29; L-46; C-13; C-44; DIR-104; annex 3): customer credit derived per client and company and traced to its source; a refund a Cash-Out that commits
// fully backed by one application of evidenced credit or not at all; a project's credit held with its reason, new credit blocking again; the reconciliation suite after each.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->project = $this->world->project();
    $this->invoice = $this->world->issued($this->project, '1000000');
    $this->refunder = Authorization::admin([$this->company->id], ['finance.view', 'refund.record', 'payment.apply'])->id;
});

/** An evidenced payment of the invoice's client and company, applying `$applied` of it to the invoice. */
function creditPayment(object $test, string $amount, ?string $applied = null, ?int $invoice = null): Payment
{
    $payment = PaymentWorld::paid($test->world->actorId, $invoice ?? $test->invoice, $amount, $applied);
    DB::table('evidence_links')->insert(['evidence_document_id' => PaymentWorld::proof($payment->company_id)->id, 'evidence_scope' => 'COMPANY', 'company_id' => $payment->company_id, 'role' => 'PRIMARY_PROOF', 'payment_id' => $payment->id,
        'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $test->world->actorId]);

    return $payment;
}

/** ROUTE-256's action on the payment's credit: its word. */
function creditRefund(object $test, Payment $payment, string $amount, ?string $commandId = null, ?int $evidence = null): string
{
    $source = ['payment' => $payment->id, 'credit' => null, 'company' => $payment->company_id, 'client' => $payment->client_organization_id, 'name' => $payment->public_id];

    return InvoiceWorld::word(app(RefundCredit::class)->run($commandId ?? (string) Str::uuid(), $test->refunder, $source, $amount, PaymentWorld::account($payment->company_id), $evidence ?? PaymentWorld::proof($payment->company_id, 'TRANSFER_PROOF')->id,
        'Klien meminta dana kembali', BusinessDate::today(), false, ['amount' => $amount, 'run' => $commandId ?? (string) Str::uuid()]));
}

/** ROUTE-264's action on the project: its word. */
function creditHold(object $test, int $project, ?string $commandId = null): string
{
    return InvoiceWorld::word(app(HoldCredit::class)->run($commandId ?? (string) Str::uuid(), $test->refunder, $project, $test->company->id, 'Kredit disimpan untuk proyek berikutnya', ['run' => $commandId ?? (string) Str::uuid()]));
}

function creditSource(int $payment): object
{
    return DB::table('application_source_balances')->where('payment_id', $payment)->first() ?? (object) [];
}

it('FX-01 3: shows a 1,200,000 payment\'s 200,000 credit traced to it, refunds it as one application to a Cash-Out, and never also applies it to an invoice (annex 3; BR-FIN-05; BR-FIN-03; criterion 1)', function () {
    $payment = creditPayment($this, '1200000', '1000000');
    $screen = app(CustomerCredit::class)->screen($this->refunder, null);

    expect($screen['groups'][0]['credit'])->toBe('200000.00')
        ->and($screen['groups'][0]['sources'][0])->toMatchArray(['id' => $payment->public_id, 'kind' => 'PAYMENT', 'capacity' => '200000.00', 'evidenced' => true])
        ->and(creditRefund($this, $payment, '200000'))->toBe('COMMITTED');

    $refund = DB::table('disbursements')->where('kind', 'REFUND')->sole();

    expect((string) $refund->amount)->toBe('200000.00')
        ->and(DB::table('money_fact_balances')->where('disbursement_id', $refund->id)->first(['fact_kind', 'amount', 'backed_amount', 'shortfall_amount']))->toEqual((object) ['fact_kind' => 'REFUND', 'amount' => '200000.00', 'backed_amount' => '200000.00', 'shortfall_amount' => '0.00'])
        ->and(DB::table('payment_applications')->where('refund_disbursement_id', $refund->id)->where('target_kind', 'REFUND')->where('state', 'ACTIVE')->count())->toBe(1)
        ->and(creditSource($payment->id)->capacity_amount)->toBe('0.00')
        ->and(DB::table('evidence_links')->where('disbursement_id', $refund->id)->where('role', 'PRIMARY_PROOF')->exists())->toBeTrue()
        ->and(DB::table('audit_events')->where('action', 'FIN.CreditRefunded')->value('authority_class'))->toBe('ADM_PLUS')
        ->and(InvoiceWorld::applyInCommand($this->world->actorId, $this->world->issued($this->project, '500000'), $payment->id, '1'))->toContain('C-13');
});

it('refuses a refund of a payment without proof, above the source\'s capacity, or without transfer evidence, writing nothing (L-29; C-13; C-44; criterion 2)', function () {
    $unevidenced = PaymentWorld::paid($this->world->actorId, $this->invoice, '1100000', '1000000');
    $payment = creditPayment($this, '300000');

    expect(creditRefund($this, $unevidenced, '100000'))->toContain('L-29')
        ->and(creditRefund($this, $payment, '300001'))->toContain('C-13')
        ->and(creditRefund($this, $payment, '100000', evidence: PaymentWorld::proof($this->company->id)->id))->toContain('L-29')
        ->and(DB::table('disbursements')->where('kind', 'REFUND')->count())->toBe(0)
        ->and(creditSource($payment->id)->refund_backed_amount)->toBe('0.00');
});

it('refunds once for one command_id replayed (CI-05; criterion 6)', function () {
    $payment = creditPayment($this, '500000');
    $command = (string) Str::uuid();

    expect(creditRefund($this, $payment, '200000', $command))->toBe('COMMITTED')
        ->and(creditRefund($this, $payment, '200000', $command))->toBe('COMMITTED')
        ->and(DB::table('disbursements')->where('kind', 'REFUND')->count())->toBe(1)
        ->and(creditSource($payment->id)->capacity_amount)->toBe('300000.00');
});

it('holds a project\'s credit with its reason, covering what has arisen; holding again is refused until new credit arises, which blocks again even after the held credit was spent (DIR-104; WF-FIN-06; criterion 8)', function () {
    $payment = creditPayment($this, '1200000', '1000000');

    expect(CreditProjects::ofProjects([$this->project->id]))->toBe([$this->project->id => ['arisen' => '200000.00', 'left' => '200000.00']])
        ->and(creditHold($this, $this->project->id))->toBe('COMMITTED')
        ->and((string) DB::table('projects')->where('id', $this->project->id)->value('credit_held_amount'))->toBe('200000.00')
        ->and(DB::table('audit_events')->where('action', 'FIN.CreditHeld')->first(['reason', 'authority_class']))->toEqual((object) ['reason' => 'Kredit disimpan untuk proyek berikutnya', 'authority_class' => 'ADM_PLUS'])
        ->and(creditHold($this, $this->project->id))->toContain('C-14');

    // The held credit spent, a later overpayment's credit arises for the project and blocks again.
    expect(creditRefund($this, $payment, '200000'))->toBe('COMMITTED')
        ->and(creditHold($this, $this->project->id))->toContain('C-14');
    $later = $this->world->issued($this->project, '100000');
    creditPayment($this, '150000', '100000', $later);

    expect(CreditProjects::ofProjects([$this->project->id]))->toBe([$this->project->id => ['arisen' => '250000.00', 'left' => '50000.00']])
        ->and(creditHold($this, $this->project->id))->toBe('COMMITTED')
        ->and((string) DB::table('projects')->where('id', $this->project->id)->value('credit_held_amount'))->toBe('250000.00');
});

it('refuses a hold on a project that is not active or has no credit, and on a source with no application, which counts for no project (DIR-104; ROUTE-264)', function () {
    creditPayment($this, '500000');

    expect(CreditProjects::ofProjects([$this->project->id]))->toBe([])
        ->and(creditHold($this, $this->project->id))->toContain('C-14');

    creditPayment($this, '1100000', '1000000');
    DB::table('projects')->where('id', $this->project->id)->update(['state' => 'COMPLETED_NORMAL']);

    expect(creditHold($this, $this->project->id))->toContain('C-14')
        ->and((string) DB::table('projects')->where('id', $this->project->id)->value('credit_held_amount'))->toBe('0.00');
});

it('counts a payment across two projects for the project of its earliest application only, never twice (DIR-104; criterion 8)', function () {
    $other = $this->world->project();
    $second = $this->world->issued($other, '300000');
    $invoice = DB::table('invoices')->where('id', $this->invoice)->first(['company_id', 'client_organization_id']);
    $outcome = PaymentWorld::record($this->world->actorId, (int) $invoice->company_id, (int) $invoice->client_organization_id, '1500000', ['applications' => [$this->invoice => '1000000', $second => '300000']]);
    expect($outcome->kind)->toBe('COMMITTED');

    $figures = CreditProjects::ofProjects([$this->project->id, $other->id]);

    expect($figures)->toBe([$this->project->id => ['arisen' => '200000.00', 'left' => '200000.00']]);
});
