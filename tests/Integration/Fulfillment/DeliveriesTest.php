<?php

use App\Modules\FUL\Services\DeliveryFacts;
use Brick\Math\BigDecimal;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\CostWorld;
use Tests\Support\DatabaseTruncation;
use Tests\Support\DeliveryWorld;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\StockWorld;

// TL-05 (TASK-045; WF-FUL-02; AX-09; NX-15; AX-30; PX-01–PX-08; C-24; C-27; C-30; CM-06–CM-08; annex 15): what reached the client recorded against what was shipped, never above it and never touching stock; a refusal closed and its goods back once through the dispatch's restoration; a loss in transit reclassified on the project; an entry error corrected exactly; the reconciliation suite after each.

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
    $this->actor = ReceivingWorld::actor($this->company, DeliveryWorld::CAPABILITIES)->id;
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->line = $this->demand['line'];
    $this->product = $this->demand['product'];
    $this->unit = $this->demand['unit'];
    $this->lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '10', '1000');
    $this->proof = DeliveryWorld::proof($this->company);
});

function deliveryClean(): void
{
    expect(StockWorld::ledgerDifferences())->toBe([])
        ->and(CostWorld::differences())->toBe([]);
}

function onHand(int $product): string
{
    return (string) DB::table('stock_product_balances')->where('product_id', $product)->value('on_hand_qty');
}

function shipped(object $test, string $qty): object
{
    expect(DispatchWorld::dispatch($test->actor, $test->company->id, $test->line, $test->unit, $qty, [DispatchWorld::pick($test->lot, $test->rack, $qty)]))->toBe('COMMITTED');

    return DispatchWorld::last($test->line);
}

it('delivers 2 of 5 shipped, then the 3 left, each with recipient, condition and proof, never above what was shipped and never moving stock (AC-07; BR-INV-02; C-30; criteria 1, 2)', function () {
    $shipment = shipped($this, '5');
    $stock = onHand($this->product);

    expect(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '2']], $this->proof))->toBe('COMMITTED')
        ->and(DeliveryWorld::delivered($this->line))->toBe('2.000')
        ->and(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '4']], $this->proof))->toContain('C-30')
        ->and(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '3']], $this->proof))->toBe('COMMITTED')
        ->and(DeliveryWorld::delivered($this->line))->toBe('5.000')
        ->and(onHand($this->product))->toBe($stock)
        ->and(DB::table('evidence_links')->whereNotNull('delivery_id')->where('role', 'PRIMARY_PROOF')->count())->toBe(2)
        ->and(DB::table('audit_events')->where('action', 'FUL.DeliveryRecorded')->count())->toBe(2);
    deliveryClean();
});

it('replays one command_id with its recorded outcome, delivering once (CI-05; AC-07; criterion 2)', function () {
    $shipment = shipped($this, '3');
    $command = (string) Str::uuid();

    expect(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '2']], $this->proof, $command))->toBe('COMMITTED')
        ->and(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '2']], $this->proof, $command))->toBe('COMMITTED')
        ->and(DB::table('deliveries')->count())->toBe(1)
        ->and(DeliveryWorld::delivered($this->line))->toBe('2.000');
    deliveryClean();
});

it('opens a difference\'s DELIVERY_DISCREPANCY case in the same command, never accepted on its own (SF-CASE; criterion 3)', function () {
    $shipment = shipped($this, '2');

    expect(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '2']], $this->proof, discrepancy: 'Satu dus penyok'))->toBe('COMMITTED')
        ->and(DB::table('correction_cases')->where('case_kind', 'DELIVERY_DISCREPANCY')->where('state', 'OPEN')->where('project_id', $this->demand['project']->id)->count())->toBe(1)
        ->and(json_decode((string) DB::table('audit_events')->where('action', 'FUL.DeliveryRecorded')->value('after'), true))->toHaveKey('case');
    deliveryClean();
});

it('rejects a likely duplicate with its matches until the actor confirms it with a reason, listed under the acknowledged action (L-49; BD-13; criterion 9)', function () {
    $shipment = shipped($this, '4');
    expect(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '1']], $this->proof))->toBe('COMMITTED');
    $first = (string) DB::table('deliveries')->value('public_id');

    expect(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '1']], $this->proof))->toContain('L-49')
        ->and(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '1']], $this->proof, acknowledged: [$first], reason: 'Dua kiriman terpisah hari ini'))->toBe('COMMITTED')
        ->and(DB::table('audit_events')->where('action', 'FUL.DeliveryDuplicateConfirmed')->value('reason'))->toBe('Dua kiriman terpisah hari ini')
        ->and(DeliveryWorld::delivered($this->line))->toBe('2.000');
    deliveryClean();
});

