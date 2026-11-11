<?php

namespace Tests\Support\Fixtures;

use App\Modules\IAM\Models\User;
use App\Modules\INV\Models\SerialUnit;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\CAT\ProductBarcodeFactory;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\Projects;

/**
 * TL-09–TL-12 (TASK-041): over StockFixture's world, DOC-03's numbering for the Surat Jalan's draft; the signed-in plain account's dispatch capability without the inter-company authority, for the routed refusal (UXS-22); the run's serialized units for the scanner flow.
 */
final class DispatchFixture
{
    public static function create(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $year = substr(BusinessDate::today(), 0, 4);
        Numbering::scheme($company, SequenceType::Doc03, "{$year}-01-01", 'YEAR', 'UJI/SJ/{tahun}/{nomor:4}');
        Numbering::started($company, SequenceType::Doc03, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
        DocumentTypeFactory::ensure('DOC-03');

        return response()->json(['ok' => true]);
    }

    /** The run's product turned serialized with three units in stock at its rack, for the scanner flow and the substitution (CJ-07); their numbers. */
    public static function serials(Request $request): JsonResponse
    {
        $product = DB::table('products')->where('public_id', (string) $request->input('product'))->value('id');
        $lot = DB::table('inventory_lots')->where('lot_code', (string) $request->input('lot'))->value('id');
        $rack = DB::table('locations')->where('code', (string) $request->input('rack'))->value('id');

        if (! is_numeric($product) || ! is_numeric($lot) || ! is_numeric($rack)) {
            return response()->json(['error' => 'unknown'], 422);
        }

        DB::table('products')->where('id', $product)->update(['is_serialized' => true]);
        $tag = Str::upper(Str::random(6));
        $numbers = [];

        foreach ([1, 2, 3] as $index) {
            $number = "SN{$tag}{$index}";
            SerialUnit::factory()->create(['product_id' => (int) $product, 'state' => 'IN_STOCK', 'current_lot_id' => (int) $lot, 'current_location_id' => (int) $rack, 'current_condition' => 'USABLE', 'serial_number' => $number, 'serial_key' => strtolower($number)]);
            $numbers[] = $number;
        }

        return response()->json(['serials' => $numbers]);
    }

    /** The signed-in plain account: a Company-A-only Admin with `stock.dispatch` and no `stock.allocate_intercompany`. */
    /** One code two products answer — the run's product by a barcode, another by its SKU —, for an ambiguous scan (UXS-34; TASK-088); the code and the other product's name. */
    public static function twin(Request $request): JsonResponse
    {
        $product = DB::table('products')->where('public_id', (string) $request->input('product'))->value('id');

        if (! is_numeric($product)) {
            return response()->json(['error' => 'unknown product'], 422);
        }

        $tag = Str::upper(Str::random(6));
        $code = "KEMBAR-{$tag}";
        $twin = Projects::product();
        $twin['product']->forceFill(['name' => "Barang Kembar {$tag}", 'sku' => $code])->save();
        ProductBarcodeFactory::new()->createOne(['product_id' => (int) $product, 'code' => $code]);

        return response()->json(['code' => $code, 'name' => "Barang Kembar {$tag}"]);
    }

    public static function plain(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();

        if (! $user instanceof User) {
            return response()->json(['error' => 'not signed in'], 401);
        }

        Authorization::grantCompany($user, $company->id);

        foreach (['stock.view', 'projects.view', 'stock.dispatch'] as $capability) {
            Authorization::grant($user, $capability);
        }

        return response()->json(['ok' => true]);
    }
}
