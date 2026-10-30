<?php

use App\Modules\FIN\Models\Invoice;
use App\Support\BusinessDate;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\InvoiceWorld;

// TL-05 (TASK-050; AX-17; AX-24; NX-09; M-26; BD-07; C-15; C-42; FX-03's recovery after a write-off; TEST_STRATEGY §5) on real PostgreSQL sessions: two billings of one invoice, the second a CONFLICT; two holds, one refused; two write-offs past the outstanding, one refused;
// a hold or write-off racing a completion — the completion's own command is TASK-055's, so a synthetic one that re-reads the predicate under LK-01 stands in — in both orders; a supersession at a real commit. The reconciliation suite after each.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();

    // The truncation between tests removes the seeded catalogue.
    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->world = InvoiceWorld::make();
    $this->project = $this->world->project();
    $this->today = BusinessDate::today();
    $this->owner = Authorization::owner()->id;
});

function receivableRaceDifferences(): array
{
    return (new Reconciler)->differences(DB::connection(), Families::named('invoice_balances') ?? throw new RuntimeException('invoice guard'));
}

it('bills an invoice once when two press at once, the second a CONFLICT with the dates (BD-07; C-41; AX-17; criterion 2)', function () {
    $id = $this->world->issued($this->project, '1000000');
    $actor = $this->world->actorId;
    $today = $this->today;

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::billInCommand($actor, $id, $today, '2099-12-31'), 'the first billing holds the invoice'),
        'second' => static function () use ($actor, $id, $today): string {
            Barrier::await('the first billing holds the invoice');

            return InvoiceWorld::billInCommand($actor, $id, $today, '2099-11-30');
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'CONFLICT BD-07'])
        ->and(DB::table('invoice_billing_acts')->where('invoice_id', $id)->sole()->due_date)->toBe('2099-12-31')
        ->and(DB::table('invoice_balances')->where('invoice_id', $id)->value('current_due_date'))->toBe('2099-12-31');
});

it('keeps one uncleared hold under two at once (C-42; NX-09; criterion 5)', function () {
    $id = $this->world->issued($this->project, '1000000');
    InvoiceWorld::bill($id, $this->today, '2099-12-31');
    $actor = $this->world->actorId;

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::holdInCommand($actor, $id), 'the first hold holds the invoice'),
        'second' => static function () use ($actor, $id): string {
            Barrier::await('the first hold holds the invoice');

            return InvoiceWorld::holdInCommand($actor, $id);
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED C-42'])->and(DB::table('receivable_dispute_holds')->where('invoice_id', $id)->count())->toBe(1);
});

it('lets one of two write-offs past the outstanding through, the other refused with what remains (C-15; AX-24; LK-25; criterion 6)', function () {
    $id = $this->world->issued($this->project, '1000000');
    InvoiceWorld::bill($id, $this->today, '2099-12-31');
    $owner = $this->owner;
    $today = $this->today;

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::writeOffInCommand($owner, $id, '700000', $today), 'the first write-off holds the invoice'),
        'second' => static function () use ($owner, $id, $today): string {
            Barrier::await('the first write-off holds the invoice');

            return InvoiceWorld::writeOffInCommand($owner, $id, '700000', $today);
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED C-15'])
        ->and(DB::table('invoice_balances')->where('invoice_id', $id)->value('outstanding'))->toBe('300000.00')
        ->and(DB::table('receivable_writeoffs')->where('invoice_id', $id)->count())->toBe(1)
        ->and(receivableRaceDifferences())->toBe([]);
});

it('fails a completion racing a hold: it re-reads its predicates under LK-01 (M-26; C-39; L-12; criterion 5)', function () {
    $id = $this->world->issued($this->project, '1000000');
    InvoiceWorld::bill($id, $this->today, '2099-12-31');
    $payment = InvoiceWorld::payment($id, '999999');
    InvoiceWorld::apply($id, $payment, '999999');
    $actor = $this->world->actorId;
    $project = $this->project->id;

    $results = Sessions::run([
        'hold' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::holdInCommand($actor, $id), 'the hold holds the project'),
        'completion' => static function () use ($actor, $project): string {
            Barrier::await('the hold holds the project');

            return InvoiceWorld::completeInCommand($actor, $project);
        },
    ]);

    expect($results)->toBe(['hold' => 'COMMITTED', 'completion' => 'REJECTED C-39'])->and(DB::table('projects')->where('id', $project)->value('state'))->not->toBe('COMPLETED_NORMAL');
});

