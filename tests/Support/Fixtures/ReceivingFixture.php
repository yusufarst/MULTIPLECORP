<?php

namespace Tests\Support\Fixtures;

use App\Modules\CAT\Models\Product;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\INV\LocationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Numbering;
use Tests\Support\Projects;
use Tests\Support\ReceivingWorld;

/**
 * TL-09–TL-12 (TASK-039): over PurchaseFixture's company and purchase, the Nota's numbering, a rack, a purchase of a serialized product, and a purchase of the confirmed project's demand. Synthetic data only.
 */
final class ReceivingFixture
{
    public static function create(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $year = substr(BusinessDate::today(), 0, 4);
        Numbering::goLive("{$year}-01-01");
        Numbering::scheme($company, SequenceType::Doc12, "{$year}-01-01", 'YEAR', 'UJI/NTB/{tahun}/{nomor:4}');
        Numbering::started($company, SequenceType::Doc12, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
        DocumentTypeFactory::ensure('DOC-12');

        $tag = Str::upper(Str::random(5));
        $rack = LocationFactory::new()->createOne(['code' => "RAK-{$tag}", 'name' => "Rak Uji {$tag}"]);
        $serial = Projects::product();
        $serial['product']->forceFill(['name' => "Barang Berseri {$tag}"])->save();
        Product::query()->whereKey($serial['product']->id)->toBase()->update(['is_serialized' => true]);
        $serialPurchase = ReceivingWorld::purchase($company, $serial['product']->id, $serial['base']->id, '2', '5000');

        $project = Project::query()->where('public_id', (string) $request->input('project'))->firstOrFail();
        $demand = DB::table('project_lines')->where('project_id', $project->id)->orderBy('line_no')->first(['id', 'product_id']);
        $unit = $demand === null ? null : DB::table('product_units')->where('product_id', $demand->product_id)->where('is_base', true)->value('unit_id');

        if ($demand === null || ! is_numeric($unit)) {
            return response()->json(['error' => 'no demand'], 500);
        }

        $projectPurchase = ReceivingWorld::purchase($company, (int) $demand->product_id, (int) $unit, '10', '1000', $project->id, (int) $demand->id);
        $sku = DB::table('products')->where('id', $demand->product_id)->value('sku');

        return response()->json([
            'rack' => $rack->code,
            'sku' => is_string($sku) ? $sku : '',
            'serial_purchase' => $serialPurchase->public_id,
            'serial_number' => $serialPurchase->purchase_number,
            'serial_name' => "Barang Berseri {$tag}",
            'project_purchase' => $projectPurchase->public_id,
        ]);
    }
}
