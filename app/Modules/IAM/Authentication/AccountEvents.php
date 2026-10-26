<?php

namespace App\Modules\IAM\Authentication;

use App\Modules\IAM\Models\User;
use App\Support\SecurityLog\SecurityEventKind;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * An account's own credential and factor events of SECURITY AU-13 (PERMISSIONS_MATRIX PJ-17; LG-05): a link issued —
 * when and by whom —, a link used, the password set or changed, TOTP enrolled, replaced, reset or disabled, its
 * credentials voided, a recovery code used, the codes viewed or regenerated, a second-factor cooldown started, a
 * Google identity linked or unlinked; and its last successful sign-in. The first sign-in after any of them carries
 * MSG-29's notice (H7-14; H7-18), an Owner's reset, reissue or unlink of the account among them (H7-17).
 */
final class AccountEvents
{
    /** @var list<SecurityEventKind> */
    public const KINDS = [
        SecurityEventKind::AccountSetupLinkIssued, SecurityEventKind::AccountSetupCompleted, SecurityEventKind::PasswordResetRequested,
        SecurityEventKind::PasswordResetCompleted, SecurityEventKind::OwnerRecoveryLinkIssued, SecurityEventKind::PasswordChanged,
        SecurityEventKind::TotpEnrolled, SecurityEventKind::TotpReplaced, SecurityEventKind::TotpReset, SecurityEventKind::TotpDisabled,
        SecurityEventKind::CredentialsVoided, SecurityEventKind::RecoveryCodeUsed, SecurityEventKind::RecoveryCodesViewed,
        SecurityEventKind::RecoveryCodesRegenerated, SecurityEventKind::TwoFactorCooldownStarted, SecurityEventKind::GoogleLinked,
        SecurityEventKind::GoogleUnlinked,
    ];

    /** The notice key the landing page reads once, as flash data (MSG-29). */
    public const NOTICE = 'account_notice';

    /**
     * The account's own events, newest first, each with the account that acted when another one did.
     *
     * @return list<array{kind: string, at: string, by: string|null}>
     */
    public function recent(User $user, int $limit = 20): array
    {
        $rows = DB::table('security_events as e')->leftJoin('users as a', 'a.id', '=', 'e.actor_user_id')
            ->where('e.user_id', $user->id)->whereIn('e.event_kind', self::kindValues())
            ->orderByDesc('e.occurred_at')->orderByDesc('e.id')->limit($limit)
            ->get(['e.event_kind', 'e.occurred_at', 'e.actor_user_id', 'a.name'])
            ->map(fn (object $row): array => [
                'kind' => is_string($row->event_kind) ? $row->event_kind : '',
                'at' => CarbonImmutable::parse(is_string($row->occurred_at) ? $row->occurred_at : null)->toIso8601ZuluString(),
                'by' => is_numeric($row->actor_user_id) && (int) $row->actor_user_id !== $user->id && is_string($row->name) ? $row->name : null,
            ]);

        return array_values($rows->all());
    }

    /** The account's last successful sign-in, if any. */
    public function lastLogin(User $user): ?string
    {
        $at = DB::table('security_events')->where('user_id', $user->id)->where('event_kind', SecurityEventKind::LoginSucceeded->value)->max('occurred_at');

        return is_string($at) ? CarbonImmutable::parse($at)->toIso8601ZuluString() : null;
    }

    /**
     * The newest event since the account's previous successful sign-in, read before this one is recorded: MSG-29's
     * notice at its first sign-in after the event (AU-13; H7-18).
     *
     * @return array{kind: string, at: string, by: string|null}|null
     */
    public function noticeSince(User $user): ?array
    {
        $previous = $this->lastLogin($user);

        if ($previous === null) {
            return null;
        }

        $latest = $this->recent($user, 1)[0] ?? null;

        return $latest !== null && $latest['at'] > $previous ? $latest : null;
    }

    /** @return list<string> */
    private static function kindValues(): array
    {
        return array_map(fn (SecurityEventKind $kind): string => $kind->value, self::KINDS);
    }
}
