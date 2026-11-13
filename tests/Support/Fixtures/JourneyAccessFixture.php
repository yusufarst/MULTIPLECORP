<?php

namespace Tests\Support\Fixtures;

use App\Http\Middleware\RequireStepUp;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;

/**
 * TL-09 (TASK-064; CJ-09, CJ-11, CJ-13): the access journeys' preconditions on the local stack, never a step they walk —
 * companies of the run with nothing in them, the signed-in account's own grants for an Admin persona of SECURITY_TEST_MATRIX
 * §2 (a second active grant never inserted: the active-unique keys of `user_company_grants` and `user_capability_grants`),
 * the session's last password confirmation moved 16 minutes back so the next high-risk command meets AU-15's step-up after
 * its 15 minutes (UXS-26), and the two QS-14 sources the browser cannot reach — pool evidence and an AUTHORIZATION_ANOMALY,
 * the latter written by EffectiveCapabilities itself on an account carrying an OWNER_ONLY grant. Synthetic data only.
 */
final class JourneyAccessFixture
{
    /** Companies for the run (`count`, one to three), each with its public identifier, code and legal name. */
    public static function companies(Request $request): JsonResponse
    {
        $count = min(3, max(1, $request->integer('count', 2)));
        $made = [];

        for ($index = 0; $index < $count; $index++) {
            $code = 'J'.Str::upper(Str::random(6));
            $name = "CV Uji Akses {$code}";
            $company = Company::factory()->create(['code' => $code, 'legal_name' => $name]);
            $made[] = ['id' => $company->public_id, 'code' => $code, 'name' => $name];
        }

        return response()->json($made);
    }

    /** The signed-in account's companies and capabilities, each granted once; an OWNER_ONLY capability refused (RG-02). */
    public static function grant(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['error' => 'not signed in'], 401);
        }

        $companies = $request->input('companies', []);
        $asked = $request->input('capabilities', []);
        $capabilities = [];

        foreach (is_array($asked) ? $asked : [] as $code) {
            if (! is_string($code) || ! CapabilityCatalogue::has($code) || CapabilityCatalogue::isOwnerOnly($code)) {
                return response()->json(['error' => 'capability'], 422);
            }

            $capabilities[] = $code;
        }

        foreach (is_array($companies) ? $companies : [] as $publicId) {
            $company = Company::query()->where('public_id', is_string($publicId) ? $publicId : '')->firstOrFail();
            $held = DB::table('user_company_grants')->where('user_id', $user->id)->where('company_id', $company->id)->whereNull('revoked_at')->exists();

            if (! $held) {
                Authorization::grantCompany($user, $company->id);
            }
        }

        foreach ($capabilities as $code) {
            $held = DB::table('user_capability_grants')->where('user_id', $user->id)->whereNull('revoked_at')
                ->where('capability_id', DB::table('capabilities')->where('code', $code)->value('id'))->exists();

            if (! $held) {
                Authorization::grant($user, $code);
            }
        }

        Authorization::forget();

        return response()->json(['ok' => true]);
    }

    /** The session's last password confirmation 16 minutes ago — past AU-15's 15 —, so the next high-risk command asks again. */
    public static function ageStepUp(Request $request): JsonResponse
    {
        $request->session()->put(RequireStepUp::CONFIRMED_AT, now()->subMinutes(16)->getTimestamp());

        return response()->json(['ok' => true]);
    }

    /**
     * Two QS-14 sources for the signed-in Owner's review queue: pool evidence it recorded (*Bukti Gudang Bersama*), and an
     * AUTHORIZATION_ANOMALY of an account of the run carrying an OWNER_ONLY capability in a user grant, which the
     * capability reader records when it next reads that account (*Keamanan*).
     */
    public static function review(Request $request): JsonResponse
    {
        $owner = $request->user();

        if (! $owner instanceof User) {
            return response()->json(['error' => 'not signed in'], 401);
        }

        $tag = Str::upper(Str::random(6));
        $issuer = "Gudang Uji {$tag}";
        EvidenceDocument::factory()->pool()->create([
            'recorded_by' => $owner->id, 'declared_issuer' => $issuer, 'issuer_reference' => "CAT/{$tag}",
            'identity_key' => Str::lower($issuer).'|WAREHOUSE_RECORD|'.Str::lower("cat/{$tag}").'|2026-10-01',
        ]);

        // Its own e-mail: the factories' sequences start again in every request (C-49).
        $holder = User::factory()->create(['name' => "Akun Janggal {$tag}", 'email' => 'janggal-'.Str::lower($tag).'@contoh.test']);
        Authorization::grant($holder, 'review.view');
        app(EffectiveCapabilities::class)->fresh($holder->id);

        return response()->json(['owner' => $owner->public_id, 'issuer' => $issuer, 'anomaly' => $holder->public_id, 'anomaly_name' => "Akun Janggal {$tag}"]);
    }
}
