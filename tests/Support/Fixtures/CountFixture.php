<?php

namespace Tests\Support\Fixtures;

use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\Support\Authorization;

/**
 * TL-09–TL-12 (TASK-043): the signed-in account, with its confirmed TOTP as these ADM_PLUS capabilities ask, as a Company-A-only Admin who counts, applies and holds the evidence resolution, for PJ-07's projection. Synthetic data only.
 */
final class CountFixture
{
    public static function admin(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();

        if (! $user instanceof User) {
            return response()->json(['error' => 'not signed in'], 401);
        }

        Authorization::grantCompany($user, $company->id);

        // With `upload`, the pool evidence registered in place too (IP-16; TASK-096).
        foreach (['stock.view', 'stock.count', 'stock.count_apply', 'stock.resolve_unattributed_evidence', 'evidence.view', ...($request->boolean('upload') ? ['evidence.upload'] : [])] as $capability) {
            Authorization::grant($user, $capability);
        }

        return response()->json(['ok' => true]);
    }
}
