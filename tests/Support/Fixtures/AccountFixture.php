<?php

namespace Tests\Support\Fixtures;

use App\Modules\IAM\Authentication\CredentialLinks;
use App\Modules\IAM\Authentication\Totp;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\CatalogueSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The sign-in journeys' fixture of the local stack (TASK-017, TASK-018): a synthetic account for one run — its
 * e-mail named by the run, its password a test value of this file —, with a reset link of AU-11 issued by a
 * synthetic Owner, so the browser suite signs in, sets a password from the link and logs out against the real
 * routes (TL-09). `kind=totp` gives the account a confirmed TOTP of a secret made for the run, with its recovery
 * codes, for the challenge and recovery journeys; `kind=owner` makes it an Owner without TOTP, for the enrolment
 * journey (AU-18); `locale` is the account's own language, which applies once it is signed in (AU-13). No real
 * person, address or credential.
 */
final class AccountFixture
{
    public const PASSWORD = 'Kata-Sandi-Uji-9';

    public static function create(Request $request, CredentialLinks $links, Totp $totp): JsonResponse
    {
        $run = preg_match('/^[a-z0-9-]{1,40}$/', (string) $request->input('run')) === 1 ? (string) $request->input('run') : Str::lower(Str::random(12));
        $owner = (new User)->forceFill(['name' => 'Owner Uji', 'email' => "owner-{$run}@contoh.test", 'password' => self::PASSWORD]);
        $owner->save();
        $locale = in_array($request->input('locale'), ['id', 'en'], true) ? $request->input('locale') : 'id';
        $account = (new User)->forceFill(['name' => 'Rani Uji', 'email' => "rani-{$run}@contoh.test", 'password' => self::PASSWORD, 'locale' => $locale]);
        $account->save();
        $answer = ['email' => $account->email, 'password' => self::PASSWORD, 'token' => $links->issue($account, 'RESET', $owner->id)];

        if ($request->input('kind') === 'totp') {
            $secret = $totp->newSecret();
            $codes = $totp->newRecoveryCodes();
            DB::table('users')->where('id', $account->id)->update([
                'two_factor_secret' => Crypt::encryptString($secret),
                'two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes, JSON_THROW_ON_ERROR)),
                'two_factor_confirmed_at' => now()->subDay(),
                'two_factor_last_step' => Totp::currentStep() - 2,
            ]);
            $answer += ['secret' => $secret, 'recovery_codes' => $codes];
        }

        // TASK-019: the account's Google identity, linked by its holder, for the Google journey.
        if (is_string($request->input('google'))) {
            DB::table('user_external_identities')->insert(['user_id' => $account->id, 'provider' => 'google', 'provider_subject' => $request->input('google'), 'email_at_link' => 'akun@contoh.test', 'linked_at' => now(), 'linked_by' => $account->id]);
        }

        if ($request->input('kind') === 'owner') {
            if (! DB::table('roles')->where('code', 'OWNER')->exists()) {
                app(CatalogueSync::class)->run();
            }

            DB::table('user_roles')->insert(['user_id' => $account->id, 'role_id' => DB::table('roles')->where('code', 'OWNER')->value('id'), 'granted_at' => now()]);
        }

        return response()->json($answer);
    }
}
