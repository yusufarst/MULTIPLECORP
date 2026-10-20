<?php

use App\Modules\IAM\Models\Capability;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Models\UserCapabilityGrant;
use App\Modules\IAM\Models\UserCompanyGrant;
use App\Modules\IAM\Models\UserExternalIdentity;
use App\Modules\ORG\Models\Company;
use App\Support\Database\DatabaseRole;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-005): DATABASE §4.1's constraints on the IAM tables, each violated as the application
// runtime and refused by PostgreSQL — C-62 on `users`, C-63 and C-64 on `user_external_identities` —
// and the partial keys of the active grants.

uses(RefreshDatabase::class);

function identityTotpFields(int $present): array
{
    $values = ['two_factor_secret' => 'eyJpdiI6ImNvbnRvaCJ9', 'two_factor_recovery_codes' => 'eyJpdiI6ImtvZGUifQ==', 'two_factor_confirmed_at' => now(), 'two_factor_last_step' => 59_000_000];

    return array_slice($values, 0, $present, true) + array_fill_keys(array_keys(array_slice($values, $present, null, true)), null);
}

it('stores an account with none or all four TOTP fields (C-62)', function (int $present) {
    $user = User::factory()->create(identityTotpFields($present));

    expect(DB::scalar('select num_nonnulls(two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at, two_factor_last_step) from users where id = ?', [$user->id]))->toBe($present);
})->with([0, 4]);

it('refuses an account with some of its TOTP fields (C-62)', function (int $present) {
    Refusal::assert(Refusal::CHECK, 'users_c62_totp_fields_together', fn () => User::factory()->create(identityTotpFields($present)));
})->with([1, 2, 3]);

it('keeps one account per address, whatever its case', function () {
    User::factory()->create(['email' => 'ayu@example.com']);

    Refusal::assert(Refusal::UNIQUE, 'users_email_lower_unique', fn () => User::factory()->create(['email' => 'Ayu@Example.com']));
});

it('refuses a display language other than Indonesian or English', function () {
    Refusal::assert(Refusal::CHECK, 'users_locale_code', fn () => User::factory()->create(['locale' => 'fr']));
});

it('refuses a link to another provider, a link created by another account and a malformed subject (C-63)', function () {
    $holder = User::factory()->create();
    $other = User::factory()->create();

    Refusal::assert(Refusal::CHECK, 'user_external_identities_c63_provider', fn () => UserExternalIdentity::factory()->for($holder)->create(['provider' => 'github', 'linked_by' => $holder->id]));
    Refusal::assert(Refusal::CHECK, 'user_external_identities_c63_linked_by_holder', fn () => UserExternalIdentity::factory()->for($holder)->create(['linked_by' => $other->id]));
    Refusal::assert(Refusal::CHECK, 'user_external_identities_subject_format', fn () => UserExternalIdentity::factory()->for($holder)->create(['linked_by' => $holder->id, 'provider_subject' => str_repeat('1', 256)]));
    Refusal::assert(Refusal::CHECK, 'user_external_identities_subject_format', fn () => UserExternalIdentity::factory()->for($holder)->create(['linked_by' => $holder->id, 'provider_subject' => "12\u{00e9}"]));
});

it('accepts each unlink of the contract with its actor (C-63)', function (string $reason, string $actor) {
    $holder = User::factory()->create();
    $owner = User::factory()->create();
    $link = UserExternalIdentity::factory()->for($holder)->create(['linked_by' => $holder->id]);

    $link->forceFill([
        'unlinked_at' => now(),
        'unlink_reason' => $reason,
        'unlinked_by' => match ($actor) {
            'holder' => $holder->id,
            'another account' => $owner->id,
            'nobody' => null,
        },
    ])->save();

    expect($link->fresh()?->unlink_reason)->toBe($reason);
})->with([
    'the holder unlinks' => ['SELF', 'holder'],
    'the holder disables TOTP' => ['TOTP_DISABLED', 'holder'],
    'an Owner unlinks' => ['OWNER', 'another account'],
    'an Owner resets TOTP' => ['CREDENTIAL_REISSUE', 'another account'],
    'an Owner deactivates the account' => ['DEACTIVATION', 'another account'],
    'an OWNER_RECOVERY link is used' => ['OWNER_RECOVERY', 'nobody'],
]);

