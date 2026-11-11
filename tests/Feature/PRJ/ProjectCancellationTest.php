<?php

use App\Modules\PRJ\Services\ResidualCommands;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\CostWorld;
use Tests\Support\DropshipWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReservationWorld;

// TL-04, TL-06, TL-07 (TASK-056; ROUTE-163; INT-163; AX-27; CM-29; CM-30; CM-31; L-10; L-11; W4-23; criteria 1–5, 7–9): "Batalkan Proyek" moving an ACTIVE project to CANCELLED with
// its reason and releasing every active reservation in the same action; each refusal; the companion corrections listed, none performed; the facts that stay true shown; a
// residual command and a late correction accepted with the project staying CANCELLED; one `command_id` replayed once.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
});

afterEach(fn () => @unlink($this->hotFile));

function cancellationAnswer(object $test, object $actor, string $url, array $body): array
{
    $answer = $test->actingAs($actor)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer'];
    Auth::forgetGuards();

    return $answer;
}

function cancellationBody(object $project, array $more = []): array
{
    return ['command_id' => (string) Str::uuid(), 'alasan' => 'Klien membatalkan kontrak', 'versi' => (int) DB::table('projects')->where('id', $project->id)->value('lock_version'), ...$more];
}

it('cancels an ACTIVE project with its reason and releases every active reservation in the same action, RESERVED falling (AX-27; CM-29; BR-RSV-02; criterion 1)', function () {
    $world = ReservationWorld::make();
    $world->stock('20');
    $project = $world->project(['WAREHOUSE', 'WAREHOUSE']);
    expect(ReservationWorld::reserve($world->actorId, ReservationWorld::line($project, 1), '3'))->toBe('COMMITTED')
        ->and(ReservationWorld::reserve($world->actorId, ReservationWorld::line($project, 2), '2'))->toBe('COMMITTED')
        ->and(ReservationWorld::guard($world->productId())->reserved_qty)->toBe('5.000');
    $actor = Authorization::admin([$world->company->id], ['projects.view', 'projects.cancel']);

    $this->actingAs($actor)->get("/proyek/{$project->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.cancel', true));
    $answer = cancellationAnswer($this, $actor, "/proyek/{$project->public_id}/batalkan", cancellationBody($project));

    $transition = DB::table('project_state_transitions')->where('project_id', $project->id)->where('transition_kind', 'CANCEL')->get();
    expect($answer['kind'])->toBe('COMMITTED')->and(DB::table('projects')->where('id', $project->id)->value('state'))->toBe('CANCELLED')
        ->and($transition)->toHaveCount(1)->and($transition[0]->reason)->toBe('Klien membatalkan kontrak')
        ->and(DB::table('reservations as r')->join('project_lines as l', 'l.id', '=', 'r.project_line_id')->where('l.project_id', $project->id)->where('r.state', 'ACTIVE')->count())->toBe(0)
        ->and(DB::table('reservation_events')->where('reason', 'Proyek dibatalkan.')->where('kind', 'RELEASE')->count())->toBe(2)
        ->and(ReservationWorld::guard($world->productId())->reserved_qty)->toBe('0.000')
        ->and(DB::table('audit_events')->where('action', 'PRJ.ProjectCancelled')->value('authority_class'))->toBe('ADM_PLUS');

    $this->actingAs($actor)->get("/proyek/{$project->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.cancel', false)->where('project.state', 'CANCELLED'));
});

it('refuses each way out: no reason at the field, a draft, an already closed project, a stale version — nothing written (AX-27; ST-01; INT-163; criteria 5, 7)', function () {
    $world = InvoiceWorld::make();
    $project = $world->project();
    $actor = Authorization::admin([$world->company->id], ['projects.view', 'projects.cancel']);
    $url = "/proyek/{$project->public_id}/batalkan";

    $this->actingAs($actor)->post($url, cancellationBody($project, ['alasan' => '']))->assertSessionHasErrors('alasan');
    $stale = cancellationAnswer($this, $actor, $url, cancellationBody($project, ['versi' => 999999]));
    expect($stale['kind'])->toBe('CONFLICT')->and($stale['code'])->toBe('ST-01')->and(DB::table('projects')->where('id', $project->id)->value('state'))->toBe('ACTIVE');

    expect(cancellationAnswer($this, $actor, $url, cancellationBody($project))['kind'])->toBe('COMMITTED');
    $again = cancellationAnswer($this, $actor, $url, cancellationBody($project));
    expect($again['kind'])->toBe('REJECTED')->and($again['code'])->toBe('AX-27')->and($again['text'])->toBe(__('projects.refusal.cancel_closed'));

    $completed = $world->project();
    InvoiceWorld::readyToComplete($completed->id);
    expect(InvoiceWorld::completeInCommand($world->actorId, $completed->id))->toBe('COMMITTED')
        ->and(cancellationAnswer($this, $actor, "/proyek/{$completed->public_id}/batalkan", cancellationBody($completed))['code'])->toBe('AX-27');

    $draft = CostWorld::make($world->company)->project(null, 'DRAFT');
    expect(cancellationAnswer($this, $actor, "/proyek/{$draft->public_id}/batalkan", cancellationBody($draft))['text'])->toBe(__('projects.refusal.cancel_draft'))
        ->and(DB::table('project_state_transitions')->where('transition_kind', 'CANCEL')->count())->toBe(1);
});

it('lists the companion corrections as links to their own commands and performs none of them (J-PRJ-04; IP-19; criterion 2)', function () {
    $world = InvoiceWorld::make();
    $project = $world->project();
    $invoice = $world->issued($project, '1000000');
    $actor = Authorization::admin([$world->company->id], ['projects.view', 'projects.cancel', 'projects.cancel_scope', 'finance.view', 'stock.view', 'sales_return.record']);
    $version = (string) $this->actingAs($actor)->get("/proyek/{$project->public_id}")->viewData('page')['version'];
    $companions = $this->actingAs($actor)->get("/proyek/{$project->public_id}", ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'PRJ/Journey', 'X-Inertia-Partial-Data' => 'companions'])->json('props.companions');

    // Nothing delivered yet: no return to record; the whole scope remains to cancel (what is actually being undone).
    expect(array_column($companions, 'kind'))->toBe(['invoice', 'scope'])
        ->and($companions[0]['href'])->toBe('/keuangan/invoice/'.InvoiceWorld::publicId($invoice));

    expect(cancellationAnswer($this, $actor, "/proyek/{$project->public_id}/batalkan", cancellationBody($project))['kind'])->toBe('COMMITTED')
        ->and(DB::table('invoices')->where('id', $invoice)->value('state'))->toBe('ISSUED')
        ->and(DB::table('project_line_balances as b')->join('project_lines as l', 'l.id', '=', 'b.project_line_id')->where('l.project_id', $project->id)->sum('b.cancelled_qty_base'))->toEqual(0);
});

it('leads a direct shipment\'s return to PG-032\'s own flow, not the warehouse return with no eligible line (J-PRJ-04; IP-19; TASK-046; criterion 2)', function () {
    DocumentTypeFactory::ensure('DOC-03');
    $world = InvoiceWorld::make();
    $direct = DropshipWorld::make($world, '10', '10');
    expect(DropshipWorld::confirm(DropshipWorld::actor($world->company)->id, $direct, '4'))->toBe('COMMITTED');
    $project = DB::table('projects')->where('id', $direct['project']->id)->first(['public_id']);
    $actor = Authorization::admin([$world->company->id], ['projects.view', 'projects.cancel', 'projects.cancel_scope', 'stock.view', 'sales_return.record']);
    $version = (string) $this->actingAs($actor)->get("/proyek/{$project->public_id}")->viewData('page')['version'];
    $companions = $this->actingAs($actor)->get("/proyek/{$project->public_id}", ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'PRJ/Journey', 'X-Inertia-Partial-Data' => 'companions'])->json('props.companions');

    expect(array_column($companions, 'kind'))->toBe(['dropship_return', 'scope'])
        ->and($companions[0]['href'])->toBe("/proyek/{$project->public_id}/kirim-langsung");
});

