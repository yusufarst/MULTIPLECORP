<?php

namespace Tests\Support\Fixtures;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use App\Support\RowHandle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\DeliveryWorld;

/**
 * TL-09–TL-12 (TASK-048; J-FUL-05; CJ-06's return): over DeliveryFixture's shipment, the three units recorded as received by
 * the client and a return proof of the company; the signed-in account takes goods back, reverses a return and closes its
 * case. Synthetic data only.
 */
final class ClientReturnFixture
{
    public const CAPABILITIES = ['sales_return.record', 'stock.reverse', 'cases.close'];

    public static function create(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();

        if (! $user instanceof User) {
            return response()->json(['error' => 'not signed in'], 401);
        }

        foreach (self::CAPABILITIES as $capability) {
            Authorization::grant($user, $capability);
        }

        $dispatch = DB::table('dispatch_lines as d')->join('stock_movements as m', 'm.id', '=', 'd.stock_movement_id')->where('m.public_id', (string) $request->input('shipment'))->first(['d.id', 'd.project_line_id', 'd.project_id']);
        $proof = DB::table('evidence_documents')->where('public_id', (string) $request->input('proof'))->value('id');
        $rack = DB::table('locations')->where('code', (string) $request->input('rack'))->value('id');

        if ($dispatch === null || ! is_numeric($proof) || ! is_numeric($rack)) {
            return response()->json(['error' => 'unknown'], 422);
        }

        $word = DeliveryWorld::record($user->id, (int) $dispatch->project_line_id, [[(int) $dispatch->id, '3']], (int) $proof);

        if ($word !== 'COMMITTED') {
            return response()->json(['error' => $word], 422);
        }

        $returned = EvidenceDocument::factory()->create(['company_id' => $company->id, 'evidence_scope' => 'COMPANY', 'evidence_type' => 'DELIVERY_PROOF', 'declared_issuer' => 'Foto barang kembali', 'issuer_reference' => 'RET-UJI-'.$request->input('shipment')]);

        return response()->json([
            'project_id' => DB::table('projects')->where('id', $dispatch->project_id)->value('public_id'),
            'return_proof' => $returned->public_id,
            'rack_handle' => RowHandle::of('locations', (int) $rack),
        ]);
    }
}
