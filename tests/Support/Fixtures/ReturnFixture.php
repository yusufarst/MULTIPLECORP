<?php

namespace Tests\Support\Fixtures;

use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;

/**
 * TL-09–TL-12 (TASK-044): the signed-in account records supplier returns and closes cases in the fixture's company; the purchase of the stock fixture's lot and one unit's current net cost, for the journey's unrecovered share. Synthetic data only.
 */
final class ReturnFixture
{
    public static function grant(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();

        if (! $user instanceof User) {
            return response()->json(['error' => 'not signed in'], 401);
        }

        Authorization::grantCompany($user, $company->id);

        foreach (['stock.view', 'cost.view', 'projects.view', 'evidence.view', 'stock.reserve', 'purchase_return.record', 'cases.close'] as $capability) {
            Authorization::grant($user, $capability);
        }

        $lot = DB::table('inventory_lots as i')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->join('purchase_lines as l', 'l.id', '=', 'r.purchase_line_id')->join('purchases as p', 'p.id', '=', 'l.purchase_id')
            ->where('i.lot_code', (string) $request->input('lot'))->first(['i.id', 'p.public_id']);
        $cost = $lot === null ? null : DB::table('lot_cost_balances')->where('lot_id', $lot->id)->first(['remaining_qty_base', 'remaining_cost']);
        $unit = $cost === null || BigDecimal::of((string) $cost->remaining_qty_base)->isZero() ? '0.00' : (string) BigDecimal::of((string) $cost->remaining_cost)->dividedBy((string) $cost->remaining_qty_base, 2, RoundingMode::Down);

        return response()->json(['purchase' => $lot?->public_id, 'unit_cost' => $unit]);
    }
}
