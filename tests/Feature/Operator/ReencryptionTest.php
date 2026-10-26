<?php

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Recovery\RecoveryRefused;
use App\Modules\IAM\Recovery\Reencryption;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Support\OwnerTrail;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-04 and TL-14 (TASK-020; SECURITY AU-21; RV-17; SX-02; SM-21): the re-encryption command, after a rotation
// through the previous-keys list, writes every TOTP secret and recovery-code set again under the current key, one
// audited transaction per account, and verifies the count against the enrolled accounts before the old key may
// be retired. Synthetic keys only, generated here.

uses(RefreshDatabase::class);

function useKeys(string $current, array $previous = []): void
{
    config(['app.key' => 'base64:'.base64_encode($current), 'app.previous_keys' => array_map(fn (string $key) => 'base64:'.base64_encode($key), $previous)]);
    app()->forgetInstance('encrypter');
    Crypt::clearResolvedInstance('encrypter');
}

beforeEach(function () {
    $this->key = config('app.key');
    $this->old = Encrypter::generateKey('aes-256-cbc');
    $this->new = Encrypter::generateKey('aes-256-cbc');
    useKeys($this->old);
    $this->enrolled = [TwoFactor::enrol(User::factory()->create()), TwoFactor::enrol(User::factory()->create())];
    User::factory()->create();
});

afterEach(function () {
    config(['app.key' => $this->key, 'app.previous_keys' => []]);
    app()->forgetInstance('encrypter');
    Crypt::clearResolvedInstance('encrypter');
});

it('re-encrypts every enrolled account under the current key and verifies the count (AU-21; RV-17)', function () {
    useKeys($this->new, [$this->old]);

    $result = app(Reencryption::class)->run(OwnerTrail::operator());

    $current = new Encrypter($this->new, 'aes-256-cbc');
    foreach ($this->enrolled as $user) {
        $row = DB::table('users')->where('id', $user->id)->first();
        expect($current->decryptString((string) $row?->two_factor_secret))->toBe(TwoFactor::SECRET)
            ->and(json_decode($current->decryptString((string) $row?->two_factor_recovery_codes), true))->toBe(TwoFactor::CODES);
    }

    expect($result)->toBe(['reencrypted' => 2, 'enrolled' => 2, 'verified' => true])
        ->and(DB::table('audit_events')->where('action', 'IAM.TotpSecretsReencrypted')->whereNull('actor_user_id')->count())->toBe(2)
        ->and(DB::table('security_events')->where('event_kind', 'TOTP_SECRETS_REENCRYPTED')->value('reason_code'))->toBe('COUNT_VERIFIED');
});

it('stops without printing a secret when a ciphertext opens under no listed key (AU-21)', function () {
    useKeys($this->new);

    expect(fn () => app(Reencryption::class)->run(OwnerTrail::operator()))->toThrow(RecoveryRefused::class, 'APP_PREVIOUS_KEYS');

    $this->artisan('iam:reencrypt-secrets', ['--operator' => OwnerTrail::OPERATOR])
        ->doesntExpectOutputToContain(TwoFactor::SECRET)
        ->assertExitCode(1);
});
