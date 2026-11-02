<?php

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AdjustmentWorld;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\ReservationWorld;
use Tests\Support\ReturnWorld;
use Tests\Support\StockWorld;

// TL-05 (TASK-044; WF-PUR-03; AX-11; NX-16; BR-INV-06; BR-INV-13; BR-CR-04; C-03; C-06; C-24; C-29; C-46; M-01; annex 12): units of a purchase's lot returned to the supplier once at their net cost, never HPP; the outcome — replacement, refund, supplier credit, unrecovered share —; the closure only when the case balances; the reconciliation suite after each.

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
    $this->actor = ReceivingWorld::actor($this->company, ReturnWorld::CAPABILITIES)->id;
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->line = $this->demand['line'];
    $this->product = $this->demand['product'];
    $this->unit = $this->demand['unit'];
    $this->lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '10', '12000');
});

function returnClean(): void
{
    expect(StockWorld::ledgerDifferences())->toBe([]);
}

function returnOnHand(int $product): string
{
    return (string) DB::table('stock_product_balances')->where('product_id', $product)->value('on_hand_qty');
}

it('returns present units once, drawing their net cost to SUPPLIER_RETURN and the case\'s returned cost, never HPP (AX-11; SF-CHARGE; criterion 2)', function () {
    $before = (string) DB::table('lot_cost_balances')->where('lot_id', $this->lot)->value('remaining_cost');

    expect(ReturnWorld::returnUnits($this->actor, $this->lot, [AdjustmentWorld::pick($this->lot, $this->rack, '3')]))->toBe('COMMITTED');
    $case = ReturnWorld::lastCase();
    $after = (string) DB::table('lot_cost_balances')->where('lot_id', $this->lot)->value('remaining_cost');
    $drawn = (string) DB::table('cost_attributions')->where('cost_class', 'SUPPLIER_RETURN')->sum('amount');

    expect(BigDecimal::of($before)->minus($after)->isEqualTo($drawn))->toBeTrue()
        ->and(ReturnWorld::figures($case))->toMatchArray(['returned' => $drawn, 'state' => 'OPEN'])
        ->and(DB::table('cost_attributions')->where('cost_class', 'HPP')->count())->toBe(0)
        ->and((string) DB::table('stock_consumptions')->where('kind', 'PURCHASE_RETURN')->sum('qty_base'))->toBe('3.000')
        ->and(DB::table('stock_movements')->where('movement_type', 'PURCHASE_RETURN')->where('correction_case_id', $case)->count())->toBe(1)
        ->and(returnOnHand($this->product))->toBe('7.000')
        ->and(DB::table('audit_events')->where('action', 'INV.ReturnedToSupplier')->where('authority_class', 'ADM_PLUS')->count())->toBe(1);
    returnClean();
});

it('refuses more than the lot physically holds: after 6 of 10 dispatched, at most 4 return (BR-INV-06; annex 12; C-03; criterion 1)', function () {
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '6', [DispatchWorld::pick($this->lot, $this->rack, '6')]))->toBe('COMMITTED')
        ->and(ReturnWorld::returnUnits($this->actor, $this->lot, [AdjustmentWorld::pick($this->lot, $this->rack, '5')]))->toContain('C-03')
        ->and(DB::table('correction_cases')->where('case_kind', 'PURCHASE_RETURN')->count())->toBe(0)
        ->and(ReturnWorld::returnUnits($this->actor, $this->lot, [AdjustmentWorld::pick($this->lot, $this->rack, '4')]))->toBe('COMMITTED')
        ->and(returnOnHand($this->product))->toBe('0.000');
    returnClean();
});

