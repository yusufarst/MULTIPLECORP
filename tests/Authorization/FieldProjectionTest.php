<?php

use App\Http\Resources\ProjectedResource;
use App\Modules\IAM\Models\User;
use App\Modules\INV\Http\Resources\InventoryLotResource;
use App\Modules\INV\Models\InventoryLot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-012): SM-25 for the families that exist — a field outside the viewer's entitlement is absent
// from the resource, not empty — and SM-12's no-oracle rule: a projected-away field is never sent to be
// hidden, so neither its value nor its presence reaches the viewer.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->lot = InventoryLot::factory()->create();
});

/** The lot's fields as the given account receives them. */
function lotFieldsFor(?User $user, InventoryLot $lot): array
{
    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);
    $loaded = InventoryLot::query()->with(InventoryLotResource::relationsFor(ProjectedResource::viewer($request)))->findOrFail($lot->id);

    return InventoryLotResource::make($loaded)->resolve($request);
}

it('sends each family only to its entitlement', function (Closure $viewer, array $keys) {
    expect(array_keys(lotFieldsFor($viewer->call($this), $this->lot)))->toBe($keys);
})->with([
    'a guest' => [fn () => null, []],
    'an account without stock.view' => [fn () => Authorization::admin([$this->lot->source_company_id], ['projects.view']), []],
    'stock.view outside the source company' => [fn () => Authorization::admin([], ['stock.view']), ['lot_code', 'product', 'source_company']],
    'stock.view within the source company' => [fn () => Authorization::admin([$this->lot->source_company_id], ['stock.view']), ['lot_code', 'product', 'source_company', 'acquisition_kind', 'business_date', 'acquired_qty_base']],
    'stock.view and cost.view within it' => [fn () => Authorization::admin([$this->lot->source_company_id], ['stock.view', 'cost.view']), ['lot_code', 'product', 'source_company', 'acquisition_kind', 'business_date', 'acquired_qty_base', 'cost_basis_flagged']],
    'the Owner' => [fn () => Authorization::owner(), ['lot_code', 'product', 'source_company', 'acquisition_kind', 'business_date', 'acquired_qty_base', 'cost_basis_flagged']],
]);

it('never names, nor reads, the source company outside scope (PJ-01, PJ-04)', function () {
    $company = $this->lot->sourceCompany()->sole();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $fields = json_encode(lotFieldsFor(Authorization::admin([], ['stock.view']), $this->lot));
    $companyReads = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'from "companies"') && ! str_contains($query['query'], 'false'));

    expect($fields)->not->toContain($company->public_id, $company->code, $company->legal_name)
        ->and($companyReads)->toBe([]);
});
