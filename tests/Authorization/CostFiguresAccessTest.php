<?php

use App\Modules\CST\Queries\CostFigures;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\CostWorld;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-036; PJ-05; PJ-06; PJ-22; CS-08; AZ-11; SM-25; criterion 15): CST's figures over an inter-company draw —
// company B's project drawing 6 of company A's lot of 10 at 12,000 — for a source-side viewer, a bearer-side viewer, an
// Admin of both, and an Admin lacking the cost family: each side sees what its projection states, a company out of scope
// answers as unknown, and the figures stay per company.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->a = CostWorld::make();
    $this->b = CostWorld::make();
    $this->lot = $this->a->receive($this->a->line($this->a->purchase(), '10', '120000.00'), '10');
    $this->project = $this->a->project($this->b->company, 'ACTIVE');
    $this->draw = $this->a->dispatch($this->lot, '6', $this->project, 'Stok A dipakai untuk proyek B');
    $this->a->loss($this->lot, '1');
    $this->b->expect($this->b->recordCharge($this->b->charge($this->b->purchase(), '3000.00', classification: 'NON_ATTRIBUTABLE')));
    $this->figures = app(CostFigures::class);
    $this->row = [
        'id' => (int) DB::table('cost_attributions')->where('stock_consumption_id', $this->draw)->value('id'),
        'class' => 'HPP', 'cause' => 'INITIAL', 'amount' => '72000.00', 'date' => (string) DB::table('cost_attributions')->where('stock_consumption_id', $this->draw)->value('business_date'),
        'product' => (string) DB::table('products')->where('id', $this->a->productId)->value('name'), 'quantity' => '6.000', 'allocated' => true,
    ];
    $this->bearerSide = ['bearer_company_id' => $this->b->company->id, 'bearer_project_id' => $this->project->id, 'reason' => 'Stok A dipakai untuk proyek B',
        'authorized_by' => (string) DB::table('users')->where('id', $this->a->actorId)->value('name')];
    $this->lotCode = (string) DB::table('inventory_lots')->where('id', $this->lot)->value('lot_code');
});

it('shows the source side the draw from its lot with the bearer, its reason and its actor masked (PJ-05)', function () {
    $source = Authorization::admin([$this->a->company->id], ['cost.view'])->id;

    expect($this->figures->allocations($source))->toBe([[...$this->row,
        'bearer_company_id' => null, 'bearer_project_id' => null, 'reason' => null, 'authorized_by' => null, 'source_company_id' => $this->a->company->id, 'lot_code' => null,
    ]])
        ->and($this->figures->lot($source, $this->lot))->toBe(['remaining_qty' => '3.000', 'remaining_cost' => '36000.00'])
        // The bearer's project is outside its scope: unknown, not zero (AZ-11).
        ->and($this->figures->project($source, $this->project->id))->toBeNull();
});

it('shows the bearer side the allocated cost in full as its project\'s own HPP with the source masked, the lot code with stock.view only (PJ-06; CALC-09)', function () {
    $bearer = Authorization::admin([$this->b->company->id], ['cost.view'])->id;
    $stock = Authorization::admin([$this->b->company->id], ['cost.view', 'stock.view'])->id;

    expect($this->figures->allocations($bearer))->toBe([[...$this->row, ...$this->bearerSide, 'source_company_id' => null, 'lot_code' => null]])
        ->and($this->figures->allocations($stock))->toBe([[...$this->row, ...$this->bearerSide, 'source_company_id' => null, 'lot_code' => $this->lotCode]])
        ->and($this->figures->project($bearer, $this->project->id))->toBe(['hpp' => '72000.00', 'loss' => '0.00', 'expenses' => '0.00', 'direct_cost' => '72000.00'])
        // The source's lot and its cost stay the source company's (PJ-06).
        ->and($this->figures->lot($bearer, $this->lot))->toBeNull();
});

it('shows an Admin of both companies every side, and figures per company, never a total across them (CS-08; criterion 15)', function () {
    $both = Authorization::admin([$this->a->company->id, $this->b->company->id], ['cost.view', 'stock.view'])->id;

    expect($this->figures->allocations($both))->toBe([[...$this->row, ...$this->bearerSide, 'source_company_id' => $this->a->company->id, 'lot_code' => $this->lotCode]])
        ->and($this->figures->companies($both))->toBe([
            ['company_id' => $this->a->company->id, 'losses' => '12000.00', 'expenses' => '0.00'],
            ['company_id' => $this->b->company->id, 'losses' => '0.00', 'expenses' => '3000.00'],
        ])
        ->and($this->figures->companies(Authorization::admin([$this->b->company->id], ['cost.view'])->id))->toBe([
            ['company_id' => $this->b->company->id, 'losses' => '0.00', 'expenses' => '3000.00'],
        ]);
});

it('gives an Admin lacking cost.view no cost figure, a quantity included, and the same Admin granted it every one (PJ-22; SM-25)', function () {
    $admin = Authorization::admin([$this->a->company->id, $this->b->company->id], ['stock.view', 'projects.view']);

    expect([
        $this->figures->lot($admin->id, $this->lot), $this->figures->project($admin->id, $this->project->id),
        $this->figures->companies($admin->id), $this->figures->allocations($admin->id),
    ])->toBe([null, null, [], []]);

    Authorization::grant($admin, 'cost.view');

    expect($this->figures->lot($admin->id, $this->lot))->toBe(['remaining_qty' => '3.000', 'remaining_cost' => '36000.00'])
        ->and($this->figures->project($admin->id, $this->project->id)['direct_cost'] ?? null)->toBe('72000.00')
        ->and($this->figures->companies($admin->id))->toHaveCount(2)
        ->and($this->figures->allocations($admin->id))->toHaveCount(1);
});

it('gives an account with no company grant nothing, the cost family notwithstanding (CS-01; AZ-11)', function () {
    $none = Authorization::admin([], ['cost.view', 'stock.view'])->id;

    expect([
        $this->figures->lot($none, $this->lot), $this->figures->project($none, $this->project->id),
        $this->figures->companies($none), $this->figures->allocations($none),
    ])->toBe([null, null, [], []]);
});