it('commits a return that would leave usable supply below the reservations only with the chosen cut (SF-RSV-CUT; C-06; criterion 3)', function () {
    expect(ReservationWorld::reserve($this->actor, $this->line, '8'))->toBe('COMMITTED');
    $reservation = (int) DB::table('reservations')->max('id');

    expect(ReturnWorld::returnUnits($this->actor, $this->lot, [AdjustmentWorld::pick($this->lot, $this->rack, '3')]))->not->toBe('COMMITTED')
        ->and(DB::table('correction_cases')->where('case_kind', 'PURCHASE_RETURN')->count())->toBe(0)
        ->and(ReturnWorld::returnUnits($this->actor, $this->lot, [AdjustmentWorld::pick($this->lot, $this->rack, '3')], [$reservation => '1']))->toBe('COMMITTED')
        ->and((string) DB::table('stock_product_balances')->where('product_id', $this->product)->value('reserved_qty'))->toBe('7.000');
    returnClean();
});

it('raises the purchase line\'s replacement expectation within the units returned, keeps the case open for a refund, and posts an evidenced supplier credit (C-29; L-08; WF-PUR-03; criterion 4)', function () {
    expect(ReturnWorld::returnUnits($this->actor, $this->lot, [AdjustmentWorld::pick($this->lot, $this->rack, '2')]))->toBe('COMMITTED');
    $case = ReturnWorld::lastCase();
    $line = (int) DB::table('purchase_lines')->where('purchase_id', ReturnWorld::purchaseOf($this->lot))->value('id');
    $returned = ReturnWorld::figures($case)['returned'];
    $half = (string) BigDecimal::of($returned)->dividedBy('2', 2, RoundingMode::Down);

    expect(ReturnWorld::outcome($this->actor, $case, 'REPLACEMENT', qty: '3'))->toContain('C-29')
        ->and(ReturnWorld::outcome($this->actor, $case, 'REPLACEMENT', qty: '1'))->toBe('COMMITTED')
        ->and((string) DB::table('purchase_line_balances')->where('purchase_line_id', $line)->value('replacement_expected_qty_base'))->toBe('1.000')
        ->and(ReturnWorld::outcome($this->actor, $case, 'REFUND', amount: $half))->toBe('COMMITTED')
        ->and(ReturnWorld::figures($case))->toMatchArray(['refunded' => '0.00', 'state' => 'OPEN'])
        ->and(ReturnWorld::outcome($this->actor, $case, 'CREDIT', amount: $half))->toContain('C-14')
        ->and(ReturnWorld::outcome($this->actor, $case, 'CREDIT', amount: (string) BigDecimal::of($returned)->plus('1'), evidence: ReturnWorld::credit($this->company->id)))->toContain('C-46')
        ->and(ReturnWorld::outcome($this->actor, $case, 'CREDIT', amount: $half, evidence: ReturnWorld::credit($this->company->id)))->toBe('COMMITTED')
        ->and(ReturnWorld::figures($case)['refunded'])->toBe($half)
        ->and((string) DB::table('purchase_line_adjustments')->where('kind', 'RETURN_REFUNDED')->where('correction_case_id', $case)->value('amount_delta'))->toBe('-'.$half);
    returnClean();
});

it('recognizes the unrecovered share once on the owning company and closes the case only when it balances (SF-LOSS; C-24; C-46; criteria 5, 6)', function () {
    expect(ReturnWorld::returnUnits($this->actor, $this->lot, [AdjustmentWorld::pick($this->lot, $this->rack, '2')]))->toBe('COMMITTED');
    $case = ReturnWorld::lastCase();
    $returned = ReturnWorld::figures($case)['returned'];

    expect(ReturnWorld::close($this->actor, $case))->toContain('C-46')
        ->and(ReturnWorld::outcome($this->actor, $case, 'UNRECOVERED', amount: (string) BigDecimal::of($returned)->plus('1')))->toContain('C-46')
        ->and(ReturnWorld::outcome($this->actor, $case, 'UNRECOVERED', amount: $returned))->toBe('COMMITTED')
        ->and((string) DB::table('cost_attributions')->where('cost_class', 'LOSS')->where('cause', 'RETURN_SETTLEMENT')->sum('amount'))->toBe($returned)
        ->and(DB::table('cost_attributions')->where('cost_class', 'LOSS')->where('cause', 'RETURN_SETTLEMENT')->distinct()->pluck('bearer_company_id')->all())->toBe([$this->company->id])
        ->and(ReturnWorld::figures($case)['unrecovered'])->toBe($returned)
        ->and(ReturnWorld::outcome($this->actor, $case, 'UNRECOVERED', amount: '1.00'))->toContain('C-46')
        ->and(ReturnWorld::close($this->actor, $case))->toBe('COMMITTED')
        ->and(ReturnWorld::figures($case)['state'])->toBe('CLOSED')
        ->and(ReturnWorld::outcome($this->actor, $case, 'REFUND'))->toContain('ST-02');
    returnClean();
});

