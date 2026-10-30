<?php

use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\HandoverWorld;
use Tests\Support\ReservationWorld;

// TL-05 (TASK-047; AX-09; AX-14; PX-08; C-30; L-49; CM-27) on real PostgreSQL sessions: a handover racing a remaining-scope
// cancellation of its line meets it at LK-01 and LK-19 — the demand never overrun, the cancellation never below what is handed
// over, whichever holds first —; two handovers of the last quantity end one COMMITTED and one REJECTED; two reversals of one line
// end one reversal line. After each, the reconciliation suite finds the guard's handed-over quantity equal to its lines.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    $this->world = HandoverWorld::make();
    $this->project = $this->world->project();
    $this->line = HandoverWorld::line($this->project);
});

/** The reconciliation suite's differences for the line guard a handover moves (C-30). */
function handoverDifferences(): array
{
    return (new Reconciler)->differences(DB::connection(), Families::named('project_line_balances') ?? throw new RuntimeException('line guard'));
}

it('never overruns the demand when a handover races a remaining-scope cancellation of its line, in both orders (AX-09; AX-14; PX-08)', function (string $holder) {
    $actor = $this->world->actorId;
    $project = $this->project->id;
    $line = $this->line;
    $waiter = $holder === 'handover' ? 'cancel' : 'handover';

    $results = Sessions::run([
        $holder => static fn (): string => ReservationWorld::hold(static fn (): string => HandoverWorld::handoverOrCancel($holder, $actor, $project, $line, '7'), 'the first command holds the line'),
        $waiter => static function () use ($waiter, $actor, $project, $line): string {
            Barrier::await('the first command holds the line');

            return HandoverWorld::handoverOrCancel($waiter, $actor, $project, $line, '7');
        },
    ]);

    $balance = DB::table('project_line_balances')->where('project_line_id', $line)->first(['handed_over_qty_base', 'cancelled_qty_base']);

    expect($results[$holder])->toBe('COMMITTED')
        ->and($results[$waiter])->toBe($holder === 'handover' ? 'REJECTED ST-06' : 'REJECTED C-30')
        ->and($balance->handed_over_qty_base)->toBe($holder === 'handover' ? '7.000' : '0.000')
        ->and($balance->cancelled_qty_base)->toBe($holder === 'handover' ? '0.000' : '7.000')
        ->and(handoverDifferences())->toBe([]);
})->with(['the handover first' => 'handover', 'the cancellation first' => 'cancel']);

it('lets one of two handovers of the last quantity commit, the other REJECTED with the remainder (AX-09; C-30; M-02)', function () {
    $actor = $this->world->actorId;
    $project = $this->project->id;
    $line = $this->line;

    $results = Sessions::run([
        'first' => static fn (): string => ReservationWorld::hold(static fn (): string => HandoverWorld::record($actor, $project, $line, '6'), 'the first handover holds the line'),
        'second' => static function () use ($actor, $project, $line): string {
            Barrier::await('the first handover holds the line');

            return HandoverWorld::record($actor, $project, $line, '5');
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toBe('REJECTED C-30')
        ->and(HandoverWorld::handedOver($line))->toBe('6.000')->and(handoverDifferences())->toBe([]);
});

it('catches a likely duplicate between two sessions at the anchor, the second REJECTED L-49 (BD-13; L-49; LR-08)', function () {
    $actor = $this->world->actorId;
    $project = $this->project->id;
    $line = $this->line;

    $results = Sessions::run([
        'first' => static fn (): string => ReservationWorld::hold(static fn (): string => HandoverWorld::record($actor, $project, $line, '2'), 'the first handover holds the line'),
        'second' => static function () use ($actor, $project, $line): string {
            Barrier::await('the first handover holds the line');

            return HandoverWorld::record($actor, $project, $line, '2');
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toBe('REJECTED L-49')
        ->and(HandoverWorld::handedOver($line))->toBe('2.000')->and(handoverDifferences())->toBe([]);
});

it('ends two reversals of one line with one reversal line (C-30; CM-27)', function () {
    $actor = $this->world->actorId;
    $project = $this->project->id;
    $line = $this->line;
    expect(HandoverWorld::record($actor, $project, $line, '4'))->toBe('COMMITTED');
    $handover = (int) DB::table('service_handovers')->where('project_id', $project)->value('id');
    $handed = (int) DB::table('service_handover_lines')->where('service_handover_id', $handover)->value('id');

    $results = Sessions::run([
        'first' => static fn (): string => ReservationWorld::hold(static fn (): string => HandoverWorld::reverse($actor, $project, $handover, $handed), 'the first reversal holds the line'),
        'second' => static function () use ($actor, $project, $handover, $handed): string {
            Barrier::await('the first reversal holds the line');

            return HandoverWorld::reverse($actor, $project, $handover, $handed);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toBe('REJECTED C-30')
        ->and(DB::table('service_handover_lines')->where('reverses_line_id', $handed)->count())->toBe(1)
        ->and(HandoverWorld::handedOver($line))->toBe('0.000')->and(handoverDifferences())->toBe([]);
});