it('closes a refused quantity without stock, its goods back once through the restoration, never delivered after (NX-15; SF-RESTORE; PX-08; criterion 4)', function () {
    $shipment = shipped($this, '5');
    $stock = onHand($this->product);
    expect(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '2']], $this->proof))->toBe('COMMITTED');

    expect(DeliveryWorld::close($this->actor, $this->line, $shipment->dispatch, 'REFUSED', '4'))->toContain('C-30')
        ->and(DeliveryWorld::close($this->actor, $this->line, $shipment->dispatch, 'REFUSED', '3'))->toBe('COMMITTED')
        ->and(onHand($this->product))->toBe($stock)
        ->and(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '1']], $this->proof))->toContain('C-30')
        ->and(DispatchWorld::restore($this->actor, $this->company->id, $shipment->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($shipment->consumption, $this->rack, '4')]))->toContain('C-30')
        ->and(DispatchWorld::restore($this->actor, $this->company->id, $shipment->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($shipment->consumption, $this->rack, '3')]))->toBe('COMMITTED')
        ->and(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '1']], $this->proof))->toContain('C-30')
        ->and(DB::table('delivery_closures')->where('closure_kind', 'REFUSED')->value('closed_qty_base'))->toBe('3.000');
    deliveryClean();
});

it('supersedes a closure recorded in error by its correct one, never a loss in transit (CM-27; criterion 4)', function () {
    $shipment = shipped($this, '4');
    expect(DeliveryWorld::close($this->actor, $this->line, $shipment->dispatch, 'REFUSED', '3'))->toBe('COMMITTED');
    $wrong = (int) DB::table('delivery_closures')->max('id');

    expect(DeliveryWorld::close($this->actor, $this->line, $shipment->dispatch, 'RETURNED_UNDELIVERED', '1', supersedes: $wrong))->toBe('COMMITTED')
        ->and(DeliveryWorld::close($this->actor, $this->line, $shipment->dispatch, 'REFUSED', '1', supersedes: $wrong))->toContain('C-07')
        ->and(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '3']], $this->proof))->toBe('COMMITTED');
    deliveryClean();
});

it('records a loss in transit as one reclassification on the shipment\'s project with its closure: the HPP contra\'d, a loss of the same amount, net dispatched lowered (CM-07; SF-LOSS; annex 15; criterion 5)', function () {
    $shipment = shipped($this, '2');
    $hpp = (string) DB::table('cost_attributions')->where('stock_consumption_id', $shipment->consumption)->where('cost_class', 'HPP')->sum('amount');
    $total = fn (): string => (string) DB::table('cost_attributions')->where('bearer_project_id', $this->demand['project']->id)->whereIn('cost_class', ['HPP', 'LOSS'])->sum('amount');
    $before = $total();
    $plain = ReceivingWorld::actor($this->company, array_values(array_diff(DeliveryWorld::CAPABILITIES, ['stock.adjust'])))->id;

    expect(fn () => DeliveryWorld::close($plain, $this->line, $shipment->dispatch, 'LOST_IN_TRANSIT', '1'))->toThrow(AuthorizationException::class)
        ->and(DeliveryWorld::close($this->actor, $this->line, $shipment->dispatch, 'LOST_IN_TRANSIT', '3'))->toContain('C-30')
        ->and(DeliveryWorld::close($this->actor, $this->line, $shipment->dispatch, 'LOST_IN_TRANSIT', '1'))->toBe('COMMITTED')
        ->and((string) DB::table('cost_attributions')->where('stock_consumption_id', $shipment->consumption)->where('cost_class', 'LOSS')->where('cause', 'RECLASSIFICATION')->sum('amount'))->toBe('1000.00')
        ->and((string) DB::table('cost_attributions')->where('stock_consumption_id', $shipment->consumption)->where('cost_class', 'HPP')->sum('amount'))->toBe((string) BigDecimal::of($hpp)->minus('1000'))
        ->and($total())->toBe($before)
        ->and((string) DB::table('project_line_balances')->where('project_line_id', $this->line)->value('dispatched_net_qty_base'))->toBe('1.000')
        ->and((string) DB::table('stock_consumption_balances')->where('stock_consumption_id', $shipment->consumption)->value('reclassified_qty_base'))->toBe('1.000')
        ->and(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '2']], $this->proof))->toContain('C-30')
        ->and(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '1']], $this->proof))->toBe('COMMITTED')
        ->and(DB::table('audit_events')->where('action', 'FUL.LossInTransitRecorded')->where('authority_class', 'ADM_PLUS')->count())->toBe(1);
    deliveryClean();
});

