<?php

namespace Tests\Support\Fixtures;

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\CAT\ProductBarcodeFactory;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Database\Factories\INV\LocationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Numbering;
use Tests\Support\PaymentWorld;
use Tests\Support\Projects;
use Tests\Support\SettlementWorld;
use Tests\Support\StockWorld;

/**
 * TL-09 (TASK-062): the starting preconditions of the critical journeys CJ-05, CJ-07 and CJ-08 that no module fixture
 * holds — master data and stock received through the real receipt, never a step a journey walks. Synthetic data only.
 *
 * - `create` (CJ-05): over PurchaseFixture's company and confirmed project, ten usable units of the project's product
 *   received at a rack of their own — no reservation, no condition change, so the journey makes FX-01 7–9's figures on
 *   the pages —, and a second product at another rack held by the company and the other company, for the pending
 *   unattributed loss (UXS-39).
 * - `twin` (CJ-07): a code both the project's product (as its barcode) and another product (as its SKU) answer to, the
 *   ambiguous scan of UXS-34.
 * - `siplah` (CJ-08): over ProjectFixture's company, the invoice layouts' numbering, the SIPLAH order to confirm on, a
 *   bank account with a transfer proof, and SIPLAH's fee advice proving 300,000 (FX-01 6).
 */
final class JourneyStockFixture
{
    public static function create(Request $request): JsonResponse
    {
        $actor = $request->user()?->getAuthIdentifier();
        $actor = is_int($actor) ? $actor : 0;
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $other = Company::query()->where('public_id', (string) $request->input('other'))->firstOrFail();
        $project = Project::query()->where('public_id', (string) $request->input('project'))->firstOrFail();
        Numbering::goLive(substr(BusinessDate::today(), 0, 4).'-01-01');
        $demand = DB::table('project_lines')->where('project_id', $project->id)->orderBy('line_no')->first(['id', 'product_id']);
        $unit = $demand === null ? null : DB::table('product_units')->where('product_id', $demand->product_id)->where('is_base', true)->value('unit_id');

        if ($demand === null || ! is_numeric($unit)) {
            return response()->json(['error' => 'no demand'], 500);
        }

        // Two racks of their own tags, so neither code is part of the other's in a rack list.
        $rack = LocationFactory::new()->createOne(['code' => 'RAK-'.Str::upper(Str::random(5)), 'name' => 'Rak Opname Uji']);
        $shelf = LocationFactory::new()->createOne(['code' => 'RAK-'.Str::upper(Str::random(5)), 'name' => 'Rak Selisih Uji']);
        $product = (int) $demand->product_id;
        $lot = StockWorld::lot($actor, $company, $product, (int) $unit, $rack->id);

        $pending = Projects::product();
        $pending['product']->forceFill(['name' => 'Barang Selisih '.Str::upper(Str::random(5))])->save();
        StockWorld::lot($actor, $company, (int) $pending['product']->id, (int) $pending['base']->id, $shelf->id, '6');
        StockWorld::lot($actor, $other, (int) $pending['product']->id, (int) $pending['base']->id, $shelf->id, '4', '10000');

        $row = DB::table('products')->where('id', $product)->first(['public_id', 'name', 'sku']);

        return response()->json([
            'product' => $row?->public_id,
            'name' => $row?->name,
            'sku' => $row?->sku,
            'rack' => $rack->code,
            'lot' => DB::table('inventory_lots')->where('id', $lot)->value('lot_code'),
            'pending_product' => DB::table('products')->where('id', $pending['product']->id)->value('public_id'),
            'pending_name' => $pending['product']->name,
            'pending_rack' => $shelf->code,
            'other_code' => $other->code,
        ]);
    }

    /** The project's product answering a code by its barcode, another product by its SKU: two candidates, none chosen (SF-SCAN). */
    public static function twin(Request $request): JsonResponse
    {
        $project = Project::query()->where('public_id', (string) $request->input('project'))->firstOrFail();
        $product = DB::table('project_lines')->where('project_id', $project->id)->orderBy('line_no')->value('product_id');

        if (! is_numeric($product)) {
            return response()->json(['error' => 'no demand'], 500);
        }

        $tag = Str::upper(Str::random(6));
        $code = "KEMBAR-{$tag}";
        $twin = Projects::product();
        $twin['product']->forceFill(['name' => "Barang Kembar {$tag}", 'sku' => $code])->save();
        ProductBarcodeFactory::new()->createOne(['product_id' => (int) $product, 'code' => $code]);

        return response()->json(['code' => $code, 'name' => "Barang Kembar {$tag}"]);
    }

    public static function siplah(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $year = substr(BusinessDate::today(), 0, 4);
        Numbering::goLive("{$year}-01-01");
        NumberingFixture::arm(Request::create('/', 'POST', ['go_live' => "{$year}-01-01"]));
        $start = CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC');

        foreach ([[SequenceType::Doc06, 'INV'], [SequenceType::Doc04, 'NOTA'], [SequenceType::Doc05, 'KW'], [SequenceType::Doc07, 'LMP'], [SequenceType::Doc13, 'SPP']] as [$type, $prefix]) {
            Numbering::scheme($company, $type, "{$year}-01-01", 'YEAR', "UJI/{$prefix}/{tahun}/{nomor:4}");
            Numbering::started($company, $type, $start);
        }

        foreach (['DOC-04', 'DOC-05', 'DOC-06', 'DOC-07', 'DOC-13'] as $layout) {
            DocumentTypeFactory::ensure($layout);
        }

        EvidenceDocumentFactory::new()->createOne(['company_id' => $company->id, 'evidence_type' => 'SIPLAH_ORDER', 'declared_issuer' => 'SIPLAH Uji '.$company->code]);
        PaymentWorld::account($company->id);
        PaymentWorld::proof($company->id, 'PAYMENT_PROOF', 'Bank Uji '.$company->code);
        SettlementWorld::evidence($company->id, 'FEE_ADVICE', '300000');

        return response()->json(['ok' => true]);
    }
}
