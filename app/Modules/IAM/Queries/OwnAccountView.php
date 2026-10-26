<?php

namespace App\Modules\IAM\Queries;

use App\Modules\IAM\Authentication\AccountEvents;
use App\Modules\IAM\Authentication\GoogleSignIn;
use App\Modules\IAM\Authentication\TwoFactorChallenge;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
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

    /** @return array<string, mixed> */
    public function page(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return [];
        }

        $link = DB::table('user_external_identities')->where('user_id', $user->id)->where('provider', 'google')->whereNull('unlinked_at')
            ->first(['email_at_link', 'linked_at']);
        $confirmedAt = $user->getAttribute('two_factor_confirmed_at');

        return [
            'account' => ['name' => $user->name, 'email' => $user->email, 'locale' => $user->getAttribute('locale')],
            'totp' => [
                'active' => $confirmedAt !== null,
                'since' => $confirmedAt instanceof \DateTimeInterface || is_string($confirmedAt) ? CarbonImmutable::parse($confirmedAt)->toIso8601ZuluString() : null,
                'high_risk' => $this->capabilities->requiresTotp($user->id),
                'established' => $request->session()->get(TwoFactorChallenge::METHOD) === 'TOTP',
            ],
            'google' => [
                'enabled' => GoogleSignIn::enabled(),
                'email' => $link === null ? null : $link->email_at_link,
                'since' => $link === null || ! is_string($link->linked_at) ? null : CarbonImmutable::parse($link->linked_at)->toIso8601ZuluString(),
                'pending' => GoogleSignIn::pendingLink($request, $user) !== null,
            ],
            'last_login' => $this->events->lastLogin($user),
            'events' => $this->events->recent($user),
        ];
    }
}