it('loses two of three serials shipped from one lot, their one consumption reclassified by their count, each LOST (CM-07; C-02; C-27; criterion 5)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '3', '1000');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $serials = [StockWorld::serial($this->product, $lot, $this->rack), StockWorld::serial($this->product, $lot, $this->rack), StockWorld::serial($this->product, $lot, $this->rack)];
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '3', array_map(fn (int $serial): array => DispatchWorld::pick($lot, $this->rack, '1', $serial), $serials)))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);

    expect(DeliveryWorld::close($this->actor, $this->line, $row->dispatch, 'LOST_IN_TRANSIT', '2', [$serials[1]]))->toContain('C-02')
        ->and(DeliveryWorld::close($this->actor, $this->line, $row->dispatch, 'LOST_IN_TRANSIT', '2', [$serials[1], $serials[2]]))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->whereIn('id', [$serials[1], $serials[2]])->where('state', 'LOST')->count())->toBe(2)
        ->and(DB::table('serial_units')->where('id', $serials[0])->value('state'))->toBe('DISPATCHED')
        ->and((string) DB::table('stock_consumption_balances')->where('stock_consumption_id', $row->consumption)->value('reclassified_qty_base'))->toBe('2.000')
        ->and((string) DB::table('project_line_balances')->where('project_line_id', $this->line)->value('dispatched_net_qty_base'))->toBe('1.000');
    deliveryClean();
});

it('rejects a likely duplicate closure with its matches until the actor confirms it with a reason (L-49; BD-13; INT-015; criterion 9)', function () {
    $shipment = shipped($this, '4');
    expect(DeliveryWorld::close($this->actor, $this->line, $shipment->dispatch, 'REFUSED', '1'))->toBe('COMMITTED');
    $first = DeliveryWorld::closureHandle();

    expect(DeliveryWorld::close($this->actor, $this->line, $shipment->dispatch, 'REFUSED', '1'))->toContain('L-49')
        ->and(DeliveryWorld::close($this->actor, $this->line, $shipment->dispatch, 'REFUSED', '1', acknowledged: [$first], reason: 'Dua penolakan terpisah'))->toBe('COMMITTED')
        ->and(DB::table('audit_events')->where('action', 'FUL.DeliveryClosedDuplicateConfirmed')->count())->toBe(1)
        ->and(DB::table('delivery_closures')->count())->toBe(2);
    deliveryClean();
});

it('holds back only what a closure closed: an undelivered return before the closure brought back other units (PX-08; SF-RESTORE; criterion 4)', function () {
    $shipment = shipped($this, '5');
    expect(DispatchWorld::restore($this->actor, $this->company->id, $shipment->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($shipment->consumption, $this->rack, '2')]))->toBe('COMMITTED')
        ->and(DeliveryWorld::close($this->actor, $this->line, $shipment->dispatch, 'REFUSED', '1'))->toBe('COMMITTED')
        ->and(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '3']], $this->proof))->toContain('C-30')
        ->and(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '2']], $this->proof))->toBe('COMMITTED')
        ->and(DispatchWorld::restore($this->actor, $this->company->id, $shipment->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($shipment->consumption, $this->rack, '1')]))->toBe('COMMITTED');
    deliveryClean();
});

it('never brings back through a shipment\'s restoration what that shipment delivered, whatever another shipment of the line still holds (PX-08; C-30; architecture challenge 3b)', function () {
    $first = shipped($this, '2');
    $second = shipped($this, '3');
    expect(DeliveryWorld::record($this->actor, $this->line, [[$first->dispatch, '2']], $this->proof))->toBe('COMMITTED')
        ->and(DispatchWorld::restore($this->actor, $this->company->id, $first->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($first->consumption, $this->rack, '1')]))->toContain('C-30')
        ->and(DispatchWorld::restore($this->actor, $this->company->id, $second->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($second->consumption, $this->rack, '1')]))->toBe('COMMITTED');
    deliveryClean();
});

