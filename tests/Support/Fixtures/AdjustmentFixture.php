<?php

namespace Tests\Support\Fixtures;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use Database\Factories\INV\LocationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\Support\Authorization;

/**
 * TL-09–TL-12 (TASK-042): over StockFixture's world, a second rack to move to and a pool proof to cite; the signed-in account, with its confirmed TOTP as these ADM_PLUS capabilities ask, as a Company-A-only Admin with `stock.adjust` and `stock.condition`, for the routed loss (UXS-22). Synthetic data only.
 */
final class AdjustmentFixture
{
    public static function create(Request $request): JsonResponse
    {
        $tag = Str::upper(Str::random(5));
        $rack = LocationFactory::new()->createOne(['code' => "RAK-{$tag}", 'name' => "Rak Tujuan {$tag}"]);
        // `later`: a proof registered after a recognition a moment ago, a minute on as a person registers it; OD-10 (b) compares the recorded seconds.
        EvidenceDocument::factory()->create([...($request->boolean('later') ? ['recorded_at' => now()->addMinute()] : []), 'company_id' => null, 'evidence_scope' => 'POOL', 'evidence_type' => 'WAREHOUSE_RECORD', 'declared_issuer' => "Gudang {$tag}", 'issuer_reference' => "CAT/{$tag}", 'identity_key' => "gudang {$tag}|WAREHOUSE_RECORD|cat/{$tag}|2026-10-01"]);

        return response()->json(['target' => $rack->code, 'proof' => "Gudang {$tag}"]);
    }

    public static function plain(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();

        if (! $user instanceof User) {
            return response()->json(['error' => 'not signed in'], 401);
        }

        Authorization::grantCompany($user, $company->id);

        foreach (['stock.view', 'stock.adjust', 'stock.condition', 'evidence.view'] as $capability) {
            Authorization::grant($user, $capability);
        }

        return response()->json(['ok' => true]);
    }
}
