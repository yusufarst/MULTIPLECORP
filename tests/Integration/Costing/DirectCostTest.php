<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\CostWorld;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-036; DATABASE §5.7, §6.1, §6.3; AX-29; CM-14; CM-15; CM-17; CM-27; CM-35; L-18) on real PostgreSQL: the
// direct costs — a non-stock line's cost on its project with its charge share, re-posted under a corrected basis and
// moved by a price correction; a drop-ship line's cost on its project, a return to the warehouse contra'd into its
// return lot, a return to the supplier moved to SUPPLIER_RETURN under its case, a confirmation reversed as never
// confirmed with its charge part re-spread — and every later correction reaching the units where they now are.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->world = CostWorld::make();
});

/** @return list<string> a direct source's cost rows by class, component, cause and amount, in id order */
function directRows(string $source, int $id): array
{
    return DB::table('cost_attributions')->where($source, $id)->orderBy('id')->get(['cost_class', 'cost_component', 'cause', 'amount'])
        ->map(fn (object $row): string => "{$row->cost_class} {$row->cost_component} {$row->cause} {$row->amount}")->values()->all();
}

/** @return array{0: string, 1: string} a charge's allocated amount and what is pending on its shares */
function directCharge(int $charge): array
{
    return [
        (string) DB::table('purchase_charge_balances')->where('purchase_charge_id', $charge)->value('allocated_amount'),
        (string) DB::table('purchase_charge_share_balances')->where('purchase_charge_id', $charge)
            ->selectRaw('coalesce(sum(share_amount - posted_amount - respread_out_amount - expensed_out_amount), 0.00) as pending')->value('pending'),
    ];
}