it('refuses an unlink outside the contract (C-63)', function (array $unlink, string $constraint) {
    $holder = User::factory()->create();
    $owner = User::factory()->create();
    $link = UserExternalIdentity::factory()->for($holder)->create(['linked_by' => $holder->id]);
    $actors = ['holder' => $holder->id, 'another account' => $owner->id, 'nobody' => null];

    Refusal::assert(Refusal::CHECK, $constraint, fn () => $link->forceFill([
        'unlinked_at' => $unlink['at'] ? now() : null,
        'unlink_reason' => $unlink['reason'],
        'unlinked_by' => $actors[$unlink['by']],
    ])->save());
})->with([
    'a reason outside the list' => [['at' => true, 'reason' => 'EXPIRED', 'by' => 'holder'], 'user_external_identities_c63_unlink_reason'],
    'a reason without a time' => [['at' => false, 'reason' => 'SELF', 'by' => 'holder'], 'user_external_identities_c63_unlink_state'],
    'a time without a reason' => [['at' => true, 'reason' => null, 'by' => 'holder'], 'user_external_identities_c63_unlink_state'],
    'OWNER_RECOVERY with an actor' => [['at' => true, 'reason' => 'OWNER_RECOVERY', 'by' => 'another account'], 'user_external_identities_c63_unlink_state'],
    'a deactivation without an actor' => [['at' => true, 'reason' => 'DEACTIVATION', 'by' => 'nobody'], 'user_external_identities_c63_unlink_state'],
    'SELF by another account' => [['at' => true, 'reason' => 'SELF', 'by' => 'another account'], 'user_external_identities_c63_unlink_actor'],
    'TOTP_DISABLED by another account' => [['at' => true, 'reason' => 'TOTP_DISABLED', 'by' => 'another account'], 'user_external_identities_c63_unlink_actor'],
    'OWNER by the holder' => [['at' => true, 'reason' => 'OWNER', 'by' => 'holder'], 'user_external_identities_c63_unlink_actor'],
    'CREDENTIAL_REISSUE by the holder' => [['at' => true, 'reason' => 'CREDENTIAL_REISSUE', 'by' => 'holder'], 'user_external_identities_c63_unlink_actor'],
]);

it('unlinks once, from NULL, and changes no other column (C-63)', function () {
    $holder = User::factory()->create();
    $link = UserExternalIdentity::factory()->for($holder)->create(['linked_by' => $holder->id]);
    $link->forceFill(['unlinked_at' => now(), 'unlink_reason' => 'SELF', 'unlinked_by' => $holder->id])->save();

    Refusal::assert(Refusal::CHECK, 'never changes (C-63)', fn () => $link->forceFill(['unlink_reason' => 'TOTP_DISABLED'])->save());

    // The table's owner is not bound by the runtime's column grants; the trigger holds against it too.
    $owner = DB::connection(DatabaseRole::MIGRATION_CONNECTION);
    $owner->beginTransaction();

    try {
        $userId = $owner->table('users')->insertGetId(['name' => 'Bayu Contoh', 'email' => 'bayu.pemilik@example.net', 'password' => 'tidak-dipakai']);
        $linkId = $owner->table('user_external_identities')->insertGetId(['user_id' => $userId, 'provider' => 'google', 'provider_subject' => '109999', 'linked_at' => now(), 'linked_by' => $userId]);

        Refusal::assert(Refusal::CHECK, 'Only the three unlink columns', fn () => $owner->table('user_external_identities')->where('id', $linkId)->update(['provider_subject' => '109998']), DatabaseRole::MIGRATION_CONNECTION);
    } finally {
        $owner->rollBack();
    }
});

it('keeps one active link per provider subject and per account, and relinks with a new row (C-64)', function () {
    $holder = User::factory()->create();
    $link = UserExternalIdentity::factory()->for($holder)->create(['linked_by' => $holder->id, 'provider_subject' => '108765']);
    $other = User::factory()->create();

    Refusal::assert(Refusal::UNIQUE, 'user_external_identities_c64_active_subject', fn () => UserExternalIdentity::factory()->for($other)->create(['linked_by' => $other->id, 'provider_subject' => '108765']));
    Refusal::assert(Refusal::UNIQUE, 'user_external_identities_c64_active_account', fn () => UserExternalIdentity::factory()->for($holder)->create(['linked_by' => $holder->id, 'provider_subject' => '108766']));

    $link->forceFill(['unlinked_at' => now(), 'unlink_reason' => 'SELF', 'unlinked_by' => $holder->id])->save();
    UserExternalIdentity::factory()->for($other)->create(['linked_by' => $other->id, 'provider_subject' => '108765']);

    expect(UserExternalIdentity::query()->where('provider_subject', '108765')->count())->toBe(2);
});

it('keeps one active grant per account and capability, and per account and company', function () {
    $user = User::factory()->create();
    $capability = Capability::factory()->create();
    $grant = UserCapabilityGrant::factory()->for($user)->for($capability)->create();

    Refusal::assert(Refusal::UNIQUE, 'user_capability_grants_active_unique', fn () => UserCapabilityGrant::factory()->for($user)->for($capability)->create());

    $grant->forceFill(['revoked_at' => now()])->save();
    UserCapabilityGrant::factory()->for($user)->for($capability)->create();

    $company = Company::factory()->create();
    $companyGrant = UserCompanyGrant::factory()->for($user)->for($company)->create();

    Refusal::assert(Refusal::UNIQUE, 'user_company_grants_active_unique', fn () => UserCompanyGrant::factory()->for($user)->for($company)->create());

    $companyGrant->forceFill(['revoked_at' => now()])->save();

    expect(UserCompanyGrant::factory()->for($user)->for($company)->create()->exists)->toBeTrue()
        ->and(UserCapabilityGrant::query()->where('user_id', $user->id)->count())->toBe(2);
});
