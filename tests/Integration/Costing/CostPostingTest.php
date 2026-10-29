<?php

use App\Modules\CST\Services\CostPosting;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\CostWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-036; DATABASE §6.1–§6.5; BR-FIN-12; BR-FIN-13; BR-INV-13; L-31; L-44; criteria 1–3, 6, 8–10, 13, 16) on
// real PostgreSQL through synthetic callers: the numeric annex's scenarios posted by CostPosting — the box split by its
// draws and its receipts, partial returns contra'd over the open quantity, a freight on line value, a price correction
// between receipts, a loss in transit and a damaged return, an inter-company draw — each followed by the reconciliation
// of the lot-cost, charge and share guards.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->world = CostWorld::make();
});

/** The CST guards' differences from their facts and C-28's overlay violations (C-21; C-26; C-28; C-57; C-61). */
function costDifferences(): array
{
    return CostWorld::differences();
}

function costOf(int $lot): array
{
    $balance = DB::table('lot_cost_balances')->where('lot_id', $lot)->first(['remaining_qty_base', 'remaining_cost']);

    return [(string) $balance->remaining_qty_base, (string) $balance->remaining_cost];
}

/** @return list<string> the consumption's cost rows' amounts by class and cause, in id order */
function costRows(int $consumption): array
{
    return CostWorld::rows($consumption);
}

it('draws a box of 12 for 100,000 as 41,667 and 58,333, and receives it in parts as the same cumulative amounts (annex 10; criterion 2)', function () {
    $purchase = $this->world->purchase();
    $line = $this->world->line($purchase, '12', '100000.00');
    $lot = $this->world->receive($line, '12');
    $x = $this->world->dispatch($lot, '5');
    $y = $this->world->dispatch($lot, '7');

    expect(costRows($x))->toBe(['HPP INITIAL 41667.00'])->and(costRows($y))->toBe(['HPP INITIAL 58333.00'])
        ->and(costOf($lot))->toBe(['0.000', '0.00']);

    // The same box received 5 then 7: GOODS 41,667 then 58,333 by cumulative rounding.
    $again = $this->world->line($this->world->purchase(), '12', '100000.00');
    $first = $this->world->receive($again, '5');
    $second = $this->world->receive($again, '7');

    expect(DB::table('lot_cost_entries')->whereIn('lot_id', [$first, $second])->orderBy('id')->pluck('amount')->all())->toBe(['41667.00', '58333.00'])
        ->and(DB::table('purchase_line_balances')->where('purchase_line_id', $again->id)->value('posted_goods_value'))->toBe('100000.00')
        ->and(costDifferences())->toBe([]);
});

it('contras X\'s partial returns over its open quantity, Y + Z + the lot = 100,000 (DATABASE §6.2; L-31; criterion 3)', function () {
    $lot = $this->world->receive($this->world->line($this->world->purchase(), '12', '100000.00'), '12');
    $x = $this->world->dispatch($lot, '5');
    $y = $this->world->dispatch($lot, '7');
    $this->world->restore($x, '2');
    $z = $this->world->dispatch($lot, '2');
    $this->world->restore($x, '3');

    expect(costRows($x))->toBe(['HPP INITIAL 41667.00', 'HPP RESTORATION_CONTRA -16667.00', 'HPP RESTORATION_CONTRA -25000.00'])
        ->and(costRows($z))->toBe(['HPP INITIAL 16667.00'])
        ->and(costOf($lot))->toBe(['3.000', '25000.00'])
        ->and(Money::sum(Money::of('58333.00'), Money::of('16667.00'), Money::of(costOf($lot)[1]))->toString())->toBe('100000.00')
        ->and(costDifferences())->toBe([]);
});

it('allocates a freight of 100,000 on line value as 25,000 and 75,000, both lots 102,500 a piece, and expenses only the fee (annex 14; criterion 1)', function () {
    $purchase = $this->world->purchase();
    $a = $this->world->line($purchase, '10', '1000000.00', lineNo: 1);
    $b = $this->world->line($purchase, '30', '3000000.00', lineNo: 2);
    $freight = $this->world->charge($purchase, '100000.00', [$a->id => '1000000.00', $b->id => '3000000.00']);
    $fee = $this->world->charge($purchase, '20000.00', classification: 'NON_ATTRIBUTABLE');
    $this->world->expect($this->world->recordCharge($freight));
    $this->world->expect($this->world->recordCharge($fee));
    $lotA = $this->world->receive($a, '10');
    $lotB = $this->world->receive($b, '30');

    expect(costOf($lotA))->toBe(['10.000', '1025000.00'])->and(costOf($lotB))->toBe(['30.000', '3075000.00'])
        ->and(DB::table('purchase_charge_balances')->where('purchase_charge_id', $freight->id)->first(['allocated_amount', 'expensed_amount']))
        ->toMatchObject(['allocated_amount' => '100000.00', 'expensed_amount' => '0.00'])
        ->and(DB::table('expenses')->where('purchase_charge_id', $fee->id)->sole())->toMatchObject(['kind' => 'PURCHASE_CHARGE', 'amount' => '20000.00'])
        ->and(DB::table('lot_cost_entries')->where('purchase_charge_id', $fee->id)->exists())->toBeFalse()
        ->and(costDifferences())->toBe([]);
});

