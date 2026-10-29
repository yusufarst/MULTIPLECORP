<?php

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\CostWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-036; SF-CHARGE; AX-29; AX-32; AX-05; AX-34; FX-03; FX-04; L-43; L-47; criteria 4, 5, 7) on real PostgreSQL:
// a charge's share posted with each receipt over the ordered quantity, its open remainder pending, re-spread over the
// received units when the remainder is closed — consumed units by draws — or expensed when nothing was received; a
// late charge after consumption; a receipt reversed as never received; the source bill's cap refusing a charge or a
// correction beyond it; a basis correction contra'ing the old version and re-posting the new — allocated + pending +
// expensed = the charge at every step, and the guards reconciled after each scenario.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->world = CostWorld::make();
});

function chargeDifferences(): array
{
    return CostWorld::differences();
}

/**
 * The charge's allocated and expensed figures from its guard, and what is pending on its lines read from the shares'
 * guards — share less posted, re-spread and expensed —, the three summing to the charge (L-47; AX-29).
 *
 * @return array{allocated: string, expensed: string, pending: string}
 */
function chargeFigures(int $charge): array
{
    $balance = DB::table('purchase_charge_balances')->where('purchase_charge_id', $charge)->first(['amount', 'allocated_amount', 'expensed_amount']);
    $pending = (string) DB::table('purchase_charge_share_balances')->where('purchase_charge_id', $charge)
        ->selectRaw('coalesce(sum(share_amount - posted_amount - respread_out_amount - expensed_out_amount), 0.00) as pending')->value('pending');

    expect(Money::sum(Money::of((string) $balance->allocated_amount), Money::of((string) $balance->expensed_amount), Money::of($pending))->toString())->toBe((string) $balance->amount);

    return ['allocated' => (string) $balance->allocated_amount, 'expensed' => (string) $balance->expensed_amount, 'pending' => $pending];
}

function lotCost(int $lot): string
{
    return (string) DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost');
}

it('posts a received 6 of 10\'s share and keeps the rest pending, re-spreading it over the received units — consumed ones by draws — when the remainder closes (L-47; AX-32; criterion 4)', function () {
    $purchase = $this->world->purchase();
    $line = $this->world->line($purchase, '10', '1000000.00');
    $freight = $this->world->charge($purchase, '50000.00', [$line->id => '1000000.00']);
    $this->world->expect($this->world->recordCharge($freight));
    $lot = $this->world->receive($line, '6');

    expect(chargeFigures($freight->id))->toBe(['allocated' => '30000.00', 'expensed' => '0.00', 'pending' => '20000.00'])
        ->and(lotCost($lot))->toBe('630000.00');

    $dispatch = $this->world->dispatch($lot, '3');
    $this->world->closeRemainder($line, '4');

    // The pending 20,000 re-spread over the 6 received units: 10,000 drawn by the 3 dispatched, 10,000 kept by the lot.
    expect(chargeFigures($freight->id))->toBe(['allocated' => '50000.00', 'expensed' => '0.00', 'pending' => '0.00'])
        ->and(DB::table('cost_attributions')->where('stock_consumption_id', $dispatch)->orderBy('id')->get(['cause', 'amount'])->map(fn ($row) => "{$row->cause} {$row->amount}")->all())
        ->toBe(['INITIAL 315000.00', 'CHARGE_RESPREAD 10000.00'])
        ->and(lotCost($lot))->toBe('325000.00')
        ->and(DB::table('lot_cost_entries')->where('lot_id', $lot)->where('entry_kind', 'CHARGE_RESPREAD')->sole())->toMatchObject(['amount' => '20000.00', 'respread_from_purchase_line_id' => $line->id])
        ->and(chargeDifferences())->toBe([]);
});

it('expenses a charge whose purchase received nothing once its remainder ends, never allocating it too (DIR-024 D-2; C-21; criterion 4)', function () {
    $purchase = $this->world->purchase();
    $line = $this->world->line($purchase, '5', '500000.00');
    $freight = $this->world->charge($purchase, '25000.00', [$line->id => '500000.00']);
    $this->world->expect($this->world->recordCharge($freight));
    $this->world->closeRemainder($line, '5');

    expect(chargeFigures($freight->id))->toBe(['allocated' => '0.00', 'expensed' => '25000.00', 'pending' => '0.00'])
        ->and(DB::table('expenses')->where('purchase_charge_id', $freight->id)->sole())->toMatchObject(['kind' => 'PURCHASE_CHARGE', 'amount' => '25000.00', 'purchase_line_id' => $line->id])
        ->and(DB::table('lot_cost_entries')->where('purchase_charge_id', $freight->id)->exists())->toBeFalse()
        ->and(chargeDifferences())->toBe([]);
});