it('lets a completion through only once a write-off it waited on has cleared the receivable (M-26; AX-24; L-17)', function () {
    $id = $this->world->issued($this->project, '1000000');
    InvoiceWorld::bill($id, $this->today, '2099-12-31');
    $owner = $this->owner;
    $actor = $this->world->actorId;
    $project = $this->project->id;
    $today = $this->today;

    $results = Sessions::run([
        'write-off' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::writeOffInCommand($owner, $id, '1000000', $today), 'the write-off holds the project'),
        'completion' => static function () use ($actor, $project): string {
            Barrier::await('the write-off holds the project');

            return InvoiceWorld::completeInCommand($actor, $project);
        },
    ]);

    expect($results)->toBe(['write-off' => 'COMMITTED', 'completion' => 'COMMITTED'])->and(DB::table('projects')->where('id', $project)->value('state'))->toBe('COMPLETED_NORMAL')->and(receivableRaceDifferences())->toBe([]);
});

it('revalidates a project a billing correction finds completed, the correction second (M-26; SF-REVAL; BR-CR-05; criterion 2)', function () {
    $id = $this->world->issued($this->project, '1000000');
    InvoiceWorld::bill($id, $this->today, '2099-12-31');
    InvoiceWorld::apply($id, InvoiceWorld::payment($id, '1000000'), '1000000');
    $evidence = InvoiceWorld::linkedEvidence($this->world->company->id, $this->project->id)->id;
    $actor = $this->world->actorId;
    $project = $this->project->id;
    $today = $this->today;

    $results = Sessions::run([
        'completion' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::completeInCommand($actor, $project), 'the completion holds the project'),
        'correction' => static function () use ($actor, $id, $evidence, $today): string {
            Barrier::await('the completion holds the project');

            return InvoiceWorld::correctBillingInCommand($actor, $id, $evidence, $today, '2099-11-30');
        },
    ]);

    expect($results)->toBe(['completion' => 'COMMITTED', 'correction' => 'COMMITTED'])
        ->and(DB::table('projects')->where('id', $project)->value('state'))->toBe('ACTIVE')
        ->and(DB::table('project_state_transitions')->where('project_id', $project)->where('to_state', 'ACTIVE')->where('from_state', 'COMPLETED_NORMAL')->value('correction_reference'))->toStartWith('FIN.BillingCorrected:');
});

it('commits FX-03\'s recovery after a write-off at a real commit, one action, the guards reconciled (FX-03; AX-24; L-17; criterion 7)', function () {
    $id = $this->world->issued($this->project, '1000000');
    InvoiceWorld::bill($id, $this->today, '2099-12-31');
    expect(InvoiceWorld::writeOffInCommand($this->owner, $id, '1000000', $this->today))->toBe('COMMITTED');
    $writeoff = (int) DB::table('receivable_writeoffs')->where('invoice_id', $id)->value('id');
    $payment = InvoiceWorld::recovery($id, '400000');

    expect(Invoice::query()->findOrFail($id)->state)->toBe('ISSUED')
        ->and(DB::table('invoice_balances')->where('invoice_id', $id)->value('outstanding'))->toBe('0.00')
        ->and(InvoiceWorld::supersedeInCommand($this->owner, $id, $writeoff, $payment->id, '400000', $this->today))->toBe('COMMITTED');

    $balance = DB::table('invoice_balances')->where('invoice_id', $id)->sole();
    expect($balance->written_off_amount)->toBe('600000.00')->and($balance->applied_amount)->toBe('400000.00')->and($balance->outstanding)->toBe('0.00')
        ->and(DB::table('receivable_writeoffs')->where('id', $writeoff)->value('amount'))->toBe('1000000.00')
        ->and(receivableRaceDifferences())->toBe([]);
});

it('refuses a supersession racing another for the same recovered money: the source guard holds (C-13; C-15; LK-26)', function () {
    $id = $this->world->issued($this->project, '1000000');
    InvoiceWorld::bill($id, $this->today, '2099-12-31');
    expect(InvoiceWorld::writeOffInCommand($this->owner, $id, '1000000', $this->today))->toBe('COMMITTED');
    $writeoff = (int) DB::table('receivable_writeoffs')->where('invoice_id', $id)->value('id');
    $payment = InvoiceWorld::recovery($id, '400000')->id;
    $owner = $this->owner;
    $today = $this->today;

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::supersedeInCommand($owner, $id, $writeoff, $payment, '400000', $today), 'the first supersession holds the invoice'),
        'second' => static function () use ($owner, $id, $writeoff, $payment, $today): string {
            Barrier::await('the first supersession holds the invoice');

            return InvoiceWorld::supersedeInCommand($owner, $id, $writeoff, $payment, '400000', $today);
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED C-15'])
        ->and(DB::table('financial_contras')->where('receivable_writeoff_id', $writeoff)->count())->toBe(1)
        ->and(receivableRaceDifferences())->toBe([]);
});
