<?php

use App\Modules\FIN\Actions\HoldCredit;
use App\Modules\PRJ\Data\CompletionSnapshot;
use App\Modules\PRJ\Queries\ProjectCompletion;
use Database\Factories\OPS\CorrectionCaseFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;

// TL-04, TL-06, TL-07 (TASK-055; ROUTE-012; ROUTE-161; ROUTE-162; NAV-012; INT-161; INT-162; AX-25; AX-26; C-38; C-39; L-12; OD-12; criteria 1–7, 13, 14): PG-012's ten conditions derived each
// time it is shown; "Selesaikan Proyek" only when all ten hold at commit, one blocker per condition naming it; the Owner's Force Complete bound to the snapshot shown, a changed one
// CONFLICT, its record keeping the unmet conditions and residual obligations; an Admin's Force Complete denied; one `command_id` replayed once.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->project = $this->world->project();
    $this->actor = Authorization::admin([$this->company->id], ['projects.view', 'projects.complete', 'finance.view']);
    $this->owner = Authorization::owner();
    $this->page = "/proyek/{$this->project->public_id}/penyelesaian";
});

afterEach(fn () => @unlink($this->hotFile));

function completionAnswer(object $test, object $actor, string $url, array $body = []): array
{
    $answer = $test->actingAs($actor)->followingRedirects()->post($url, ['command_id' => (string) Str::uuid(), ...$body])->viewData('page')['flash']['answer'];
    Auth::forgetGuards();

    return $answer;
}

function completionState(object $test): string
{
    return (string) DB::table('projects')->where('id', $test->project->id)->value('state');
}

it('shows PG-012\'s ten conditions, each with its state and, unmet, what is missing and where it is resolved (J-PRJ-03; UXI-26; L-01; criterion 1)', function () {
    $this->actingAs($this->actor)->get($this->page)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('PRJ/Completion', false)
        ->has('conditions', 10)->where('conditions.0.key', 'fulfilment')->where('conditions.0.met', false)->where('conditions.0.items.0.key', 'projects.item.handovers_open')
        ->where('conditions.0.href', "/proyek/{$this->project->public_id}")->where('conditions.5.key', 'billing')->where('conditions.5.met', true)
        ->where('conditions.4.key', 'administration')->where('conditions.4.met', false)->where('conditions.4.items.0.key', 'projects.item.requirements_invoice')
        ->where('unmet', ['fulfilment', 'administration'])->where('can', ['complete' => true, 'force' => false])->where('force', null));

    // Derived again each time: the line's remainder cancelled, the condition holds at once.
    InvoiceWorld::readyToComplete($this->project->id);
    $this->actingAs($this->actor)->get($this->page)->assertInertia(fn (AssertableInertia $page) => $page->where('conditions.0.met', true)->where('unmet', []));
});

it('completes only on the explicit decision once all ten hold, and never by itself (AX-25; L-12; C-39; criteria 2, 3)', function () {
    $refused = completionAnswer($this, $this->actor, "/proyek/{$this->project->public_id}/selesaikan");
    expect($refused['kind'])->toBe('REJECTED')->and($refused['code'])->toBe('C-39')->and($refused['state']['unmet'])->toBe(['fulfilment', 'administration'])->and($refused['text'])->toContain(__('journey.pred.fulfilment'))
        ->and(completionState($this))->toBe('ACTIVE')->and(DB::table('project_state_transitions')->where('project_id', $this->project->id)->whereIn('transition_kind', ['COMPLETE_NORMAL', 'FORCE_COMPLETE'])->count())->toBe(0);

    InvoiceWorld::readyToComplete($this->project->id);
    expect(completionState($this))->toBe('ACTIVE');

    $this->actingAs($this->actor)->post("/proyek/{$this->project->public_id}/selesaikan", ['command_id' => (string) Str::uuid()])->assertRedirect("/proyek/{$this->project->public_id}");
    $transition = DB::table('project_state_transitions')->where('project_id', $this->project->id)->where('transition_kind', 'COMPLETE_NORMAL')->first();
    expect(completionState($this))->toBe('COMPLETED_NORMAL')->and($transition)->not->toBeNull()->and($transition->decided_by)->toBe($this->actor->id)
        ->and(json_decode((string) $transition->predicate_snapshot, true)['format'])->toBe(CompletionSnapshot::FORMAT)
        ->and(DB::table('audit_events')->where('action', 'PRJ.ProjectCompleted')->count())->toBe(1);

    // On a completed project the page offers no completion and the command is a stale state.
    $this->actingAs($this->actor)->get($this->page)->assertInertia(fn (AssertableInertia $page) => $page->where('can.complete', false)->where('record.kind', 'COMPLETE_NORMAL'));
    expect(completionAnswer($this, $this->actor, "/proyek/{$this->project->public_id}/selesaikan")['code'])->toBe('ST-02');
});

