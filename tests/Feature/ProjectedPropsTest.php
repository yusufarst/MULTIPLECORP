<?php

use App\Http\Resources\ProjectedResource;
use App\Modules\IAM\Data\Audience;
use App\Modules\INV\Http\Resources\InventoryLotResource;
use App\Modules\INV\Models\InventoryLot;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-012): the props of a sample page for each viewer class (PERMISSIONS_MATRIX §7.1–§7.3; SM-25):
// a lot's physical view pool-wide, its source company named within scope and marked outside it (PJ-01,
// PJ-02, PJ-04), its acquisition facts and cost flag only to their entitlements; a prop the viewer may not
// see is not defined, so a partial reload naming it obtains nothing (WS-07; DP-15), and an admitted one is
// evaluated only when asked for (PF-33, PF-36).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    config(['inertia.testing.ensure_pages_exist' => false]);

    Authorization::catalogue();
    $this->lot = InventoryLot::factory()->create();
    $this->evaluated = [];

    Route::middleware(['web', 'auth', 'capability:stock.view'])->get('/__test/lot', function () {
        $viewer = ProjectedResource::viewer(request());
        $lot = InventoryLot::query()->with(InventoryLotResource::relationsFor($viewer))->findOrFail($this->lot->id);

        return Inertia::render('Test/Lot', [
            'lot' => fn () => InventoryLotResource::make($lot)->resolve(request()),
            ...ProjectedResource::props($viewer, [
                'costs' => [Audience::family('cost.view', $lot->source_company_id), function () {
                    $this->evaluated[] = 'costs';

                    return ['lot_cost' => '150000.00'];
                }],
                'history' => [Audience::family('stock.view', $lot->source_company_id), function () {
                    $this->evaluated[] = 'history';

                    return [];
                }],
            ]),
        ]);
    });
});

afterEach(function () {
    @unlink($this->hotFile);
});

it('gives a pool viewer outside the source company the physical view and the relation marker only', function () {
    $viewer = Authorization::admin([], ['stock.view']);

    $this->actingAs($viewer)->get('/__test/lot')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('lot.lot_code', $this->lot->lot_code)
        ->where('lot.source_company', ['relation' => 'ANOTHER_COMPANY'])
        ->missing('lot.acquisition_kind')
        ->missing('lot.business_date')
        ->missing('lot.acquired_qty_base')
        ->missing('lot.cost_basis_flagged')
        ->missing('costs')
        ->missing('history'));
});

it('names the source company and its acquisition facts within scope, the cost flag only to cost.view', function () {
    $stock = Authorization::admin([$this->lot->source_company_id], ['stock.view']);
    $cost = Authorization::admin([$this->lot->source_company_id], ['stock.view', 'cost.view']);

    $this->actingAs($stock)->get('/__test/lot')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('lot.source_company.public_id', $this->lot->sourceCompany->public_id)
        ->where('lot.acquisition_kind', 'OPENING')
        ->has('lot.acquired_qty_base')
        ->missing('lot.cost_basis_flagged')
        ->missing('costs')
        ->has('history'));

    $this->actingAs($cost)->get('/__test/lot')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('lot.cost_basis_flagged', false)
        ->where('costs.lot_cost', '150000.00'));
});

it('gives the Owner every field', function () {
    $this->actingAs(Authorization::owner())->get('/__test/lot')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('lot.source_company.name')
        ->has('lot.cost_basis_flagged')
        ->has('costs')
        ->has('history'));
});

it('obtains nothing by naming an unauthorized prop in a partial reload, and evaluates only what it names (DP-15; PF-36)', function () {
    $viewer = Authorization::admin([$this->lot->source_company_id], ['stock.view']);
    $version = $this->actingAs($viewer)->get('/__test/lot')->viewData('page')['version'];
    $this->evaluated = [];

    $partial = $this->actingAs($viewer)->get('/__test/lot', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
        'X-Inertia-Partial-Component' => 'Test/Lot',
        'X-Inertia-Partial-Data' => 'costs,history',
    ]);

    expect($partial->json('props'))->not->toHaveKey('costs')
        ->and($partial->json('props'))->toHaveKey('history')
        ->and($partial->json('props'))->not->toHaveKey('lot')
        ->and($this->evaluated)->toBe(['history']);
});
