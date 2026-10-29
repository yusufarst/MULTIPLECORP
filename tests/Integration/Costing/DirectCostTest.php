<?php

use App\Modules\CST\Services\CostPosting;
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

it('passes a correction of a drop-ship supplier return to the replacement confirmation carrying its basis (CM-15; PX-03; DATABASE §6.3 step 3)', function () {
    $project = $this->world->project(state: 'ACTIVE');
    $line = $this->world->dropshipLine($this->world->purchase($project), $project, '10', '1000000.00');
    $confirmed = $this->world->confirmDropship($line, '10');
    $case = $this->world->returnCase();
    $this->world->restoreDropship($confirmed, '2', 'DROPSHIP_SUPPLIER_RETURN', $case);
    $replacement = $this->world->confirmReplacement($line, '2', $case, '200000.00');
    $this->world->closeCase($case, '0.00', '200000.00');

    expect(directRows('dropship_confirmation_line_id', $replacement))->toBe(['HPP BASIS INITIAL 200000.00']);

    // +100,000 over the 10 units: 80,000 on the project's 8, 20,000 more returned — passed whole to the 2 replacement units.
    $this->world->expect($this->world->correctPrice($line, '1100000.00'));
    expect(directRows('dropship_confirmation_line_id', $replacement))->toBe(['HPP BASIS INITIAL 200000.00', 'HPP BASIS COST_CORRECTION 20000.00'])
        ->and(DB::table('correction_cases')->where('id', $case)->first(['returned_cost', 'replaced_value']))->toMatchObject(['returned_cost' => '220000.00', 'replaced_value' => '220000.00'])
        ->and(DB::table('cost_attributions')->where('dropship_confirmation_line_id', $confirmed)->where('cost_class', 'HPP')->sum('amount'))->toBe('880000.00')
        ->and(CostWorld::differences())->toBe([]);

    // A replacement confirmation never takes the purchase line's own cost (PX-03).
    expect(fn () => $this->world->expect($this->world->run(CostPosting::purchaseLocks($line->purchase_id), fn ($context) => app(CostPosting::class)->dropshipConfirmed($context, $replacement, '2026-10-29'))))
        ->toThrow(InvalidArgumentException::class);
});

it('takes a drop-ship return lot\'s units back onto their confirmation line as never returned, with what corrections and charges brought them (§5.3; CM-37; AX-36)', function () {
    $project = $this->world->project(state: 'ACTIVE');
    $purchase = $this->world->purchase($project);
    $line = $this->world->dropshipLine($purchase, $project, '10', '1000000.00');
    $freight = $this->world->charge($purchase, '10000.00', [$line->id => '1000000.00']);
    $this->world->expect($this->world->recordCharge($freight));
    $confirmed = $this->world->confirmDropship($line, '10');
    $returned = $this->world->restoreDropship($confirmed, '2', 'DROPSHIP_RETURN');
    $lot = (int) DB::table('inventory_lots')->where('restoration_id', $returned)->value('id');
    $this->world->expect($this->world->correctPrice($line, '1100000.00'));
    $this->world->expect($this->world->correctCharge($freight, '20000.00', [$line->id => '1000000.00']));
    $hpp = fn (): string => (string) DB::table('cost_attributions')->where('dropship_confirmation_line_id', $confirmed)->where('cost_class', 'HPP')->sum('amount');
    $lotCost = fn (): string => (string) DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost');

    expect([$hpp(), $lotCost()])->toBe(['896000.00', '224000.00']);

    // One unit back as never returned: its 112,000 — the basis it was returned with, its price correction and the new freight — back on the line.
    $this->world->reverseDropshipReturn($lot, '1');
    expect([$hpp(), $lotCost()])->toBe(['1008000.00', '112000.00'])
        ->and(directCharge($freight->id))->toBe(['20000.00', '0.00'])
        ->and(CostWorld::differences())->toBe([]);

    // The line's units are 9 on the project and 1 in the lot: a further correction follows them there.
    $this->world->expect($this->world->correctPrice($line, '1210000.00'));
    expect([$hpp(), $lotCost()])->toBe(['1107000.00', '123000.00'])
        ->and(CostWorld::differences())->toBe([]);
});

it('passes a correction of one line\'s returned units to that line\'s replacement only, a case it leaves balanced kept closed (§6.3 steps 3–4; C-46)', function () {
    $project = $this->world->project(state: 'ACTIVE');
    $purchase = $this->world->purchase($project);
    $stock = $this->world->line($purchase, '10', '1000000.00', lineNo: 1);
    $lot = $this->world->receive($stock, '10');
    $dropship = $this->world->dropshipLine($purchase, $project, '10', '1000000.00', lineNo: 2);
    $confirmed = $this->world->confirmDropship($dropship, '10');
    $case = $this->world->returnCase();
    $this->world->purchaseReturn($lot, '3', $case);
    $this->world->restoreDropship($confirmed, '2', 'DROPSHIP_SUPPLIER_RETURN', $case);
    $replacement = $this->world->confirmReplacement($dropship, '2', $case, '200000.00');
    $this->world->closeCase($case, '300000.00', '200000.00');

    $this->world->expect($this->world->correctPrice($dropship, '1100000.00'));

    // +20,000 for the drop-ship line's 2 returned units, passed whole to their replacement: the case stays balanced, and closed.
    expect(directRows('dropship_confirmation_line_id', $replacement))->toBe(['HPP BASIS INITIAL 200000.00', 'HPP BASIS COST_CORRECTION 20000.00'])
        ->and(DB::table('correction_cases')->where('id', $case)->first(['state', 'returned_cost', 'refunded_value', 'replaced_value']))
        ->toMatchObject(['state' => 'CLOSED', 'returned_cost' => '520000.00', 'refunded_value' => '300000.00', 'replaced_value' => '220000.00'])
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