it('corrects a box to 120,000 between receipts: +8,333 on the lot, +3,333 on the dispatch, 70,000 on the second receipt (DATABASE §6.3; C-57; criterion 6)', function () {
    $line = $this->world->line($this->world->purchase(), '12', '100000.00');
    $first = $this->world->receive($line, '5');
    $dispatch = $this->world->dispatch($first, '2');
    $this->world->expect($this->world->correctPrice($line, '120000.00'));
    $second = $this->world->receive($line, '7');

    expect(costRows($dispatch))->toBe(['HPP INITIAL 16667.00', 'HPP COST_CORRECTION 3333.00'])
        ->and(costOf($first))->toBe(['3.000', '30000.00'])
        ->and(DB::table('lot_cost_entries')->where('lot_id', $first)->orderBy('id')->get(['entry_kind', 'amount'])->map(fn ($row) => "{$row->entry_kind} {$row->amount}")->all())
        ->toBe(['GOODS 41667.00', 'COST_CORRECTION 8333.00'])
        ->and(DB::table('lot_cost_entries')->where('lot_id', $second)->value('amount'))->toBe('70000.00')
        ->and(DB::table('purchase_line_balances')->where('purchase_line_id', $line->id)->value('posted_goods_value'))->toBe('120000.00')
        ->and(costDifferences())->toBe([]);
});

it('reclassifies a unit lost in transit and contras the delivered one over its open quantity only (annex 15; criterion 8)', function () {
    $lot = $this->world->receive($this->world->line($this->world->purchase(), '2', '100000.00'), '2');
    $dispatch = $this->world->dispatch($lot, '2');
    $this->world->reclassify($dispatch, '1');
    $this->world->restore($dispatch, '1');

    expect(costRows($dispatch))->toBe(['HPP INITIAL 100000.00', 'HPP RECLASSIFICATION -50000.00', 'LOSS RECLASSIFICATION 50000.00', 'HPP RESTORATION_CONTRA -50000.00'])
        ->and(costOf($lot))->toBe(['1.000', '50000.00'])
        ->and(costDifferences())->toBe([]);
});

it('contras a damaged return into its lot and draws its disposal once as the project\'s loss (annex 16; BR-INV-13; criterion 9)', function () {
    $lot = $this->world->receive($this->world->line($this->world->purchase(), '1', '50000.00'), '1');
    $project = $this->world->project();
    $dispatch = $this->world->dispatch($lot, '1', $project);
    $this->world->restore($dispatch, '1', 'SALES_RETURN', 'UNUSABLE');
    $loss = $this->world->loss($lot, '1', $project, 'UNUSABLE');

    expect(costRows($dispatch))->toBe(['HPP INITIAL 50000.00', 'HPP RESTORATION_CONTRA -50000.00'])
        ->and(costRows($loss))->toBe(['LOSS INITIAL 50000.00'])
        ->and(DB::table('cost_attributions')->where('bearer_project_id', $project->id)->sum('amount'))->toBe('50000.00')
        ->and(costOf($lot))->toBe(['0.000', '0.00'])
        ->and(costDifferences())->toBe([]);
});

it('draws 6 of A\'s lot for B\'s project as 72,000 with source A, bearer B and its reason, reversed with its consumption (annex 12; C-28; L-44; criterion 10)', function () {
    $lot = $this->world->receive($this->world->line($this->world->purchase(), '10', '120000.00'), '10');
    $other = CostWorld::make()->company;
    $project = $this->world->project($other);
    $draw = $this->world->dispatch($lot, '6', $project, 'Stok A dipakai untuk proyek B');
    $row = DB::table('cost_attributions')->where('stock_consumption_id', $draw)->sole();

    expect($row)->toMatchObject(['cost_class' => 'HPP', 'amount' => '72000.00', 'source_company_id' => $this->world->company->id, 'bearer_company_id' => $other->id,
        'bearer_project_id' => $project->id, 'allocation_reason' => 'Stok A dipakai untuk proyek B']);

    // A cross-company row without its reason is refused (C-28).
    Refusal::assert(Refusal::CHECK, 'cost_attributions_c28_allocation_reason', fn () => DB::table('cost_attributions')->insert([
        ...array_diff_key((array) $row, ['id' => true]), 'allocation_reason' => null, 'cause' => 'COST_CORRECTION', 'command_id' => null, 'command_line_no' => null,
    ]));

    $this->world->restore($draw, '6');
    expect(DB::table('cost_attributions')->where('stock_consumption_id', $draw)->whereColumn('source_company_id', '<>', 'bearer_company_id')->sum('amount'))->toBe('0.00')
        ->and(costOf($lot))->toBe(['10.000', '120000.00'])
        ->and(costDifferences())->toBe([]);
});

it('takes a line\'s non-creditable tax into its lots and never a creditable one (BR-FIN-17; L-43; criterion 13)', function () {
    $taxed = $this->world->line($this->world->purchase(), '4', '400000.00', '44000.00');
    $lot = $this->world->receive($taxed, '4');
    $creditable = $this->world->line($this->world->purchase(), '4', '400000.00');
    $plain = $this->world->receive($creditable, '4');

    expect(DB::table('lot_cost_entries')->where('lot_id', $lot)->orderBy('id')->pluck('entry_kind')->all())->toBe(['GOODS', 'NONCREDITABLE_TAX'])
        ->and(costOf($lot))->toBe(['4.000', '444000.00'])
        ->and(DB::table('lot_cost_entries')->where('lot_id', $plain)->pluck('entry_kind')->all())->toBe(['GOODS'])
        ->and(costOf($plain))->toBe(['4.000', '400000.00'])
        ->and(costDifferences())->toBe([]);
});