it('keeps the facts that stay true after cancellation — open receivables active and collectable — and accepts a residual payment and a late correction, the project staying CANCELLED (L-10; L-11; CM-32; FX-03; criteria 3, 4, 9)', function () {
    $world = InvoiceWorld::make();
    $project = $world->project();
    $invoice = $world->issued($project, '1000000');
    InvoiceWorld::bill($invoice, now()->toDateString(), '2099-12-31');
    $actor = Authorization::admin([$world->company->id], ['projects.view', 'projects.cancel', 'finance.view']);
    expect(cancellationAnswer($this, $actor, "/proyek/{$project->public_id}/batalkan", cancellationBody($project))['kind'])->toBe('COMMITTED');

    $version = (string) $this->actingAs($actor)->get("/proyek/{$project->public_id}")->viewData('page')['version'];
    $record = $this->actingAs($actor)->get("/proyek/{$project->public_id}", ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'PRJ/Journey', 'X-Inertia-Partial-Data' => 'cancelled'])->json('props.cancelled');
    expect($record['reason'])->toBe('Klien membatalkan kontrak')->and(collect($record['obligations'])->firstWhere('kind', 'receivable')['amount'])->toBe('1000000.00');

    // The receivable stays collectable: a payment is a residual command, accepted without any state change.
    $payment = PaymentWorld::record($world->actorId, $world->company->id, (int) DB::table('invoices')->where('id', $invoice)->value('client_organization_id'), '400000', ['accountId' => PaymentWorld::account($world->company->id), 'applications' => [$invoice => '400000']]);
    expect($payment->kind)->toBe('COMMITTED')->and(DB::table('invoice_balances')->where('invoice_id', $invoice)->value('outstanding'))->toBe('600000.00')
        ->and(DB::table('projects')->where('id', $project->id)->value('state'))->toBe('CANCELLED');

    // FX-03's late correction: the billing corrected changes only what it corrects; no transition leaves CANCELLED.
    $evidence = InvoiceWorld::linkedEvidence($world->company->id, $project->id)->id;
    expect(InvoiceWorld::correctBillingInCommand($world->actorId, $invoice, $evidence, now()->toDateString(), '2099-11-30'))->toBe('COMMITTED')
        ->and(DB::table('projects')->where('id', $project->id)->value('state'))->toBe('CANCELLED')
        ->and(DB::table('project_state_transitions')->where('project_id', $project->id)->where('from_state', 'CANCELLED')->count())->toBe(0)
        ->and(ResidualCommands::decision('CANCELLED', 'FIN.CorrectBilling', true))->toBe(ResidualCommands::ACCEPT);
});

