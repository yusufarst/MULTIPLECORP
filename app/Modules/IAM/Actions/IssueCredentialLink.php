<?php

namespace App\Modules\IAM\Actions;

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\AccountAccess;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * `IAM IssueCredentialLink` (ROUTE-125; INT-125; NX-02; AU-11; AU-12; CI-05): "Buat Tautan Atur Kata Sandi" — an
 * ONBOARDING link while the account has never set a password through a link, a RESET link afterwards —, the account's
 * open link of the same purpose revoked first (AU-11's reissue). The link is shown once to the acting Owner as
 * non-persisted flash data, built from the configured application URL (WS-11), for out-of-band delivery; a replay
 * returns the link's record, never the link: the Owner revokes it and issues another (CI-05). Its audit event names
 * the link's identifier, never its token (LG-03). Never the acting Owner's own account (AUTH_JOURNEYS §2.10).
 */
final class IssueCredentialLink
{
    public const TYPE = 'IAM.IssueCredentialLink';

    /** @var array{token: string, id: int, expires_at: CarbonImmutable, purpose: string}|null */
    private ?array $issued = null;

    public function __construct(private readonly AccountAccess $access) {}

    public function run(string $commandId, int $actorUserId, User $target): Outcome
    {
        return $this->access->run($commandId, self::TYPE, $actorUserId, 'users.manage', $target->public_id, [],
            function (CommandContext $context) use ($target): CommandResult {
                $this->issued = null;
                $context->lock($this->access->locks($target->id, false));
                $row = User::query()->findOrFail($target->id);

                if ($row->id === $context->request->actorUserId) {
                    throw Refused::rejected('OD-01', ['reason' => 'self']);
                }

                $purpose = self::purposeFor($row->id);
                $link = $this->access->issueLink($row->id, $purpose, $context->request->actorUserId, $context->now());
                $this->access->recordLink($context, $row, $link, $purpose, $purpose === 'RESET' ? 'OWNER_ISSUED' : null);
                $this->issued = [...$link, 'purpose' => $purpose];

                return new CommandResult('credential_link', $link['id']);
            },
            fn (Outcome $outcome): ?array => $outcome->resultId === null ? null : self::record($outcome->resultId));
    }

    /**
     * The link this command issued, for the one view; none on a replay.
     *
     * @return array{token: string, id: int, expires_at: CarbonImmutable, purpose: string}|null
     */
    public function issued(): ?array
    {
        return $this->issued;
    }

    /** ONBOARDING until the account has set a password through a link, RESET afterwards (AU-11). */
    public static function purposeFor(int $userId): string
    {
        return DB::table('credential_tokens')->where('user_id', $userId)->whereNotNull('used_at')->exists() ? 'RESET' : 'ONBOARDING';
    }

    /**
     * A link's record — its identifier, purpose, expiry and state — for a replayed issue (CI-05), never its token.
     *
     * @return array<string, mixed>|null
     */
    public static function record(int $linkId): ?array
    {
        $link = DB::table('credential_tokens')->where('id', $linkId)->first(['id', 'purpose', 'issued_at', 'expires_at', 'used_at', 'revoked_at']);

        if ($link === null) {
            return null;
        }

        $expires = is_string($link->expires_at) ? CarbonImmutable::parse($link->expires_at) : null;

        return [
            'link' => $link->id,
            'purpose' => $link->purpose,
            'expires_at' => $expires?->toIso8601ZuluString(),
            'state' => $link->used_at !== null ? 'used' : ($link->revoked_at !== null ? 'revoked' : ($expires === null || $expires->isPast() ? 'expired' : 'open')),
        ];
    }
}
