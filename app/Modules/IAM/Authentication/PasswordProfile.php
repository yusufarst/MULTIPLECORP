<?php

namespace App\Modules\IAM\Authentication;

use App\Modules\IAM\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * SECURITY AU-03's profile AICWDF-COMPAT-8 (D-SEC-15) for a new password, normalized to NFC first: 8 to 128
 * characters of printable Unicode, spaces allowed, with a lowercase and an uppercase letter, a digit and a symbol;
 * refused when it is on the local list of common passwords, when it contains the account's own name or e-mail,
 * the product's name or a company's name, and — the remote check last, only for a password that passed every
 * local rule — when the breached-password range check finds it (BreachedPasswordCheck). With bcrypt as the
 * runtime's fallback, more than 72 bytes is refused, never truncated (AU-02).
 */
final class PasswordProfile implements ValidationRule
{
    public const MINIMUM = 8;

    public const MAXIMUM = 128;

    private const PRODUCT = 'multiplecorp';

    public function __construct(private readonly ?User $account = null) {}

    /**
     * @param  Closure(string): mixed  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = AttemptLogin::password($value);
        $length = mb_strlen($password);

        if ($length < self::MINIMUM || $length > self::MAXIMUM || preg_match('/\p{C}/u', $password) === 1
            || preg_match('/\p{Ll}/u', $password) !== 1 || preg_match('/\p{Lu}/u', $password) !== 1
            || preg_match('/\p{Nd}/u', $password) !== 1 || preg_match('/[^\p{L}\p{N}\s]/u', $password) !== 1
            || (config('hashing.driver') === 'bcrypt' && strlen($password) > 72)) {
            $fail(__('auth_rules.password_profile'));

            return;
        }

        if (CommonPasswords::contains($password)) {
            $fail(__('auth_rules.password_common'));

            return;
        }

        if ($this->containsPersonalText($password)) {
            $fail(__('auth_rules.password_personal'));

            return;
        }

        // IAM's own check, never the framework's deferred default, which skips a failure without recording it.
        if (! app(BreachedPasswordCheck::class)->verify(['value' => $password, 'threshold' => 0])) {
            $fail(__('auth_rules.password_breached'));
        }
    }

    private function containsPersonalText(string $password): bool
    {
        $haystack = mb_strtolower($password);
        $needles = [self::PRODUCT];

        if ($this->account !== null) {
            $needles = [...$needles, ...self::words($this->account->name), ...self::words(explode('@', $this->account->email)[0])];
        }

        foreach (DB::table('companies')->pluck('legal_name') as $name) {
            $needles = [...$needles, ...self::words(is_string($name) ? $name : '')];
        }

        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The words of a name long enough to be personal — four characters or more —, without a company's legal form.
     *
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];

        return array_values(array_filter($words, fn (string $word): bool => mb_strlen($word) >= 4 && ! in_array($word, ['tbk', 'persero'], true)));
    }
}