it('keeps PG-011 within three deferred groups, a cancelled project\'s record arriving with the page (PF-34; UXN-14; UXS-44; L-10; TASK-089)', function () {
    $world = InvoiceWorld::make();
    $project = $world->project();
    $actor = Authorization::admin([$world->company->id], ['projects.view', 'projects.cancel', 'finance.view']);

    // An ACTIVE project: the three groups, and no cancelled record.
    $active = $this->actingAs($actor)->get("/proyek/{$project->public_id}")->assertOk()->viewData('page');
    expect(array_keys($active['deferredProps']))->toEqualCanonicalizing(['predicates', 'documents', 'history'])
        ->and($active['props']['cancelled'])->toBeNull();

    // Cancelled: still the three groups; the record is in the page's own answer.
    expect(cancellationAnswer($this, $actor, "/proyek/{$project->public_id}/batalkan", cancellationBody($project))['kind'])->toBe('COMMITTED');
    $cancelled = $this->actingAs($actor)->get("/proyek/{$project->public_id}")->assertOk()->viewData('page');
    expect(array_keys($cancelled['deferredProps']))->toEqualCanonicalizing(['predicates', 'documents', 'history'])
        ->and($cancelled['props']['cancelled']['reason'])->toBe('Klien membatalkan kontrak');
});

it('shows no control and forbids the route without projects.cancel; another company\'s project is unknown; one command_id replayed once (W4-23; AZ-11; CI-05; criteria 7, 8)', function () {
    $world = InvoiceWorld::make();
    $project = $world->project();
    $reader = Authorization::admin([$world->company->id], ['projects.view', 'projects.manage', 'projects.complete']);

    $this->actingAs($reader)->get("/proyek/{$project->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.cancel', false));
    $this->actingAs($reader)->post("/proyek/{$project->public_id}/batalkan", cancellationBody($project))->assertForbidden();

    $actor = Authorization::admin([$world->company->id], ['projects.view', 'projects.cancel']);
    $other = InvoiceWorld::make()->project();
    $this->actingAs($actor)->post("/proyek/{$other->public_id}/batalkan", cancellationBody($other))->assertNotFound();

    $body = cancellationBody($project);
    expect(cancellationAnswer($this, $actor, "/proyek/{$project->public_id}/batalkan", $body)['kind'])->toBe('COMMITTED')
        ->and(cancellationAnswer($this, $actor, "/proyek/{$project->public_id}/batalkan", $body)['kind'])->toBe('COMMITTED')
        ->and(DB::table('project_state_transitions')->where('project_id', $project->id)->where('transition_kind', 'CANCEL')->count())->toBe(1)
        ->and(cancellationAnswer($this, $actor, "/proyek/{$project->public_id}/batalkan", [...$body, 'alasan' => 'Alasan lain sama sekali'])['kind'])->toBe('CONFLICT');
});