it('posts a late charge to each lot whole and draws the consumed units\' part on their consumptions (AX-29; DATABASE §6.1)', function () {
    $purchase = $this->world->purchase();
    $line = $this->world->line($purchase, '4', '400000.00');
    $lot = $this->world->receive($line, '4');
    $dispatch = $this->world->dispatch($lot, '4');
    $freight = $this->world->charge($purchase, '8000.00', [$line->id => '400000.00']);
    $this->world->expect($this->world->recordCharge($freight));

    expect(DB::table('lot_cost_entries')->where('lot_id', $lot)->where('entry_kind', 'CHARGE_SHARE')->value('amount'))->toBe('8000.00')
        ->and(DB::table('cost_attributions')->where('stock_consumption_id', $dispatch)->orderBy('id')->get(['cause', 'amount'])->map(fn ($row) => "{$row->cause} {$row->amount}")->all())
        ->toBe(['INITIAL 400000.00', 'LATE_CHARGE 8000.00'])
        ->and(lotCost($lot))->toBe('0.00')
        ->and(chargeFigures($freight->id))->toBe(['allocated' => '8000.00', 'expensed' => '0.00', 'pending' => '0.00'])
        ->and(chargeDifferences())->toBe([]);
});

it('reverses a receipt as never received, its charge share re-spread over the remaining received units at once (AX-05; SF-CHARGE)', function () {
    $purchase = $this->world->purchase();
    $line = $this->world->line($purchase, '10', '1000000.00');
    $freight = $this->world->charge($purchase, '50000.00', [$line->id => '1000000.00']);
    $this->world->expect($this->world->recordCharge($freight));
    $first = $this->world->receive($line, '6');
    $second = $this->world->receive($line, '4');
    $this->world->reverseReceipt($second, '4');

    // The 4 reversed units' goods leave the line's posted value; their 20,000 of charge moves onto the first lot.
    expect(DB::table('purchase_line_balances')->where('purchase_line_id', $line->id)->first(['normal_received_net_qty_base', 'posted_goods_value']))
        ->toMatchObject(['normal_received_net_qty_base' => '6.000', 'posted_goods_value' => '600000.00'])
        ->and(lotCost($second))->toBe('0.00')
        ->and(lotCost($first))->toBe('650000.00')
        ->and(chargeFigures($freight->id))->toBe(['allocated' => '50000.00', 'expensed' => '0.00', 'pending' => '0.00'])
        ->and(chargeDifferences())->toBe([]);

    // Re-receiving those units posts none of the re-spread share again.
    $again = $this->world->receive($line, '4');
    expect(DB::table('lot_cost_entries')->where('lot_id', $again)->where('entry_kind', 'CHARGE_SHARE')->exists())->toBeFalse()
        ->and(chargeFigures($freight->id)['allocated'])->toBe('50000.00')
        ->and(chargeDifferences())->toBe([]);
});

it('refuses a charge or a correction beyond its source bill with C-20, a bill cited once (L-43; BD-17; criterion 5)', function () {
    $purchase = $this->world->purchase();
    $line = $this->world->line($purchase, '2', '200000.00');
    $bill = $this->world->bill('30000.00');
    $first = $this->world->charge($purchase, '20000.00', [$line->id => '200000.00'], billId: $bill);
    $this->world->expect($this->world->recordCharge($first));
    $outcome = $this->world->recordNew($purchase, '15000.00', [$line->id => '200000.00'], billId: $bill);

    // The second charge is refused whole: neither it, nor a guard, nor a citation stays.
    expect([$outcome->kind, $outcome->code])->toBe(['REJECTED', 'C-20'])
        ->and(DB::table('purchase_charges')->where('purchase_id', $purchase->id)->pluck('id')->all())->toBe([$first->id])
        ->and(DB::table('evidence_balances')->where('evidence_document_id', $bill)->value('cited_amount'))->toBe('20000.00');

    $correction = $this->world->correctCharge($first, '31000.00', [$line->id => '200000.00']);
    expect([$correction->kind, $correction->code])->toBe(['REJECTED', 'C-20'])
        ->and(DB::table('purchase_charge_balances')->where('purchase_charge_id', $first->id)->value('amount'))->toBe('20000.00')
        ->and(chargeDifferences())->toBe([]);

    // Never allocated and expensed beyond the charge: the guard refuses it under the runtime role (C-21).
    Refusal::assert(Refusal::CHECK, 'purchase_charge_balances_c21_conservation',
        fn () => DB::table('purchase_charge_balances')->where('purchase_charge_id', $first->id)->update(['allocated_amount' => '20000.00', 'expensed_amount' => '1.00']));
});

