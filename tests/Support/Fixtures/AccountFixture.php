<?php

namespace Tests\Support\Fixtures;

use App\Modules\IAM\Authentication\CredentialLinks;
use App\Modules\IAM\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The sign-in journeys' fixture of the local stack (TASK-017): a synthetic account for one run — its e-mail named by
 * the run, its password a test value of this file —, with a reset link of AU-11 issued by a synthetic Owner, so the
 * browser suite signs in, sets a password from the link and logs out against the real routes (TL-09). No real
 * person, address or credential.
 */
final class AccountFixture
{
    public const PASSWORD = 'Kata-Sandi-Uji-9';

    public static function create(Request $request, CredentialLinks $links): JsonResponse
    {
        $run = preg_match('/^[a-z0-9-]{1,40}$/', (string) $request->input('run')) === 1 ? (string) $request->input('run') : Str::lower(Str::random(12));
        $owner = (new User)->forceFill(['name' => 'Owner Uji', 'email' => "owner-{$run}@contoh.test", 'password' => self::PASSWORD]);
        $owner->save();
        $account = (new User)->forceFill(['name' => 'Rani Uji', 'email' => "rani-{$run}@contoh.test", 'password' => self::PASSWORD]);
        $account->save();

        return response()->json([
            'email' => $account->email,
            'password' => self::PASSWORD,
            'token' => $links->issue($account, 'RESET', $owner->id),
        ]);
    }
}