it('corrects an entry error with the exact reversal and the right quantity on the same delivery, once, stock untouched (CM-08; criterion 6)', function () {
    $shipment = shipped($this, '5');
    $stock = onHand($this->product);
    expect(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '3']], $this->proof))->toBe('COMMITTED');
    $delivery = DeliveryWorld::lastDelivery();

    expect(DeliveryWorld::correct($this->actor, $this->line, $delivery->delivery, $delivery->line, '6'))->toContain('C-30')
        ->and(DeliveryWorld::correct($this->actor, $this->line, $delivery->delivery, $delivery->line, '2'))->toBe('COMMITTED')
        ->and(DeliveryWorld::delivered($this->line))->toBe('2.000')
        ->and(DB::table('delivery_lines')->where('delivery_id', $delivery->delivery)->where('reverses_delivery_line_id', $delivery->line)->value('qty_base'))->toBe('3.000')
        ->and(DeliveryWorld::correct($this->actor, $this->line, $delivery->delivery, $delivery->line, '1'))->toContain('C-30')
        ->and(onHand($this->product))->toBe($stock);
    deliveryClean();
});

it('keeps a delivery\'s lines after the shipment\'s identity substitution, its papers naming the serial actually shipped (AX-35; CM-36; criterion 7; architecture challenge 3c)', function () {
    $old = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '1', '1000');
    $new = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '1', '1500');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $recorded = StockWorld::serial($this->product, $old, $this->rack);
    $carried = StockWorld::serial($this->product, $new, $this->rack);
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($old, $this->rack, '1', $recorded)]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);
    expect(DeliveryWorld::record($this->actor, $this->line, [[$row->dispatch, '1']], $this->proof, inspection: ['inspector' => 'Pak Adi', 'result' => 'Sesuai pesanan']))->toBe('COMMITTED');
    $delivery = DeliveryWorld::lastDelivery();
    $lines = fn (): array => DB::table('delivery_lines')->where('delivery_id', $delivery->delivery)->orderBy('id')->get(['dispatch_line_id', 'qty_base'])->map(fn (object $line): array => (array) $line)->all();
    $before = $lines();

    expect(DispatchWorld::substitute($this->actor, $this->company->id, $row->consumption, '1', $recorded, $new, $this->rack, $carried, DispatchWorld::evidence($this->company)))->toBe('COMMITTED')
        ->and($lines())->toBe($before)
        ->and(DeliveryFacts::of($delivery->delivery, true)?->lines[0]['note'])->toBe('No. Seri: '.DB::table('serial_units')->where('id', $carried)->value('serial_number'))
        ->and(DeliveryFacts::of($delivery->delivery, true)?->references)->toContain(['label' => 'Pemeriksa', 'value' => 'Pak Adi']);
});

it('leaves a delivery racing the dispatch\'s restoration of the same line one of them, never both on the same units, in both orders on real sessions (C-30; SF-RESTORE; LK-19; architecture challenge 3b)', function (bool $deliveryFirst) {
    $shipment = shipped($this, '3');
    [$actor, $company, $line, $rack, $proof, $dispatch, $consumption] = [$this->actor, $this->company->id, $this->line, $this->rack, $this->proof, $shipment->dispatch, $shipment->consumption];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => $deliveryFirst
            ? DeliveryWorld::record($actor, $line, [[$dispatch, '3']], $proof)
            : DispatchWorld::restore($actor, $company, $dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($consumption, $rack, '3')]), 'the first holds the line'),
        'second' => static function () use ($deliveryFirst, $actor, $company, $line, $rack, $proof, $dispatch, $consumption): string {
            Barrier::await('the first holds the line');

            return $deliveryFirst
                ? DispatchWorld::restore($actor, $company, $dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($consumption, $rack, '3')])
                : DeliveryWorld::record($actor, $line, [[$dispatch, '3']], $proof);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('C-30')
        ->and(DeliveryWorld::delivered($line))->toBe($deliveryFirst ? '3.000' : '0.000');
    deliveryClean();
})->with(['the delivery first' => true, 'the restoration first' => false]);
