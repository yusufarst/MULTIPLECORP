<?php

namespace Tests\Support\Fixtures;

use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use Database\Factories\INV\LocationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\CostWorld;
use Tests\Support\Numbering;
use Tests\Support\ReservationWorld;
use Tests\Support\StockWorld;

/**
 * TL-09–TL-12 (TASK-040): over PurchaseFixture's company and project, ON HAND 10, RESERVED 3 and UNUSABLE 2 at a rack, a movement to reverse; another company's lot of the same product; a product with a pending case. `grant` gives the signed-in plain account one company and `stock.view`. Synthetic data only.
 */
final class StockFixture
{
    public static function create(Request $request): JsonResponse
    {
        $actor = $request->user()?->getAuthIdentifier();
        $actor = is_int($actor) ? $actor : 0;
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $other = Company::query()->where('public_id', (string) $request->input('other'))->firstOrFail();
        $project = Project::query()->where('public_id', (string) $request->input('project'))->firstOrFail();
        Numbering::goLive(substr(BusinessDate::today(), 0, 4).'-01-01');
        $tag = Str::upper(Str::random(5));
        $rack = LocationFactory::new()->createOne(['code' => "RAK-{$tag}", 'name' => "Rak Uji {$tag}"]);
        $demand = DB::table('project_lines')->where('project_id', $project->id)->orderBy('line_no')->first(['id', 'product_id']);
        $unit = $demand === null ? null : DB::table('product_units')->where('product_id', $demand->product_id)->where('is_base', true)->value('unit_id');

        if ($demand === null || ! is_numeric($unit)) {
            return response()->json(['error' => 'no demand'], 500);
        }

        $product = (int) $demand->product_id;
        $lot = StockWorld::lot($actor, $company, $product, (int) $unit, $rack->id);
        $change = StockWorld::conditionChange($actor, $company, $product, $lot, $rack->id, '2');
        $reserved = ReservationWorld::reserve($actor, (int) $demand->id, '3');

        if ($reserved !== 'COMMITTED') {
            return response()->json(['error' => $reserved], 500);
        }

        CostWorld::resume(['company' => $other->id, 'product' => $product, 'location' => $rack->id, 'actor' => $actor])->openingLot('4', '40000.00');
        DB::table('products')->where('id', $product)->update(['min_stock_qty_base' => '50']);
        $row = DB::table('products')->where('id', $product)->first(['public_id', 'name', 'sku']);
        $movement = DB::table('stock_movements')->where('id', $change)->value('public_id');

        return response()->json(['product' => $row?->public_id, 'name' => $row?->name, 'sku' => $row?->sku, 'rack' => $rack->code, 'movement' => $movement, 'lot' => DB::table('inventory_lots')->where('id', $lot)->value('lot_code'), 'other_code' => $other->code, 'other_name' => $other->legal_name]);
    }

    /** A recognized pending case on the fixture's lot: its pending line. */
    public static function pending(Request $request): JsonResponse
    {
        $actor = $request->user()?->getAuthIdentifier();
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $product = DB::table('products')->where('public_id', (string) $request->input('product'))->value('id');
        $lot = DB::table('inventory_lots')->where('lot_code', (string) $request->input('lot'))->value('id');
        $rack = DB::table('locations')->where('code', (string) $request->input('rack'))->value('id');

        if (! is_int($actor) || ! is_numeric($product) || ! is_numeric($lot) || ! is_numeric($rack)) {
            return response()->json(['error' => 'unknown'], 422);
        }

        StockWorld::recognition($actor, $company, (int) $product, (int) $lot, (int) $rack, '1', '5');

        return response()->json(['ok' => true]);
    }

    /** The signed-in plain account: one company and `stock.view`, a Company-A-only Admin (UXS-20). */
    public static function grant(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();

        if (! $user instanceof User) {
            return response()->json(['error' => 'not signed in'], 401);
        }

        Authorization::grantCompany($user, $company->id);
        Authorization::grant($user, 'stock.view');

        return response()->json(['ok' => true]);
    }
}
