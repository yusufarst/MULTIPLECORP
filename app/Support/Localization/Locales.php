<?php

namespace App\Support\Localization;

use Closure;
use Illuminate\Support\Facades\App;

/**
 * The two locales of LOCALIZATION §1–§2: Indonesian by default, English second. Before sign-in the choice is
 * the address key `bahasa`, nothing stored; after sign-in it is the account's own `users.locale`, read into
 * the session at sign-in; an unknown value is ignored and Indonesian applies (INT-001). Documents, exports and
 * business data stay Indonesian whatever the interface language (§3.4; CAP-14).
 */
final class Locales
{
    public const DEFAULT = 'id';

    public const SUPPORTED = ['id', 'en'];

    /** The address key of the pages before sign-in (INT-001). */
    public const ADDRESS_KEY = 'bahasa';

    /** The session key the sign-in sequence fills from `users.locale` (§2; TASK-017, TASK-023). */
    public const SESSION_KEY = 'locale';

    /** A supported locale, or null for anything else — an unknown value is ignored, never an error. */
    public static function supported(mixed $value): ?string
    {
        return is_string($value) && in_array($value, self::SUPPORTED, true) ? $value : null;
    }

    /**
     * Runs the work of a generated or issued document or an export in Indonesian, whatever the interface
     * language, the request's locale restored after it (§3.4; DIR-037 D7).
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public static function forDocuments(Closure $work): mixed
    {
        $interface = App::getLocale();
        App::setLocale(self::DEFAULT);

        try {
            return $work();
        } finally {
            App::setLocale($interface);
        }
    }
}
