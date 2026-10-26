<?php

use App\Modules\IAM\Authentication\CredentialLinks;
use App\Modules\IAM\Authentication\GoogleIdentities;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Recovery\IncidentRuns;
use App\Modules\IAM\Recovery\OwnerRecoveryLinks;
use App\Modules\IAM\Recovery\RecoveryRefused;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\OwnerTrail;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-04 and TL-14 (TASK-020; SECURITY AU-14, AU-20 e; SM-19, SM-21; M-46; criterion 2): the Owner-recovery command
// issues an OWNER_RECOVERY link for an active OWNER-role account only, changing nothing else, its events naming the
// link and the operator, never a user row; its use clears the TOTP fields, ends every session, revokes the other links
// and unlinks Google with reason OWNER_RECOVERY and no actor. Until an incident run's recovery ends, its guard: no
// confirmer only for a pre-window Owner; every other Owner only with a recovered pre-window Owner as confirmer.

uses(RefreshDatabase::class);

beforeEach(function () {
    OwnerTrail::catalogue();
    $this->now = CarbonImmutable::now();
    $this->owner = OwnerTrail::account('OWNER', $this->now->subDays(30), email: 'ratna@contoh.test');
    $this->confirmer = OwnerTrail::account('OWNER', $this->now->subDays(30), email: 'bayu@contoh.test');
    $this->links = app(OwnerRecoveryLinks::class);
});

function issueRecovery(object $test, string $email, ?string $confirmer = null): array
{
    return $test->links->issue($email, 'Kode pemulihan hilang dan tidak ada Owner lain', OwnerTrail::operator(), $confirmer);
}

it('issues a link for an active Owner only, its events naming the link and the operator, no user row (AU-14; SM-19; criterion 2)', function () {
    $admin = OwnerTrail::account('ADMIN_OPERASIONAL', $this->now->subDays(30), email: 'citra@contoh.test');
    $inactive = OwnerTrail::account('OWNER', $this->now->subDays(30), email: 'dewi@contoh.test');
    OwnerTrail::setActive($inactive, false, $this->now->subDay(), $this->confirmer->id);
    $password = (string) $this->owner->fresh()?->password;

    expect(fn () => issueRecovery($this, 'citra@contoh.test'))->toThrow(RecoveryRefused::class, 'not an active account holding the OWNER role')
        ->and(fn () => issueRecovery($this, 'dewi@contoh.test'))->toThrow(RecoveryRefused::class, 'not an active account holding the OWNER role')
        ->and(fn () => issueRecovery($this, 'tidak-ada@contoh.test'))->toThrow(RecoveryRefused::class, 'No account')
        ->and(fn () => $this->links->issue('ratna@contoh.test', ' ', OwnerTrail::operator()))->toThrow(RecoveryRefused::class, '--reason')
        ->and(DB::table('credential_tokens')->count())->toBe(0);

    $issued = issueRecovery($this, 'RATNA@contoh.test');
    $link = DB::table('credential_tokens')->sole();
    $audit = DB::table('audit_events')->where('action', IncidentRuns::LINK_ISSUED)->sole();
    $metadata = json_decode((string) $audit->metadata, true);

    expect($issued['account']->id)->toBe($this->owner->id)
        ->and($link->purpose)->toBe('OWNER_RECOVERY')
        ->and($link->issued_by)->toBeNull()
        ->and($link->token_hash)->toBe(CredentialLinks::hash($issued['token']))
        ->and($audit->actor_user_id)->toBeNull()
        ->and($audit->reason)->toBe('Kode pemulihan hilang dan tidak ada Owner lain')
        ->and($metadata['credential_link'])->toBe($link->id)
        ->and($metadata['operator']['name'])->toBe(OwnerTrail::OPERATOR)
        ->and(json_encode($metadata))->not->toContain($issued['token'])
        ->and(DB::table('security_events')->where('event_kind', 'OWNER_RECOVERY_LINK_ISSUED')->where('user_id', $this->owner->id)->whereNull('actor_user_id')->count())->toBe(1)
        // Issuing changes nothing else (AU-14).
        ->and((string) $this->owner->fresh()?->password)->toBe($password)
        ->and(User::query()->count())->toBe(4);
});