it('posts a non-stock line\'s charge share on its project, re-posts it under a corrected basis and moves it by a price correction (AX-29; CM-35; CM-17; L-18)', function () {
    $project = $this->world->project(state: 'ACTIVE');
    $purchase = $this->world->purchase($project);
    $goods = $this->world->line($purchase, '10', '1000000.00', lineNo: 1);
    $service = $this->world->nonStockLine($purchase, '500000.00', lineNo: 2);
    $this->world->attributeNonStock($service, $project);
    $freight = $this->world->charge($purchase, '30000.00', [$goods->id => '1000000.00', $service->id => '500000.00']);
    $this->world->expect($this->world->recordCharge($freight));

    // The service was attributed at the purchase: its 10,000 posts on the project at once; the goods' 20,000 waits.
    expect(directRows('purchase_line_id', $service->id))->toBe(['HPP GOODS INITIAL 500000.00', 'HPP CHARGE INITIAL 10000.00'])
        ->and(directCharge($freight->id))->toBe(['10000.00', '20000.00']);

    $lot = $this->world->receive($goods, '10');
    $this->world->expect($this->world->correctCharge($freight, '30000.00', [$goods->id => '1', $service->id => '2']));

    expect(DB::table('cost_attributions')->where('purchase_line_id', $service->id)->where('cost_component', 'CHARGE')->sum('amount'))->toBe('20000.00')
        ->and((string) DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->toBe('1010000.00')
        ->and(directCharge($freight->id))->toBe(['30000.00', '0.00']);

    $this->world->expect($this->world->correctPrice($service, '550000.00'));
    expect(DB::table('cost_attributions')->where('purchase_line_id', $service->id)->where('cost_component', 'GOODS')->sum('amount'))->toBe('550000.00')
        ->and(DB::table('purchase_line_balances')->where('purchase_line_id', $service->id)->value('posted_goods_value'))->toBe('550000.00')
        ->and(CostWorld::differences())->toBe([]);
});

it('posts a non-stock line\'s share when its charge is recorded before the line is attributed at the same purchase (AX-29; L-18)', function () {
    $project = $this->world->project(state: 'ACTIVE');
    $purchase = $this->world->purchase($project);
    $service = $this->world->nonStockLine($purchase, '300000.00');
    $fee = $this->world->charge($purchase, '6000.00', [$service->id => '300000.00']);
    $this->world->expect($this->world->recordCharge($fee));
    $this->world->attributeNonStock($service, $project);

    expect(directRows('purchase_line_id', $service->id))->toBe(['HPP GOODS INITIAL 300000.00', 'HPP CHARGE INITIAL 6000.00'])
        ->and(directCharge($fee->id))->toBe(['6000.00', '0.00'])
        ->and(CostWorld::differences())->toBe([]);
});

it('follows a drop-ship line\'s units to its return lot and its supplier-return case through a price correction, a confirmation reversal and a charge correction (CM-14; CM-15; CM-27; CM-17; CM-35)', function () {
    $project = $this->world->project(state: 'ACTIVE');
    $purchase = $this->world->purchase($project);
    $line = $this->world->dropshipLine($purchase, $project, '10', '1000000.00');
    $freight = $this->world->charge($purchase, '10000.00', [$line->id => '1000000.00']);
    $this->world->expect($this->world->recordCharge($freight));
    $confirmed = $this->world->confirmDropship($line, '10');

    expect(directRows('dropship_confirmation_line_id', $confirmed))->toBe(['HPP GOODS INITIAL 1000000.00', 'HPP CHARGE INITIAL 10000.00']);

    // CM-14: 2 of 10 back to the warehouse — 202,000 contra'd into the return lot, its basis exactly that.
    $returned = $this->world->restoreDropship($confirmed, '2', 'DROPSHIP_RETURN');
    $lot = (int) DB::table('inventory_lots')->where('restoration_id', $returned)->value('id');
    // CM-15: 1 of the 8 left back to the supplier — 101,000 moved to SUPPLIER_RETURN under its case.
    $case = $this->world->returnCase();
    $this->world->restoreDropship($confirmed, '1', 'DROPSHIP_SUPPLIER_RETURN', $case);
    $hpp = fn (): string => (string) DB::table('cost_attributions')->where('dropship_confirmation_line_id', $confirmed)->where('cost_class', 'HPP')->sum('amount');
    $lotCost = fn (): string => (string) DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost');
    $returnedCost = fn (): string => (string) DB::table('correction_cases')->where('id', $case)->value('returned_cost');

    expect(DB::table('lot_cost_entries')->where('lot_id', $lot)->get(['entry_kind', 'amount'])->map(fn ($row) => "{$row->entry_kind} {$row->amount}")->all())->toBe(['DROPSHIP_RETURN_BASIS 202000.00'])
        ->and([$hpp(), $lotCost(), $returnedCost()])->toBe(['707000.00', '202000.00', '101000.00'])
        ->and(CostWorld::differences())->toBe([]);

    // CM-17: +100,000 over the 10 units where they are — 70,000 on the project's 7, 20,000 in the lot, 10,000 under the case.
    $this->world->expect($this->world->correctPrice($line, '1100000.00'));
    expect([$hpp(), $lotCost(), $returnedCost()])->toBe(['777000.00', '222000.00', '111000.00'])
        ->and(CostWorld::differences())->toBe([]);

    // CM-27: 1 unit never confirmed — 111,000 contra'd, the line's posted goods lowered, its 1,000 of freight re-spread over the 9.
    $this->world->restoreDropship($confirmed, '1', 'DROPSHIP_CONFIRMATION_REVERSAL');
    expect([$hpp(), $lotCost(), $returnedCost()])->toBe(['666667.00', '222222.00', '111111.00'])
        ->and(DB::table('purchase_line_balances')->where('purchase_line_id', $line->id)->first(['dropship_confirmed_net_qty_base', 'posted_goods_value']))
        ->toMatchObject(['dropship_confirmed_net_qty_base' => '9.000', 'posted_goods_value' => '990000.00'])
        ->and(directCharge($freight->id))->toBe(['10000.00', '0.00'])
        ->and(CostWorld::differences())->toBe([]);

    // CM-35: the freight is 20,000 — every part of version 1 contra'd where it sits, 18,000 re-posted over the 9 units, the reversed unit's 2,000 pending.
    $this->world->expect($this->world->correctCharge($freight, '20000.00', [$line->id => '1000000.00']));
    expect([$hpp(), $lotCost(), $returnedCost()])->toBe(['672000.00', '224000.00', '112000.00'])
        ->and(directCharge($freight->id))->toBe(['18000.00', '2000.00'])
        ->and(CostWorld::differences())->toBe([]);
});