it('corrects a charge\'s basis by contra\'ing the old version and re-posting the new one, Σ allocated = the charge (CM-35; AX-34; criterion 7)', function () {
    $purchase = $this->world->purchase();
    $a = $this->world->line($purchase, '10', '1000000.00', lineNo: 1);
    $b = $this->world->line($purchase, '10', '1000000.00', lineNo: 2);
    $freight = $this->world->charge($purchase, '40000.00', [$a->id => '1.000', $b->id => '1.000']);
    $this->world->expect($this->world->recordCharge($freight));
    $lotA = $this->world->receive($a, '10');
    $lotB = $this->world->receive($b, '5');
    $dispatch = $this->world->dispatch($lotA, '5');

    expect([lotCost($lotA), lotCost($lotB)])->toBe(['510000.00', '510000.00']);

    // The basis was 3 : 1, not 1 : 1 — A's share 30,000, B's 10,000; B's open half stays pending.
    $this->world->expect($this->world->correctCharge($freight, '40000.00', [$a->id => '3.000', $b->id => '1.000']));

    expect(chargeFigures($freight->id))->toBe(['allocated' => '35000.00', 'expensed' => '0.00', 'pending' => '5000.00'])
        ->and(DB::table('purchase_charge_share_balances')->where('purchase_charge_id', $freight->id)->orderBy('purchase_line_id')->get(['allocation_version', 'share_amount', 'posted_amount'])
            ->map(fn ($row) => "{$row->allocation_version} {$row->share_amount} {$row->posted_amount}")->all())->toBe(['2 30000.00 30000.00', '2 10000.00 5000.00'])
        ->and(Money::of(lotCost($lotA))->plus(Money::of((string) DB::table('cost_attributions')->where('stock_consumption_id', $dispatch)->sum('amount')))->toString())->toBe('1030000.00')
        ->and(lotCost($lotB))->toBe('505000.00')
        ->and(chargeDifferences())->toBe([]);

    // Ending B's remainder re-spreads its pending 5,000; every remainder ended, Σ allocated = the charge exactly.
    $this->world->closeRemainder($b, '5');
    expect(chargeFigures($freight->id))->toBe(['allocated' => '40000.00', 'expensed' => '0.00', 'pending' => '0.00'])
        ->and(chargeDifferences())->toBe([]);
});

it('corrects a charge\'s amount with an expensed share present, cites the delta and posts a later receipt under the new version (CM-35; FX-04; C-20; criterion 7)', function () {
    $purchase = $this->world->purchase();
    $a = $this->world->line($purchase, '10', '1000000.00', lineNo: 1);
    $b = $this->world->line($purchase, '10', '1000000.00', lineNo: 2);
    $bill = $this->world->bill('100000.00');
    $freight = $this->world->charge($purchase, '40000.00', [$a->id => '1', $b->id => '1'], billId: $bill);
    $this->world->expect($this->world->recordCharge($freight));
    // B ends with nothing of the charge received anywhere: its 20,000 is expensed.
    $this->world->closeRemainder($b, '10');
    $this->world->receive($a, '4');

    expect(chargeFigures($freight->id))->toBe(['allocated' => '8000.00', 'expensed' => '20000.00', 'pending' => '12000.00']);

    // 60,000 on a basis of 2 : 1 — A 40,000, B 20,000: B's share stays expensed, A's 4 received units take 16,000.
    $this->world->expect($this->world->correctCharge($freight, '60000.00', [$a->id => '2', $b->id => '1']));
    expect(chargeFigures($freight->id))->toBe(['allocated' => '16000.00', 'expensed' => '20000.00', 'pending' => '24000.00'])
        ->and(DB::table('evidence_balances')->where('evidence_document_id', $bill)->value('cited_amount'))->toBe('60000.00')
        ->and(DB::table('purchase_charge_balances')->where('purchase_charge_id', $freight->id)->value('amount'))->toBe('60000.00');

    // A later receipt posts only what the new version still has pending (FX-04).
    $second = $this->world->receive($a, '6');
    expect(DB::table('lot_cost_entries')->where('lot_id', $second)->orderBy('id')->get(['entry_kind', 'amount'])->map(fn ($row) => "{$row->entry_kind} {$row->amount}")->all())
        ->toBe(['GOODS 600000.00', 'CHARGE_SHARE 24000.00'])
        ->and(chargeFigures($freight->id))->toBe(['allocated' => '40000.00', 'expensed' => '20000.00', 'pending' => '0.00'])
        ->and(chargeDifferences())->toBe([]);

    // A share below what it already expensed cannot be posted (C-21); a version leaving a line out is no version.
    $refused = $this->world->correctCharge($freight, '60000.00', [$a->id => '5', $b->id => '1']);
    expect([$refused->kind, $refused->code])->toBe(['REJECTED', 'C-21'])
        ->and(fn () => $this->world->correctCharge($freight, '60000.00', [$a->id => '1']))->toThrow(InvalidArgumentException::class)
        ->and(chargeDifferences())->toBe([]);
});