it('clears the TOTP fields, ends every session, revokes other links and unlinks Google when the link is used (AU-20 e; AU-24; SM-19)', function () {
    TwoFactor::enrol($this->owner);
    app(GoogleIdentities::class)->link($this->owner->fresh() ?? $this->owner, 'sub-ratna', 'ratna@gmail.example');
    DB::table('sessions')->insert(['id' => 'sesi-lama', 'user_id' => $this->owner->id, 'payload' => '', 'last_activity' => time()]);
    $other = app(CredentialLinks::class)->issue($this->owner, 'RESET', $this->confirmer->id);
    $issued = issueRecovery($this, 'ratna@contoh.test');

    app(CredentialLinks::class)->consume($issued['token'], 'Rapat-Gudang-24!', null, null);

    $row = DB::table('users')->where('id', $this->owner->id)->first();
    $identity = DB::table('user_external_identities')->where('user_id', $this->owner->id)->sole();
    $linkId = DB::table('credential_tokens')->where('purpose', 'OWNER_RECOVERY')->value('id');

    expect([$row?->two_factor_secret, $row?->two_factor_recovery_codes, $row?->two_factor_confirmed_at, $row?->two_factor_last_step])->toBe([null, null, null, null])
        ->and(DB::table('sessions')->where('user_id', $this->owner->id)->count())->toBe(0)
        ->and(DB::table('credential_tokens')->where('token_hash', CredentialLinks::hash($other))->value('revoked_at'))->not->toBeNull()
        ->and($identity->unlink_reason)->toBe('OWNER_RECOVERY')
        ->and($identity->unlinked_by)->toBeNull()
        ->and(DB::table('audit_events')->where('action', 'IAM.TotpReset')->whereNull('actor_user_id')->whereRaw("metadata->>'credential_link' = ?", [(string) $linkId])->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'IAM.GoogleUnlinked')->whereNull('actor_user_id')->whereRaw("metadata->>'credential_link' = ?", [(string) $linkId])->count())->toBe(1)
        ->and(DB::table('security_events')->where('user_id', $this->owner->id)->whereIn('event_kind', ['PASSWORD_RESET_COMPLETED', 'TOTP_RESET', 'GOOGLE_UNLINKED'])->pluck('event_kind')->sort()->values()->all())
        ->toBe(['GOOGLE_UNLINKED', 'PASSWORD_RESET_COMPLETED', 'TOTP_RESET']);
});

it('keeps the TOTP fields of an account using a RESET link — only an OWNER_RECOVERY link resets TOTP (AU-11)', function () {
    TwoFactor::enrol($this->owner);
    $token = app(CredentialLinks::class)->issue($this->owner, 'RESET', $this->confirmer->id);

    app(CredentialLinks::class)->consume($token, 'Rapat-Gudang-24!', null, null);

    expect(DB::table('users')->where('id', $this->owner->id)->value('two_factor_confirmed_at'))->not->toBeNull()
        ->and(DB::table('audit_events')->where('action', 'IAM.TotpReset')->count())->toBe(0);
});

