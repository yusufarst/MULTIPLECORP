<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Authentication\GoogleSignIn;
use App\Modules\IAM\Authentication\OwnAccount;
use App\Modules\IAM\Authentication\Totp;
use App\Modules\IAM\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Vite;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-05 (TASK-023; M-39, M-41, M-42; HO-41; RV-12, RV-16; criteria 3, 6 and 7) on real PostgreSQL with database
// sessions: a code at PG-065's code-taking step passes once when another submission commits the same step under the
// row lock first; a link racing another account's link of the same subject is refused generically with the conflict
// event; a link after the account was deactivated is refused, no link surviving; the holder's own unlink, signed in
// for real, ends its other sessions and keeps the acting one.

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['session.driver' => 'database']);
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    $this->travelTo(now()->setTimestamp(1_800_000_015));
    $this->user = TwoFactor::enrol(User::factory()->create(['email' => 'sari@contoh.test', 'password' => 'Kata-Sandi-Uji-9']));
});

afterEach(fn () => @unlink($this->hotFile));

/** Signs the account in through the real password and TOTP steps, then confirms the password for the step-up. */
function signedInForReal(object $test, int $offset = 0): void
{
    $test->postJson('/masuk', ['email' => 'sari@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertOk();
    $test->postJson('/masuk/verifikasi', ['code' => TwoFactor::code(offset: $offset)]);
    $test->withSession([RequireStepUp::CONFIRMED_AT => now()->getTimestamp()]);
}

it('passes a code at a code-taking step once when another submission commits its step first (M-39; RV-12)', function () {
    $this->actingAs($this->user)->withSession([RequireStepUp::CONFIRMED_AT => now()->getTimestamp()]);
    $step = Totp::currentStep();

    DB::listen(function (QueryExecuted $query) use ($step) {
        static $raced = false;

        if (! $raced && str_contains($query->sql, 'from "users"') && str_contains($query->sql, 'for update')) {
            $raced = true;
            DB::table('users')->where('id', $this->user->id)->update(['two_factor_last_step' => $step]);
        }
    });

    $this->post('/akun/kode-pemulihan', ['code' => TwoFactor::code()])->assertSessionHasErrors('code');

    expect(session('inertia.flash_data')['recovery_codes'] ?? null)->toBeNull()
        ->and(DB::table('security_events')->where('event_kind', 'RECOVERY_CODES_VIEWED')->count())->toBe(0);
});

it('refuses a password change whose verified password changed before its transaction, applying neither twice (RV-10; criterion 2)', function () {
    // The request verified the password it holds; another change committed before its transaction took the row lock.
    $seen = User::query()->findOrFail($this->user->id);
    $request = Request::create('/akun/kata-sandi', 'PUT');
    $request->setLaravelSession(app('session')->driver());
    $request->setUserResolver(fn () => $seen);
    DB::table('users')->where('id', $seen->id)->update(['password' => Hash::make('Kata-Sandi-Lain-7')]);

    expect(app(OwnAccount::class)->changePassword($request, 'Pelangi-Biru#8492'))->toBeFalse()
        ->and(Hash::check('Kata-Sandi-Lain-7', (string) DB::table('users')->where('id', $seen->id)->value('password')))->toBeTrue()
        ->and(DB::table('audit_events')->where('action', 'IAM.PasswordChanged')->exists())->toBeFalse()
        ->and(DB::table('security_events')->where('event_kind', 'PASSWORD_CHANGED')->exists())->toBeFalse();
});

it('refuses a link generically when another account links the subject first, with the conflict event (M-41; BD-19; C-64)', function () {
    $other = TwoFactor::enrol(User::factory()->create());
    $this->actingAs($this->user)->withSession([RequireStepUp::CONFIRMED_AT => now()->getTimestamp(), GoogleSignIn::LINK => ['user' => $this->user->id, 'sub' => 'sub-rebutan', 'email' => 'sari@contoh.invalid', 'expires_at' => now()->getTimestamp() + 600]]);

    // The other account's link commits between this request's code and its link transaction.
    DB::listen(function (QueryExecuted $query) use ($other) {
        static $raced = false;

        if (! $raced && str_contains($query->sql, 'two_factor_last_step') && str_starts_with(strtolower($query->sql), 'update')) {
            $raced = true;
            DB::table('user_external_identities')->insert(['user_id' => $other->id, 'provider' => 'google', 'provider_subject' => 'sub-rebutan', 'email_at_link' => 'lain@contoh.invalid', 'linked_at' => now(), 'linked_by' => $other->id]);
        }
    });

    $this->post('/akun/google', ['code' => TwoFactor::code()])->assertRedirect('/akun');

    expect(session('inertia.flash_data')['answer']['text'] ?? null)->toBe(__('account.google.refused'))
        ->and(DB::table('user_external_identities')->where('provider_subject', 'sub-rebutan')->whereNull('unlinked_at')->pluck('user_id')->all())->toBe([$other->id])
        ->and(DB::table('security_events')->where('event_kind', 'GOOGLE_LINK_CONFLICT')->exists())->toBeTrue();
});

it('refuses a link when the device is replaced between the request\'s read and its transaction, no link made (AU-23; RV-16)', function () {
    // The request holds the account as it was; a replacement commits before the code's and the link's one transaction.
    $this->actingAs($this->user)->withSession([RequireStepUp::CONFIRMED_AT => now()->getTimestamp(), GoogleSignIn::LINK => ['user' => $this->user->id, 'sub' => 'sub-ganti', 'email' => null, 'expires_at' => now()->getTimestamp() + 600]]);
    DB::table('users')->where('id', $this->user->id)->update(['two_factor_confirmed_at' => now()->addSecond()]);

    $this->post('/akun/google', ['code' => TwoFactor::code()])->assertSessionHasErrors('code');

    expect(DB::table('user_external_identities')->where('provider_subject', 'sub-ganti')->exists())->toBeFalse();
});

it('refuses a link once the account is deactivated before its transaction, no link surviving (M-42; RV-05; RV-16)', function () {
    $this->actingAs($this->user)->withSession([RequireStepUp::CONFIRMED_AT => now()->getTimestamp(), GoogleSignIn::LINK => ['user' => $this->user->id, 'sub' => 'sub-sari', 'email' => 'sari@contoh.invalid', 'expires_at' => now()->getTimestamp() + 600]]);

    // The deactivation lands ahead of the code's and the link's one transaction, past the request's own checks.
    Event::listen(TransactionBeginning::class, function () {
        static $raced = false;

        if (! $raced) {
            $raced = true;
            DB::table('users')->where('id', $this->user->id)->update(['is_active' => false]);
        }
    });

    $this->post('/akun/google', ['code' => TwoFactor::code()]);

    expect(DB::table('user_external_identities')->where('user_id', $this->user->id)->whereNull('unlinked_at')->exists())->toBeFalse();
});

it('ends the holder\'s other sessions at its own unlink and keeps the acting one, signed in for real (HO-41; AU-24 SELF)', function () {
    DB::table('user_external_identities')->insert(['user_id' => $this->user->id, 'provider' => 'google', 'provider_subject' => 'sub-sari', 'email_at_link' => 'sari@contoh.invalid', 'linked_at' => now(), 'linked_by' => $this->user->id]);
    DB::table('sessions')->insert(['id' => str_repeat('b', 40), 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
    signedInForReal($this);

    $this->delete('/akun/google')->assertRedirect('/akun');

    expect(DB::table('sessions')->where('id', str_repeat('b', 40))->exists())->toBeFalse()
        ->and(DB::table('user_external_identities')->where('user_id', $this->user->id)->value('unlink_reason'))->toBe('SELF');
    $this->get('/akun')->assertOk();
});
