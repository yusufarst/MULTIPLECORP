<?php

namespace Tests\Support;

use App\Modules\IAM\Authentication\Totp;
use App\Modules\IAM\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * The tests' authenticator (TASK-018): an account's TOTP confirmed as the confirmation writes it — the secret and
 * the recovery codes encrypted with the application key, the confirmation time and the last accepted step, the four
 * fields together (AU-16, AU-19, AU-21; C-62) —, and the code of a secret at a step of the controlled clock.
 */
final class TwoFactor
{
    public const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    /** @var list<string> */
    public const CODES = ['AAAAAAAAAA-BBBBBBBBBB', 'CCCCCCCCCC-DDDDDDDDDD', 'EEEEEEEEEE-FFFFFFFFFF', 'GGGGGGGGGG-HHHHHHHHHH',
        'IIIIIIIIII-JJJJJJJJJJ', 'KKKKKKKKKK-LLLLLLLLLL', 'MMMMMMMMMM-NNNNNNNNNN', 'OOOOOOOOOO-PPPPPPPPPP'];

    /** Confirms the account's TOTP with the fixed secret and codes, its last step one before now's. */
    public static function enrol(User $user, ?int $lastStep = null): User
    {
        DB::table('users')->where('id', $user->id)->update([
            'two_factor_secret' => Crypt::encryptString(self::SECRET),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(self::CODES, JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now()->subDay(),
            'two_factor_last_step' => $lastStep ?? Totp::currentStep() - 2,
        ]);

        return User::query()->findOrFail($user->id);
    }

    /** The code of a secret at the step `offset` steps from now's. */
    public static function code(string $secret = self::SECRET, int $offset = 0): string
    {
        return app(Totp::class)->codeAt($secret, Totp::currentStep() + $offset);
    }

    /** @return list<string> the account's stored recovery codes, decrypted */
    public static function storedCodes(User $user): array
    {
        $stored = DB::table('users')->where('id', $user->id)->value('two_factor_recovery_codes');
        $codes = is_string($stored) ? json_decode(Crypt::decryptString($stored), true) : null;

        return is_array($codes) ? array_values(array_filter($codes, 'is_string')) : [];
    }
}
