<?php

use Database\Factories\DOC\DocumentTypeFactory;
use Tests\Support\Authorization;
use Tests\Support\ClientReturnWorld;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\CostWorld;
use Tests\Support\DatabaseTruncation;
use Tests\Support\DeliveryWorld;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\StockWorld;

// TL-05 (TASK-048; AX-10; AX-36; PX-08; M-07; LK-19; LK-24; architecture challenge 3b) on real PostgreSQL sessions: two
// returns of the same delivered units meet at the shipment's guards — one commits, the other is REJECTED with what is
// left —; a sales return and an undelivered return of one shipment each take only their own units; a return racing a
// cost correction of its lot follows the cascade, the costs reconciling in either order. The reconciliation suite runs
// after every scenario (tests/Pest.php).

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company, ClientReturnWorld::CAPABILITIES)->id;
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->line = $this->demand['line'];
    $this->lot = StockWorld::lot($this->actor, $this->company, $this->demand['product'], $this->demand['unit'], $this->rack, '10', '1000');
    $this->proof = ClientReturnWorld::proof($this->company);
});

/** `shipped` dispatched and `delivered` of them received by the client: the shipment. */
function racedShipment(object $test, string $shipped, string $delivered): object
{
    expect(DispatchWorld::dispatch($test->actor, $test->company->id, $test->line, $test->demand['unit'], $shipped, [DispatchWorld::pick($test->lot, $test->rack, $shipped)]))->toBe('COMMITTED');
    $shipment = DispatchWorld::last($test->line);
    expect(DeliveryWorld::record($test->actor, $test->line, [[$shipment->dispatch, $delivered]], DeliveryWorld::proof($test->company)))->toBe('COMMITTED');

    return $shipment;
}


it('lets one of two returns of the same delivered units commit, the other REJECTED with nothing left, never both (C-30; LK-19; LK-24; criterion 1)', function () {
    $shipment = racedShipment($this, '2', '2');
    [$actor, $company, $rack, $proof, $dispatch, $consumption] = [$this->actor, $this->company->id, $this->rack, $this->proof, $shipment->dispatch, $shipment->consumption];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => ClientReturnWorld::restore($actor, $company, $dispatch, [DispatchWorld::item($consumption, $rack, '2')], $proof), 'the first holds the shipment'),
        'second' => static function () use ($actor, $company, $rack, $proof, $dispatch, $consumption): string {
            Barrier::await('the first holds the shipment');

            return ClientReturnWorld::restore($actor, $company, $dispatch, [DispatchWorld::item($consumption, $rack, '2')], $proof);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('C-30')
        ->and(DeliveryWorld::delivered($this->line))->toBe('0.000')
        ->and(ClientReturnWorld::restored($consumption))->toBe('2.000')
        ->and(CostWorld::differences())->toBe([]);
});

it('commits a client\'s return and its share of the units racing in both orders, each restoring its own units once (C-27; LK-19; LK-24)', function (bool $salesFirst) {
    $shipment = racedShipment($this, '3', '2');
    [$actor, $company, $rack, $proof, $dispatch, $consumption] = [$this->actor, $this->company->id, $this->rack, $this->proof, $shipment->dispatch, $shipment->consumption];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => $salesFirst
            ? ClientReturnWorld::restore($actor, $company, $dispatch, [DispatchWorld::item($consumption, $rack, '2')], $proof)
            : DispatchWorld::restore($actor, $company, $dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($consumption, $rack, '1')]), 'the first holds the shipment'),
        'second' => static function () use ($salesFirst, $actor, $company, $rack, $proof, $dispatch, $consumption): string {
            Barrier::await('the first holds the shipment');

            return $salesFirst
                ? DispatchWorld::restore($actor, $company, $dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($consumption, $rack, '1')])
                : ClientReturnWorld::restore($actor, $company, $dispatch, [DispatchWorld::item($consumption, $rack, '2')], $proof);
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'COMMITTED'])
        ->and(ClientReturnWorld::restored($consumption))->toBe('3.000')
        ->and(ClientReturnWorld::dispatched($this->line))->toBe('0.000')
        ->and(DeliveryWorld::delivered($this->line))->toBe('0.000')
        ->and(CostWorld::differences())->toBe([]);
})->with(['the client\'s return first' => true, 'the undelivered return first' => false]);

it('follows the cascade when a return races a cost correction of its lot, the costs reconciling in either order (M-07)', function (bool $returnFirst) {
    $shipment = racedShipment($this, '2', '2');
    [$actor, $company, $rack, $proof, $dispatch, $consumption, $lot] = [$this->actor, $this->company->id, $this->rack, $this->proof, $shipment->dispatch, $shipment->consumption, $this->lot];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => $returnFirst
            ? ClientReturnWorld::restore($actor, $company, $dispatch, [DispatchWorld::item($consumption, $rack, '1')], $proof)
            : DispatchWorld::correctLotCost($actor, $lot, '1200'), 'the first holds the lot'),
        'second' => static function () use ($returnFirst, $actor, $company, $rack, $proof, $dispatch, $consumption, $lot): string {
            Barrier::await('the first holds the lot');

            return $returnFirst
                ? DispatchWorld::correctLotCost($actor, $lot, '1200')
                : ClientReturnWorld::restore($actor, $company, $dispatch, [DispatchWorld::item($consumption, $rack, '1')], $proof);
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'COMMITTED'])
        ->and(ClientReturnWorld::restored($consumption))->toBe('1.000')
        ->and(CostWorld::differences())->toBe([]);
})->with(['the return first' => true, 'the correction first' => false]);
