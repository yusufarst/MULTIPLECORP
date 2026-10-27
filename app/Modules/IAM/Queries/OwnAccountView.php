<?php

namespace App\Modules\IAM\Queries;

use App\Modules\IAM\Authentication\AccountEvents;
use App\Modules\IAM\Authentication\GoogleSignIn;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * PG-065's read (ROUTE-065; SELF; PERMISSIONS_MATRIX PJ-17): the account's own row only — its name and e-mail, its
 * display language, its TOTP status and enrolment time, whether it is high-risk (AU-18), its linked Google e-mail and
 * link date, whether a verified Google identity waits for the confirming request, its last successful sign-in and
 * its own credential and factor events. Its role, effective capabilities and companies are the shell's shared props
 * (WS-07). Never a secret, a code, a provider subject or a hash.
 */
final class OwnAccountView
{
    public function __construct(private readonly EffectiveCapabilities $capabilities, private readonly AccountEvents $events) {}

    /**
     * The page for the signed-in account, with what its session holds: whether it signed in with TOTP, whether a
     * verified Google identity waits for it, and the LINK attempt's answer once.
     *
     * @param  'ready'|'failed'|null  $answer
     * @return array<string, mixed>
     */
    public function page(User $user, bool $established, bool $pending, ?string $answer): array
    {
        $link = DB::table('user_external_identities')->where('user_id', $user->id)->where('provider', 'google')->whereNull('unlinked_at')
            ->first(['email_at_link', 'linked_at']);
        $confirmedAt = $user->getAttribute('two_factor_confirmed_at');

        return [
            'account' => ['name' => $user->name, 'email' => $user->email, 'locale' => $user->getAttribute('locale')],
            'totp' => [
                'active' => $confirmedAt !== null,
                'since' => $confirmedAt instanceof \DateTimeInterface || is_string($confirmedAt) ? CarbonImmutable::parse($confirmedAt)->toIso8601ZuluString() : null,
                'high_risk' => $this->capabilities->requiresTotp($user->id),
                'established' => $established,
            ],
            'google' => [
                'enabled' => GoogleSignIn::enabled(),
                'linked' => $link !== null,
                // The e-mail Google verified at the link, when it did (GoogleIdToken keeps no unverified one).
                'email' => $link === null ? null : $link->email_at_link,
                'since' => $link === null || ! is_string($link->linked_at) ? null : CarbonImmutable::parse($link->linked_at)->toIso8601ZuluString(),
                'pending' => $pending,
                // A LINK attempt's answer from Google's callback, once: `ready` or `failed` (AU-22; AU-23).
                'answer' => $answer,
            ],
            'last_login' => $this->events->lastLogin($user),
            'events' => $this->events->recent($user),
        ];
    }
}
