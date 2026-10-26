<?php

use App\Modules\IAM\Authentication\CredentialLinks;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Recovery\AccountHistory;
use App\Modules\IAM\Recovery\Installation;
use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Modules\IAM\Recovery\OwnerRecoveryLinks;
use App\Modules\IAM\Recovery\RecoveryRefused;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\OwnerTrail;
use Tests\Support\RefreshDatabase;

// TL-04 and TL-14 (TASK-020; PRQ-09; GAP-049; SM-24; DIR-085 OF-06; criteria 1 and 6): the installation step syncs
// the catalogue first, creates exactly one first Owner with the audit event of its OWNER assignment recording the
// operator's identity — the host account and the stated name — and no user row as actor, refuses a second run, and
// leaves the password unusable, set only through the OWNER_RECOVERY link issued right after it.

uses(RefreshDatabase::class);

it('creates exactly one first Owner, the catalogue synced first, its OWNER assignment audited with the operator\'s identity (SM-24; criterion 1)', function () {
    $this->artisan('iam:install-owner', ['--name' => 'Ratna Dewi', '--email' => 'ratna@contoh.test', '--operator' => 'Yusuf Operator'])
        ->expectsOutputToContain('iam:owner-recovery-link ratna@contoh.test')
        ->assertExitCode(0);

    $owner = User::query()->sole();
    $audit = DB::table('audit_events')->where('action', Installation::ACTION)->sole();
    $metadata = json_decode((string) $audit->metadata, true);
    $after = json_decode((string) $audit->after, true);

    expect($owner->is_active)->toBeTrue()
        ->and(DB::table('roles')->where('code', 'OWNER')->where('is_builtin', true)->exists())->toBeTrue()
        ->and(DB::table('capabilities')->count())->toBeGreaterThan(0)
        ->and(app(AccountHistory::class)->current($owner->id)['role'])->toBe('OWNER')
        ->and($audit->actor_user_id)->toBeNull()
        ->and($audit->authority_class)->toBe('SYSTEM')
        ->and($audit->entity_id)->toBe($owner->id)
        ->and($metadata['operator']['name'])->toBe('Yusuf Operator')
        ->and($metadata['operator']['host_account'])->not->toBe('')
        ->and($after[AccountHistory::ROLE])->toBe('OWNER')
        ->and($after[AccountHistory::ACTIVE])->toBeTrue()
        // The trail now establishes the first Owner as a pre-window Owner of any later window (AU-25's obligation).
        ->and(app(AccountHistory::class)->preWindowOwners(CarbonImmutable::now()->addSecond()))->toBe([$owner->id])
        ->and($owner->two_factor_secret)->toBeNull();
});

it('is refused once the workspace is installed, creating nothing (SM-24; RG-05)', function () {
    $installation = app(Installation::class);
    $installation->install('Ratna Dewi', 'ratna@contoh.test', OwnerTrail::operator());

    expect(fn () => $installation->install('Bayu', 'bayu@contoh.test', OwnerTrail::operator()))->toThrow(RecoveryRefused::class, 'installed already')
        ->and(User::query()->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', Installation::ACTION)->count())->toBe(1);

    $this->artisan('iam:install-owner', ['--name' => 'Bayu', '--email' => 'bayu@contoh.test', '--operator' => 'Yusuf Operator'])->assertExitCode(1);
});

it('refuses an operator without a stated name, a missing name or e-mail, and a run outside a console (AU-14; SX-04)', function () {
    $this->artisan('iam:install-owner', ['--name' => 'Ratna Dewi', '--email' => 'ratna@contoh.test'])->expectsOutputToContain('--operator')->assertExitCode(1);
    $this->artisan('iam:install-owner', ['--name' => 'Ratna Dewi', '--email' => 'bukan-surel', '--operator' => 'Yusuf'])->assertExitCode(1);
    $this->artisan('iam:install-owner', ['--email' => 'ratna@contoh.test', '--operator' => 'Yusuf'])->assertExitCode(1);

    $console = (fn () => $this->isRunningInConsole)->call(app());
    (fn () => $this->isRunningInConsole = false)->call(app());

    try {
        expect(fn () => OperatorIdentity::stated('Yusuf'))->toThrow(LogicException::class, 'only in a console')
            ->and(fn () => app(Installation::class)->install('Ratna', 'ratna@contoh.test', OwnerTrail::operator()))->toThrow(LogicException::class);
    } finally {
        (fn () => $this->isRunningInConsole = $console)->call(app());
    }

    expect(User::query()->count())->toBe(0);
});

it('lets the first Owner set a password only through the OWNER_RECOVERY link issued right after it (DIR-085 OF-06; criterion 6)', function () {
    $owner = app(Installation::class)->install('Ratna Dewi', 'ratna@contoh.test', OwnerTrail::operator());
    $unusable = (string) $owner->fresh()?->password;

    // The installation's hash is of an unguessable value: no password the operator or the Owner could know opens it.
    foreach (['', 'Ratna Dewi', 'ratna@contoh.test', 'password'] as $guess) {
        expect(Hash::check($guess, $unusable))->toBeFalse();
    }

    $issued = app(OwnerRecoveryLinks::class)->issue('ratna@contoh.test', 'First Owner set-up at installation', OwnerTrail::operator());
    $used = app(CredentialLinks::class)->consume($issued['token'], 'Rapat-Gudang-24!', null, null);

    expect($used?->id)->toBe($owner->id)
        ->and(Hash::check('Rapat-Gudang-24!', (string) $owner->fresh()?->password))->toBeTrue()
        ->and(DB::table('credential_tokens')->where('user_id', $owner->id)->whereNotNull('used_at')->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'IAM.OwnerRecoveryLinkIssued')->value('reason'))->toBe('First Owner set-up at installation');
});
