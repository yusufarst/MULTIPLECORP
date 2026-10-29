<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\CostWorld;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-036; DATABASE §6.1–§6.3, §6.5; BR-INV-12; AX-11; L-31; L-44; C-46) on real PostgreSQL: a lot's basis other
// than a receipt — an opening lot's units removed as never acquired by a BASIS_REVERSAL —; a replaced supplier return
// whose correction reaches its replacement's basis and the case's replaced value; a supplier return's unrecovered share
// settled as a cost-only allocation on another company's project; a dispatch's substitution and a loss's reversal
// contra'd over their open quantity; and a restoration refused against a consumption it cannot reverse.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->world = CostWorld::make();
});

/** @return list<string> the lot's cost entries by kind and amount, in id order */
function basisEntries(int $lot): array
{
    return DB::table('lot_cost_entries')->where('lot_id', $lot)->orderBy('id')->get(['entry_kind', 'amount'])->map(fn (object $row): string => "{$row->entry_kind} {$row->amount}")->values()->all();
}

/** @return array{0: string, 1: string} the lot's remaining quantity and cost */
function basisLot(int $lot): array
{
    $balance = DB::table('lot_cost_balances')->where('lot_id', $lot)->first(['remaining_qty_base', 'remaining_cost']);

    return [(string) $balance->remaining_qty_base, (string) $balance->remaining_cost];
}

it('removes an opening lot\'s units as never acquired by a BASIS_REVERSAL of their share of its remaining cost (BR-INV-12; DATABASE §6.3)', function () {
    $lot = $this->world->openingLot('10', '1000000.00');
    $this->world->dispatch($lot, '3');
    $this->world->removeBasis($lot, '2');

    expect(basisEntries($lot))->toBe(['OPENING_COST 1000000.00', 'BASIS_REVERSAL -200000.00'])
        ->and(basisLot($lot))->toBe(['5.000', '500000.00'])
        ->and(CostWorld::differences())->toBe([]);
});

it('passes a correction of a replaced supplier return to its replacement lot\'s basis and the case\'s replaced value, the case balanced (DATABASE §6.3 steps 3–4; C-46)', function () {
    $line = $this->world->line($this->world->purchase(), '10', '1000000.00');
    $lot = $this->world->receive($line, '10');
    $case = $this->world->returnCase();
    $this->world->purchaseReturn($lot, '2', $case);
    $replacement = $this->world->replacementLot($line, '2', $case, '200000.00');
    $this->world->closeCase($case, '0.00', '200000.00');

    $this->world->expect($this->world->correctPrice($line, '1100000.00'));

    // +100,000 over the lot's 10 units: 20,000 more returned, passed whole to the 2 replacement units; the lot keeps 80,000 for its 8.
    expect(basisEntries($replacement))->toBe(['REPLACEMENT_BASIS 200000.00', 'COST_CORRECTION 20000.00'])
        ->and(basisLot($replacement))->toBe(['2.000', '220000.00'])
        ->and(basisLot($lot))->toBe(['8.000', '880000.00'])
        // Its figures changed, so the closed case is open for its re-closure (TASK-031's rule), its equation still whole.
        ->and(DB::table('correction_cases')->where('id', $case)->first(['state', 'returned_cost', 'refunded_value', 'replaced_value', 'unrecovered_value']))
        ->toMatchObject(['state' => 'OPEN', 'returned_cost' => '220000.00', 'refunded_value' => '0.00', 'replaced_value' => '220000.00', 'unrecovered_value' => '0.00'])
        ->and(CostWorld::differences())->toBe([]);
});

it('settles a supplier return\'s unrecovered share on another company\'s project as a cost-only allocation, and contras a substitution and a loss reversal (AX-11; L-44; DATABASE §6.2)', function () {
    $lot = $this->world->receive($this->world->line($this->world->purchase(), '10', '100000.00'), '10');
    $case = $this->world->returnCase();
    $returned = $this->world->purchaseReturn($lot, '2', $case);
    $causal = $this->world->project(CostWorld::make()->company, 'ACTIVE');
    $this->world->settleReturn($returned, '5000.00', $causal, 'Pemasok hanya mengganti sebagian');

    expect(CostWorld::rows($returned))->toBe(['SUPPLIER_RETURN INITIAL 20000.00', 'SUPPLIER_RETURN RETURN_SETTLEMENT -5000.00', 'LOSS RETURN_SETTLEMENT 5000.00'])
        ->and(DB::table('cost_attributions')->where('stock_consumption_id', $returned)->where('cause', 'RETURN_SETTLEMENT')->distinct()->pluck('bearer_project_id')->all())->toBe([$causal->id])
        ->and(DB::table('correction_cases')->where('id', $case)->first(['returned_cost', 'unrecovered_value']))->toMatchObject(['returned_cost' => '20000.00', 'unrecovered_value' => '5000.00']);

    $dispatch = $this->world->dispatch($lot, '3');
    $this->world->restore($dispatch, '1', 'SUBSTITUTION');
    $loss = $this->world->loss($lot, '1');
    $this->world->restore($loss, '1', 'LOSS_REVERSAL');

    expect(CostWorld::rows($dispatch))->toBe(['HPP INITIAL 30000.00', 'HPP SUBSTITUTION -10000.00'])
        ->and(CostWorld::rows($loss))->toBe(['LOSS INITIAL 10000.00', 'LOSS LOSS_REVERSAL -10000.00'])
        ->and(basisLot($lot))->toBe(['6.000', '60000.00'])
        ->and(CostWorld::differences())->toBe([]);

    // A loss's reversal never reverses a dispatch (L-31).
    expect(fn () => $this->world->restore($dispatch, '1', 'LOSS_REVERSAL'))->toThrow(InvalidArgumentException::class);
});
