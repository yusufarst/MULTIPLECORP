<?php

namespace Tests\Support\Fixtures;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\DispatchWorld;
use Tests\Support\Numbering;

/**
 * TL-09–TL-12 (TASK-045): over StockFixture's world, three units of the project's warehouse line shipped from the run's lot, a delivery proof of the company and DOC-08's numbering; the signed-in account records, closes and corrects deliveries and issues their papers. Synthetic data only.
 */
final class DeliveryFixture
{
    public const CAPABILITIES = ['projects.view', 'stock.view', 'cost.view', 'stock.dispatch', 'stock.reserve', 'evidence.view', 'evidence.upload', 'documents.issue', 'delivery.record', 'delivery.close', 'fulfillment.correct', 'stock.adjust'];

    public static function create(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();

        if (! $user instanceof User) {
            return response()->json(['error' => 'not signed in'], 401);
        }

        Authorization::grantCompany($user, $company->id);

        foreach (self::CAPABILITIES as $capability) {
            Authorization::grant($user, $capability);
        }

        $project = DB::table('projects')->where('public_id', (string) $request->input('project'))->value('id');
        $product = DB::table('products')->where('public_id', (string) $request->input('product'))->value('id');
        $line = DB::table('project_lines')->where('project_id', $project)->where('product_id', $product)->value('id');
        $lot = DB::table('inventory_lots')->where('lot_code', (string) $request->input('lot'))->value('id');
        $rack = DB::table('locations')->where('code', (string) $request->input('rack'))->value('id');
        $unit = DB::table('product_units')->where('product_id', $product)->where('is_base', true)->value('unit_id');

        if (! is_numeric($line) || ! is_numeric($lot) || ! is_numeric($rack) || ! is_numeric($unit)) {
            return response()->json(['error' => 'unknown'], 422);
        }

        $word = DispatchWorld::dispatch($user->id, $company->id, (int) $line, (int) $unit, '3', [DispatchWorld::pick((int) $lot, (int) $rack, '3')]);

        if ($word !== 'COMMITTED') {
            return response()->json(['error' => $word], 422);
        }

        $year = substr(BusinessDate::today(), 0, 4);
        Numbering::scheme($company, SequenceType::Doc08, "{$year}-01-01", 'YEAR', 'UJI/BAST/{tahun}/{nomor:4}');
        Numbering::started($company, SequenceType::Doc08, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
        DocumentTypeFactory::ensure('DOC-08');
        DocumentTypeFactory::ensure('DOC-10');
        $proof = EvidenceDocument::factory()->create(['company_id' => $company->id, 'evidence_scope' => 'COMPANY', 'evidence_type' => 'DELIVERY_PROOF', 'declared_issuer' => 'Foto penerimaan', 'issuer_reference' => 'SJ-UJI-'.$request->input('lot')]);
        $shipment = DB::table('dispatch_lines as d')->join('stock_movements as m', 'm.id', '=', 'd.stock_movement_id')->where('d.project_line_id', (int) $line)->orderByDesc('d.id')->value('m.public_id');

        return response()->json(['shipment' => $shipment, 'proof' => $proof->public_id]);
    }
}