describe('during an incident run\'s recovery (AU-14\'s guard; M-46; SM-21)', function () {
    beforeEach(function () {
        $this->windowStart = $this->now->subDays(10);
        $this->runId = OwnerTrail::run($this->windowStart, $this->now->subHour(), $this->now->subMinutes(30));
        // The confirmer is a recovered pre-window Owner: its own OWNER_RECOVERY link used after the window.
        OwnerTrail::recovered($this->confirmer, $this->now->subMinutes(20));
        $this->formerOwner = OwnerTrail::account('OWNER', $this->now->subDays(30), email: 'eko@contoh.test');
        OwnerTrail::setActive($this->formerOwner, false, $this->now->subDays(20), $this->owner->id);
        OwnerTrail::setActive($this->formerOwner, true, $this->now->subDays(5), $this->owner->id);
    });

    it('issues without a confirmer to a pre-window Owner, its events naming the run and its window start', function () {
        OwnerTrail::changeRole($this->owner, 'ADMIN_OPERASIONAL', $this->now->subDays(6), $this->confirmer->id);
        OwnerTrail::changeRole($this->owner, 'OWNER', $this->now->subDays(5), $this->confirmer->id);

        $issued = issueRecovery($this, 'ratna@contoh.test');

        $metadata = json_decode((string) DB::table('audit_events')->where('action', IncidentRuns::LINK_ISSUED)->value('metadata'), true);
        $details = json_decode((string) DB::table('security_events')->where('event_kind', 'OWNER_RECOVERY_LINK_ISSUED')->value('details'), true);
        expect($issued['run']?->id)->toBe($this->runId)
            ->and($metadata['incident_run'])->toBe($this->runId)
            ->and($metadata['window_start'])->toBe(IncidentRuns::format($this->windowStart))
            ->and($metadata['confirmer_public_id'])->toBeNull()
            ->and($details['incident_run'])->toBe($this->runId);
    });

    it('refuses a reactivated former Owner without a confirmer, and issues it with a recovered pre-window Owner named', function () {
        expect(fn () => issueRecovery($this, 'eko@contoh.test'))->toThrow(RecoveryRefused::class, '--confirmer');

        $issued = issueRecovery($this, 'eko@contoh.test', 'bayu@contoh.test');

        $details = json_decode((string) DB::table('security_events')->where('event_kind', 'OWNER_RECOVERY_LINK_ISSUED')->value('details'), true);
        expect($issued['confirmer']?->id)->toBe($this->confirmer->id)
            ->and(json_decode((string) DB::table('audit_events')->where('action', IncidentRuns::LINK_ISSUED)->value('metadata'), true)['confirmer_public_id'])->toBe($this->confirmer->public_id)
            ->and($details['confirmer_public_id'])->toBe($this->confirmer->public_id);
    });

    it('refuses a confirmer that was not a pre-window Owner, one not recovered after the window, one no longer an active Owner, and the account itself', function () {
        $windowOwner = OwnerTrail::account('ADMIN_OPERASIONAL', $this->now->subDays(30), email: 'fajar@contoh.test');
        OwnerTrail::changeRole($windowOwner, 'OWNER', $this->now->subDays(5), $this->owner->id);
        OwnerTrail::recovered($windowOwner, $this->now->subMinutes(15));

        expect(fn () => issueRecovery($this, 'eko@contoh.test', 'fajar@contoh.test'))->toThrow(RecoveryRefused::class, 'did not hold the OWNER role')
            ->and(fn () => issueRecovery($this, 'eko@contoh.test', 'ratna@contoh.test'))->toThrow(RecoveryRefused::class, 'no OWNER_RECOVERY link used after the window')
            ->and(fn () => issueRecovery($this, 'eko@contoh.test', 'eko@contoh.test'))->toThrow(RecoveryRefused::class, 'never confirms itself');

        OwnerTrail::changeRole($this->confirmer, 'ADMIN_OPERASIONAL', $this->now->subMinutes(5), $this->owner->id);

        expect(fn () => issueRecovery($this, 'eko@contoh.test', 'bayu@contoh.test'))->toThrow(RecoveryRefused::class, 'not an active Owner now')
            ->and(DB::table('credential_tokens')->count())->toBe(0);
    });

    it('refuses a deactivated pre-window Owner before its restoration, and any issue before the incident command completes', function () {
        OwnerTrail::setActive($this->owner, false, $this->now->subDays(4), $this->confirmer->id);

        expect(fn () => issueRecovery($this, 'ratna@contoh.test'))->toThrow(RecoveryRefused::class, 'not an active account');

        OwnerTrail::run($this->windowStart, $this->now->subMinutes(10), null, '00000000-0000-4000-8000-000000000002');

        expect(fn () => issueRecovery($this, 'bayu@contoh.test'))->toThrow(RecoveryRefused::class, 'has not completed')
            ->and(DB::table('credential_tokens')->count())->toBe(0);
    });
});

it('prints the link once for the Owner, through the console command (AU-14)', function () {
    $this->artisan('iam:owner-recovery-link', ['email' => 'ratna@contoh.test', '--operator' => OwnerTrail::OPERATOR, '--reason' => 'Uji'])
        ->expectsOutputToContain('/atur-kata-sandi#')
        ->assertExitCode(0);
    $this->artisan('iam:owner-recovery-link', ['email' => 'ratna@contoh.test', '--operator' => OwnerTrail::OPERATOR])
        ->expectsOutputToContain('--reason')
        ->assertExitCode(1);
});
