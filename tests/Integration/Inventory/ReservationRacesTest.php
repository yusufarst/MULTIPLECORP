<?php

use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\ReservationWorld;

// TL-05 (TASK-038; M-02; AX-01; AX-02; BR-RSV-04; IP-20; criteria 5, 6, 9, 15) on real PostgreSQL sessions: two reservations
// of the last unit for different projects end one COMMITTED and one REJECTED with the current availability — never both,
// never CONFLICT — whichever holds the product guard first, for a counted and a serialized product; a release racing a
// consumption of the same reservation leaves its units released or consumed, never both; a supply reduction below the
// reservations commits only with the actor's cuts covering it (C-06), the product guard's CHECK refusing it without them
// (C-04). After each, the reconciliation suite finds RESERVED and each line's reserved quantity equal to the reservations.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    $this->world = ReservationWorld::make();
});

/** The reconciliation suite's differences for the two guards a reservation moves (C-61; BR-INV-08; criterion 15). */
function reservationDifferences(): array
{
    return [...(new Reconciler)->differences(DB::connection(), Families::named('stock_product_balances') ?? throw new RuntimeException('product guard')),
        ...(new Reconciler)->differences(DB::connection(), Families::named('project_line_balances') ?? throw new RuntimeException('line guard'))];
}

it('lets one of two reservations of the last unit commit, the other REJECTED with the availability, in both orders (M-02; ST-06; criterion 5)', function (bool $serialized, string $holder) {
    if ($serialized) {
        DB::table('products')->where('id', $this->world->productId())->update(['is_serialized' => true]);
    }

    $this->world->stock('1');
    $lines = ['first' => ReservationWorld::line($this->world->project()), 'second' => ReservationWorld::line($this->world->project())];
    $actor = $this->world->actorId;
    $waiter = $holder === 'first' ? 'second' : 'first';
    [$held, $waiting] = [$lines[$holder], $lines[$waiter]];

    $results = Sessions::run([
        $holder => static fn (): string => ReservationWorld::hold(static fn (): string => ReservationWorld::reserve($actor, $held, '1'), 'the first reservation holds the product guard'),
        $waiter => static function () use ($actor, $waiting): string {
            Barrier::await('the first reservation holds the product guard');

            return ReservationWorld::reserve($actor, $waiting, '1');
        },
    ]);

    expect($results[$holder])->toBe('COMMITTED')
        ->and($results[$waiter])->toBe('REJECTED C-04')
        ->and(DB::table('reservations')->where('state', 'ACTIVE')->pluck('project_line_id')->all())->toBe([$held])
        ->and(ReservationWorld::guard($this->world->productId())->reserved_qty)->toBe('1.000')
        ->and(reservationDifferences())->toBe([]);
})->with([
    'counted, the first project first' => [false, 'first'],
    'counted, the second project first' => [false, 'second'],
    'serialized, the first project first' => [true, 'first'],
    'serialized, the second project first' => [true, 'second'],
]);

it('leaves a reservation released or consumed, never both, when a release races a consumption of it (AX-02; AX-03; C-05; criterion 6)', function (string $holder) {
    $this->world->stock('10');
    $line = ReservationWorld::line($this->world->project());
    expect(ReservationWorld::reserve($this->world->actorId, $line, '4'))->toBe('COMMITTED');
    $reservation = ReservationWorld::active($line);
    $actor = $this->world->actorId;
    $waiter = $holder === 'release' ? 'consume' : 'release';

    // Each session builds its own command: a closure made here would carry the test's scope into the other process.
    $results = Sessions::run([
        $holder => static fn (): string => ReservationWorld::hold(static fn (): string => $holder === 'release'
            ? ReservationWorld::release($actor, $reservation, null) : ReservationWorld::consume($actor, $line, '4'), 'the first command holds the reservation'),
        $waiter => static function () use ($waiter, $actor, $reservation, $line): string {
            Barrier::await('the first command holds the reservation');

            return $waiter === 'release' ? ReservationWorld::release($actor, $reservation, null) : ReservationWorld::consume($actor, $line, '4');
        },
    ]);
    $events = DB::table('reservation_events')->where('reservation_id', $reservation)->orderBy('id')->pluck('kind')->all();

    expect($results[$holder])->toBe('COMMITTED')
        ->and(ReservationWorld::guard($this->world->productId())->reserved_qty)->toBe('0.000')
        ->and(reservationDifferences())->toBe([]);

    if ($holder === 'release') {
        // The consumption found nothing reserved: its dispatch takes unreserved stock (AX-03).
        expect($results['consume'])->toBe('COMMITTED')
            ->and($events)->toBe(['CREATE', 'RELEASE'])
            ->and(DB::table('reservations')->where('id', $reservation)->value('state'))->toBe('RELEASED')
            ->and(DB::table('dispatch_lines')->value('reserved_qty_base'))->toBe('0.000');
    } else {
        expect($results['release'])->toBe('REJECTED C-05')
            ->and($events)->toBe(['CREATE', 'CONSUME'])
            ->and(DB::table('reservations')->where('id', $reservation)->value('state'))->toBe('CONSUMED');
    }
})->with(['the release first' => 'release', 'the consumption first' => 'consume']);

it('commits a supply reduction below the reservations only with the actor\'s cuts covering it (annex 9; BR-RSV-04; C-06; IP-20; criterion 9)', function () {
    $lot = $this->world->stock('8');
    [$first, $second] = [ReservationWorld::line($this->world->project()), ReservationWorld::line($this->world->project())];
    expect(ReservationWorld::reserve($this->world->actorId, $first, '5'))->toBe('COMMITTED')
        ->and(ReservationWorld::reserve($this->world->actorId, $second, '3'))->toBe('COMMITTED');
    [$a, $b] = [ReservationWorld::active($first), ReservationWorld::active($second)];
    $reduce = fn (array $cuts): string => ReservationWorld::reduceSupply($this->world->actorId, $this->world->company->id, $this->world->productId(), $lot, $this->world->locationId, '2', $cuts);
    $movements = DB::table('stock_movements')->count();

    // RESERVED 8 of 8: turning 2 unusable needs cuts of 2 — without them the guard's CHECK refuses; short of it C-06.
    expect($reduce([]))->toBe('REJECTED C-04')
        ->and($reduce([$a => '1']))->toBe('REJECTED C-06')
        ->and(DB::table('stock_movements')->count())->toBe($movements);

    // The actor's choice, no order proposed: 1 from each.
    expect($reduce([$a => '1', $b => '1']))->toBe('COMMITTED');
    $movement = (int) DB::table('stock_movements')->where('movement_type', 'CONDITION_CHANGE')->value('id');

    expect(ReservationWorld::guard($this->world->productId()))->toEqual((object) ['on_hand_qty' => '8.000', 'unusable_qty' => '2.000', 'reserved_qty' => '6.000'])
        ->and(DB::table('reservations')->whereIn('id', [$a, $b])->orderBy('id')->pluck('remaining_qty_base')->all())->toBe(['4.000', '2.000'])
        ->and(DB::table('reservation_events')->where('kind', 'CUT')->pluck('cut_by_movement_id')->unique()->values()->all())->toBe([$movement])
        ->and(DB::table('project_line_balances')->whereIn('project_line_id', [$first, $second])->orderBy('project_line_id')->pluck('reserved_qty_base')->all())->toBe(['4.000', '2.000'])
        ->and(reservationDifferences())->toBe([]);
});