it('refuses the return and its outcome to an actor without purchase_return.record (AZ-11; SM-04; criterion 7)', function () {
    $stocker = ReceivingWorld::actor($this->company, DispatchWorld::CAPABILITIES)->id;

    expect(fn () => ReturnWorld::returnUnits($stocker, $this->lot, [AdjustmentWorld::pick($this->lot, $this->rack, '1')]))->toThrow(AuthorizationException::class)
        ->and(ReturnWorld::returnUnits($this->actor, $this->lot, [AdjustmentWorld::pick($this->lot, $this->rack, '1')]))->toBe('COMMITTED')
        ->and(fn () => ReturnWorld::outcome($stocker, ReturnWorld::lastCase(), 'REFUND'))->toThrow(AuthorizationException::class);
    returnClean();
});

it('replays one command_id with its recorded outcome, never returning the units twice (CI-05; AC-16; criterion 8)', function () {
    $command = (string) Str::uuid();

    expect(ReturnWorld::returnUnits($this->actor, $this->lot, [AdjustmentWorld::pick($this->lot, $this->rack, '2')], commandId: $command))->toBe('COMMITTED')
        ->and(ReturnWorld::returnUnits($this->actor, $this->lot, [AdjustmentWorld::pick($this->lot, $this->rack, '2')], commandId: $command))->toBe('COMMITTED')
        ->and(DB::table('correction_cases')->where('case_kind', 'PURCHASE_RETURN')->count())->toBe(1)
        ->and(returnOnHand($this->product))->toBe('8.000');
    returnClean();
});

it('refuses a lot of another purchase and units of two lots in one return (C-08; CS-04)', function () {
    $other = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '5', '12000');

    expect(ReturnWorld::returnUnits($this->actor, $other, [AdjustmentWorld::pick($other, $this->rack, '1')], purchase: ReturnWorld::purchaseOf($this->lot)))->toContain('C-14')
        ->and(ReturnWorld::returnUnits($this->actor, $this->lot, [AdjustmentWorld::pick($this->lot, $this->rack, '1'), AdjustmentWorld::pick($other, $this->rack, '1')]))->toContain('C-14')
        ->and(DB::table('correction_cases')->where('case_kind', 'PURCHASE_RETURN')->count())->toBe(0);
    returnClean();
});

it('leaves a return racing a dispatch of the same lot one of them, never both beyond the lot, in both orders on real sessions (M-01; ST-06; LK-13)', function (bool $dispatchFirst) {
    [$actor, $company, $line, $unit, $rack, $lot, $product] = [$this->actor, $this->company->id, $this->line, $this->unit, $this->rack, $this->lot, $this->product];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => $dispatchFirst
            ? DispatchWorld::dispatch($actor, $company, $line, $unit, '7', [DispatchWorld::pick($lot, $rack, '7')])
            : ReturnWorld::returnUnits($actor, $lot, [AdjustmentWorld::pick($lot, $rack, '7')]), 'the first holds the lot'),
        'second' => static function () use ($dispatchFirst, $actor, $company, $line, $unit, $rack, $lot): string {
            Barrier::await('the first holds the lot');

            return $dispatchFirst
                ? ReturnWorld::returnUnits($actor, $lot, [AdjustmentWorld::pick($lot, $rack, '7')])
                : DispatchWorld::dispatch($actor, $company, $line, $unit, '7', [DispatchWorld::pick($lot, $rack, '7')]);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->not->toBe('COMMITTED')
        ->and(returnOnHand($product))->toBe('3.000');
    returnClean();
})->with(['the dispatch first' => true, 'the return first' => false]);
