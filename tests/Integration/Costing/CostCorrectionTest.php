<?php

use App\Modules\CST\Queries\CostFigures;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\CostWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-036; DATABASE §6.3 steps 4–5; §6.4 (8); C-46; C-53; SF-REVAL; BR-FIN-01; BR-FIN-11; criteria 14–16) on
// real PostgreSQL: a price correction reaching a closed purchase-return case and a completed project in the correcting
// command — the case reopened, one REVALIDATE per completed project —; CALC-09 and CALC-11's cost parts summing each
// cost row and expense once with no Cash-Out written; and the cost rows refusing update and delete under the runtime role.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->world = CostWorld::make();
});

it('reopens a closed purchase-return case its correction unbalances and revalidates each completed bearer project once, in the correcting command (C-46; SF-REVAL; criterion 14)', function () {
    $line = $this->world->line($this->world->purchase(), '12', '120000.00');
    $lot = $this->world->receive($line, '12');
    $case = $this->world->returnCase();
    $returned = $this->world->purchaseReturn($lot, '2', $case);
    $this->world->closeCase($case, '20000.00');
    $completed = $this->world->project(state: 'ACTIVE');
    $first = $this->world->dispatch($lot, '2', $completed);
    $second = $this->world->dispatch($lot, '2', $completed);
    $active = $this->world->project(state: 'ACTIVE');
    $third = $this->world->dispatch($lot, '1', $active);
    $this->world->complete($completed);

    $this->world->expect($this->world->correctPrice($line, '132000.00'));
    $adjustment = DB::table('purchase_line_adjustments')->where('purchase_line_id', $line->id)->sole(['id', 'command_id']);

    // +12,000 over the 12 units: 2,000 more returned to the supplier, 1,000 a unit on the dispatches, 5,000 kept by the lot.
    expect(CostWorld::rows($returned))->toBe(['SUPPLIER_RETURN INITIAL 20000.00', 'SUPPLIER_RETURN COST_CORRECTION 2000.00'])
        ->and([CostWorld::rows($first), CostWorld::rows($second), CostWorld::rows($third)])->toBe([
            ['HPP INITIAL 20000.00', 'HPP COST_CORRECTION 2000.00'], ['HPP INITIAL 20000.00', 'HPP COST_CORRECTION 2000.00'], ['HPP INITIAL 10000.00', 'HPP COST_CORRECTION 1000.00'],
        ])
        ->and(DB::table('lot_cost_balances')->where('lot_id', $lot)->first(['remaining_qty_base', 'remaining_cost']))->toMatchObject(['remaining_qty_base' => '5.000', 'remaining_cost' => '55000.00'])
        // The case's equation broken by the correction, it is OPEN again in the same command (C-46).
        ->and(DB::table('correction_cases')->where('id', $case)->first(['state', 'returned_cost', 'refunded_value', 'reopen_command_id']))
        ->toMatchObject(['state' => 'OPEN', 'returned_cost' => '22000.00', 'refunded_value' => '20000.00', 'reopen_command_id' => $adjustment->command_id])
        // One REVALIDATE for the completed project its two dispatches reach, none for the active one (SF-REVAL).
        ->and(DB::table('project_state_transitions')->where('transition_kind', 'REVALIDATE')->get(['project_id', 'from_state', 'to_state', 'correction_reference', 'command_id'])->map(fn ($row) => (array) $row)->all())
        ->toBe([['project_id' => $completed->id, 'from_state' => 'COMPLETED_NORMAL', 'to_state' => 'ACTIVE', 'correction_reference' => "purchase_line_adjustment:{$adjustment->id}", 'command_id' => $adjustment->command_id]])
        ->and(DB::table('projects')->whereIn('id', [$completed->id, $active->id])->orderBy('id')->pluck('state')->all())->toBe(['ACTIVE', 'ACTIVE'])
        ->and(CostWorld::differences())->toBe([]);
});

it('counts each cost row and expense once in CALC-09 and CALC-11\'s cost parts and writes no Cash-Out (DATABASE §6.4 (8); BR-FIN-01; BR-FIN-11; criterion 15)', function () {
    $project = $this->world->project(state: 'ACTIVE');
    $purchase = $this->world->purchase($project);
    $line = $this->world->line($purchase, '10', '1000000.00');
    $this->world->expect($this->world->recordCharge($this->world->charge($purchase, '20000.00', [$line->id => '1000000.00'])));
    $this->world->expect($this->world->recordCharge($this->world->charge($purchase, '5000.00', classification: 'NON_ATTRIBUTABLE')));
    $this->world->expect($this->world->recordCharge($this->world->charge($this->world->purchase(), '7000.00', classification: 'NON_ATTRIBUTABLE')));
    $lot = $this->world->receive($line, '10');
    $dispatch = $this->world->dispatch($lot, '4', $project);
    $this->world->loss($lot, '1', $project);
    $this->world->restore($dispatch, '1');
    $this->world->loss($lot, '1');
    $viewer = Authorization::admin([$this->world->company->id], ['cost.view'])->id;
    $figures = app(CostFigures::class);

    // At 102,000 a unit: HPP 408,000 less the 102,000 restored, the project's loss 102,000, its purchase's fee 5,000.
    expect($figures->project($viewer, $project->id))->toBe(['hpp' => '306000.00', 'loss' => '102000.00', 'expenses' => '5000.00', 'direct_cost' => '413000.00'])
        ->and($figures->companies($viewer))->toBe([['company_id' => $this->world->company->id, 'losses' => '102000.00', 'expenses' => '7000.00']])
        ->and($figures->lot($viewer, $lot))->toBe(['remaining_qty' => '5.000', 'remaining_cost' => '510000.00'])
        // Every cost row borne by the project once: the figure is the rows' own sum.
        ->and((string) DB::table('cost_attributions')->where('bearer_project_id', $project->id)->sum('amount'))->toBe('408000.00')
        ->and(DB::table('disbursements')->count())->toBe(0)
        ->and(DB::table('money_fact_balances')->count())->toBe(0)
        ->and(CostWorld::differences())->toBe([]);
});

it('refuses an update or a delete of a cost row under the runtime role (C-53; BR-CR-02; criterion 16)', function () {
    $purchase = $this->world->purchase();
    $line = $this->world->line($purchase, '2', '200000.00');
    $this->world->expect($this->world->recordCharge($this->world->charge($purchase, '4000.00', classification: 'NON_ATTRIBUTABLE')));
    $lot = $this->world->receive($line, '2');
    $dispatch = $this->world->dispatch($lot, '1');
    $rows = [
        'lot_cost_entries' => DB::table('lot_cost_entries')->where('lot_id', $lot)->value('id'),
        'cost_attributions' => DB::table('cost_attributions')->where('stock_consumption_id', $dispatch)->value('id'),
        'expenses' => DB::table('expenses')->where('company_id', $this->world->company->id)->value('id'),
    ];

    foreach ($rows as $table => $id) {
        expect($id)->not->toBeNull();
        Refusal::assert(Refusal::PRIVILEGE, "permission denied for table {$table}", fn () => DB::table($table)->where('id', $id)->update(['amount' => '1.00']));
        Refusal::assert(Refusal::PRIVILEGE, "permission denied for table {$table}", fn () => DB::table($table)->where('id', $id)->delete());
    }

    expect(DB::table('cost_attributions')->where('id', $rows['cost_attributions'])->value('amount'))->toBe('100000.00')
        ->and(CostWorld::differences())->toBe([]);
});