it('names one blocker per money condition — billing, receivable, credit, open cases — and delivery or payment alone never completes (C-39; WF-PRJ-03; criteria 2, 14)', function () {
    // Confirmed at the 1,000,000 it is invoiced for, its service handed over: only the money facts remain (ADM's default invoice item met once invoiced).
    $this->project = $this->world->project(['SERVICE'], '4');
    InvoiceWorld::readyToComplete($this->project->id, true);
    $unmet = fn (): array => app(ProjectCompletion::class)->read($this->project->id)['unmet'];

    $invoice = $this->world->issued($this->project, '1000000');
    expect($unmet())->toBe(['billing']);

    InvoiceWorld::bill($invoice, now()->toDateString(), '2099-12-31');
    expect($unmet())->toBe(['receivable']);

    // Paid in full and more: the receivable holds, the client's credit left for the project blocks predicate 9 until it is held (DIR-104).
    $payment = PaymentWorld::paid($this->world->actorId, $invoice, '1200000', '1000000');
    DB::table('evidence_links')->insert(['evidence_document_id' => PaymentWorld::proof($this->company->id)->id, 'evidence_scope' => 'COMPANY', 'company_id' => $this->company->id, 'role' => 'PRIMARY_PROOF', 'payment_id' => $payment->id,
        'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $this->world->actorId]);
    expect($unmet())->toBe(['payment'])
        ->and(completionAnswer($this, $this->actor, "/proyek/{$this->project->public_id}/selesaikan")['state']['unmet'])->toBe(['payment']);

    $holder = Authorization::admin([$this->company->id], ['finance.view', 'refund.record']);
    expect(app(HoldCredit::class)->run((string) Str::uuid(), $holder->id, $this->project->id, $this->company->id, 'Untuk proyek lanjutan', ['t' => 1])->kind)->toBe('COMMITTED')
        ->and($unmet())->toBe([]);

    CorrectionCaseFactory::new()->createOne(['company_id' => $this->company->id, 'project_id' => $this->project->id, 'case_kind' => 'PAYMENT_CORRECTION']);
    expect($unmet())->toBe(['returns', 'payment']);
});

it('lets only the Owner force-complete, bound to the snapshot shown: a reason required, a changed snapshot CONFLICT, the record keeping what stays (AX-26; ST-04; C-38; BR-PRJ-04; criteria 4, 5, 7)', function () {
    $invoice = $this->world->issued($this->project, '1000000');
    InvoiceWorld::bill($invoice, now()->toDateString(), '2099-12-31');
    $url = "/proyek/{$this->project->public_id}/selesaikan-paksa";
    $shown = $this->actingAs($this->owner)->get($this->page)->viewData('page')['props']['force'];
    expect($shown['unmet'])->toBe(['fulfilment', 'administration', 'receivable'])->and(collect($shown['obligations'])->firstWhere('kind', 'receivable')['amount'])->toBe('1000000.00');

    $this->actingAs($this->owner)->post($url, ['command_id' => (string) Str::uuid(), 'snapshot' => $shown['hash']])->assertSessionHasErrors('alasan');

    // A blocker that changed between the page and the command: CONFLICT with the current snapshot, the project unchanged (M-27).
    InvoiceWorld::apply($invoice, InvoiceWorld::payment($invoice, '400000'), '400000');
    $stale = completionAnswer($this, $this->owner, $url, ['alasan' => 'Klien tidak dapat dihubungi', 'snapshot' => $shown['hash']]);
    expect($stale['kind'])->toBe('CONFLICT')->and($stale['code'])->toBe('ST-04')->and(completionState($this))->toBe('ACTIVE');

    $current = $this->actingAs($this->owner)->get($this->page)->viewData('page')['props']['force'];
    expect($current['hash'])->not->toBe($shown['hash'])
        ->and(completionAnswer($this, $this->owner, $url, ['alasan' => 'Klien tidak dapat dihubungi', 'snapshot' => $current['hash']])['kind'])->toBe('COMMITTED');

    $transition = DB::table('project_state_transitions')->where('project_id', $this->project->id)->where('transition_kind', 'FORCE_COMPLETE')->first();
    expect(completionState($this))->toBe('COMPLETED_FORCED')->and($transition->reason)->toBe('Klien tidak dapat dihubungi')->and($transition->confirmed_snapshot_hash)->toBe($current['hash'])
        ->and(json_decode((string) $transition->unmet_predicates, true))->toBe(['fulfilment', 'administration', 'receivable'])
        ->and(collect(json_decode((string) $transition->residual_obligations, true))->firstWhere('kind', 'receivable')['amount'])->toBe('600000.00')
        // Nothing fabricated: the receivable stays outstanding and ageing.
        ->and(DB::table('invoice_balances')->where('invoice_id', $invoice)->value('outstanding'))->toBe('600000.00');

    $this->actingAs($this->owner)->get($this->page)->assertInertia(fn (AssertableInertia $page) => $page->where('record.kind', 'FORCE_COMPLETE')->where('record.unmet', ['fulfilment', 'administration', 'receivable'])->where('record.obligations.0.kind', 'receivable'));
});

it('denies an Admin\'s Force Complete whatever is granted, and no payload names a state or a snapshot (OD-12; WS-02; SM-04; criterion 6)', function () {
    $hash = app(ProjectCompletion::class)->read($this->project->id)['hash'];
    $granted = Authorization::admin([$this->company->id], ['projects.view', 'projects.complete', 'projects.force_complete']);
    $body = ['command_id' => (string) Str::uuid(), 'alasan' => 'Paksa', 'snapshot' => $hash, 'state' => 'COMPLETED_FORCED', 'transition_kind' => 'FORCE_COMPLETE'];

    expect($this->actingAs($granted)->post("/proyek/{$this->project->public_id}/selesaikan-paksa", $body)->status())->toBeIn([403, 404])
        ->and($this->actingAs($this->actor)->post("/proyek/{$this->project->public_id}/selesaikan-paksa", $body)->status())->toBeIn([403, 404]);
    $this->actingAs($granted)->get($this->page)->assertInertia(fn (AssertableInertia $page) => $page->where('can.force', false)->where('force', null));

    // A forged state on the normal completion is ignored: the command reads the predicates, not the payload.
    $this->actingAs($this->actor)->post("/proyek/{$this->project->public_id}/selesaikan", ['command_id' => (string) Str::uuid(), 'state' => 'COMPLETED_NORMAL']);
    expect(completionState($this))->toBe('ACTIVE');

    // Without `projects.complete` the command is forbidden; another company's project is unknown.
    $reader = Authorization::admin([$this->company->id], ['projects.view']);
    $this->actingAs($reader)->post("/proyek/{$this->project->public_id}/selesaikan", ['command_id' => (string) Str::uuid()])->assertForbidden();
    $other = InvoiceWorld::make()->project();
    $this->actingAs($this->actor)->get("/proyek/{$other->public_id}/penyelesaian")->assertNotFound();
    $this->actingAs($this->actor)->post("/proyek/{$other->public_id}/selesaikan", ['command_id' => (string) Str::uuid()])->assertNotFound();
});

it('replays one command_id for either command with its recorded outcome, one transition (CI-05; criterion 13)', function () {
    InvoiceWorld::readyToComplete($this->project->id);
    $command = (string) Str::uuid();

    foreach ([1, 2] as $send) {
        expect(completionAnswer($this, $this->actor, "/proyek/{$this->project->public_id}/selesaikan", ['command_id' => $command])['kind'])->toBe('COMMITTED');
    }

    expect(DB::table('project_state_transitions')->where('project_id', $this->project->id)->where('transition_kind', 'COMPLETE_NORMAL')->count())->toBe(1);
});

it('refuses a FORCE_COMPLETE written without its reason, snapshot or hash (C-38; criterion 4)', function () {
    $row = ['project_id' => $this->project->id, 'company_id' => $this->company->id, 'from_state' => 'ACTIVE', 'to_state' => 'COMPLETED_FORCED', 'transition_kind' => 'FORCE_COMPLETE', 'decided_by' => $this->owner->id,
        'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $this->owner->id, 'reason' => 'Paksa', 'predicate_snapshot' => '{}', 'confirmed_snapshot_hash' => str_repeat('a', 64)];

    foreach (['reason', 'predicate_snapshot', 'confirmed_snapshot_hash'] as $missing) {
        expect(fn () => DB::transaction(fn () => DB::table('project_state_transitions')->insert([...$row, $missing => null, 'command_id' => (string) Str::uuid()])))->toThrow(QueryException::class);
    }
});
