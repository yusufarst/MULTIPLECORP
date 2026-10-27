<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Models\User;
use App\Support\Localization\Locales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-08 (TASK-023; IG-01, IG-02, IG-05): INT-002 and INT-111–INT-118 on the server's side — present with their session
// state and absent without it, success, validation and each rejection their rows name, the step-up expiring on each
// guarded one, a code step's throttle closed when its store cannot be reached, the display language in force at once
// and again at the next sign-in, another account unaffected.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->admin = TwoFactor::enrol(Authorization::admin());
});

afterEach(fn () => @unlink($this->hotFile));

it('offers PG-065 to a signed-in account with its factor\'s state, and to no guest (INT-111–INT-117; IG-01)', function () {
    $this->actingAs($this->admin)->get('/akun')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('IAM/OwnAccount', false)->where('totp.active', true)->where('google.pending', false));

    $plain = Authorization::admin();
    $this->actingAs($plain)->get('/akun')->assertInertia(fn (AssertableInertia $page) => $page->where('totp.active', false));

    auth()->logout();
    $this->get('/akun')->assertRedirect('/masuk');
});

it('asks the step-up again on each guarded step once its 15 minutes pass, and admits it within them (INT-112–INT-117; IG-05)', function () {
    $this->actingAs($this->admin)->withSession([RequireStepUp::CONFIRMED_AT => time() - 899])->post('/akun/kode-pemulihan', ['code' => TwoFactor::code()])->assertRedirect('/akun');

    $this->travel(2)->seconds();
    $this->actingAs($this->admin)->postJson('/akun/kode-pemulihan/baru', ['code' => TwoFactor::code(offset: 1)])->assertStatus(423);
});

it('refuses a code step when its throttle\'s store cannot be reached — fail closed (INT-113; AU-05; H6-07)', function () {
    config(['cache.default' => 'redis', 'database.redis.cache.port' => 1, 'database.redis.default.port' => 1]);
    app('cache')->forgetDriver('redis');
    app('redis')->purge('cache');
    app('redis')->purge('default');

    $this->actingAs($this->admin)->withSession([RequireStepUp::CONFIRMED_AT => time()])->post('/akun/kode-pemulihan', ['code' => TwoFactor::code()])
        ->assertSessionHasErrors('code');

    expect(session('inertia.flash_data')['recovery_codes'] ?? null)->toBeNull();
});

it('applies the display language at once, keeps it for the next sign-in and leaves another account unaffected (INT-002; INT-118)', function () {
    $other = Authorization::admin();

    $this->actingAs($this->admin)->from('/akun')->put('/akun/bahasa', ['locale' => 'en']);
    $this->actingAs($this->admin)->get('/akun')->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'en'));

    // A new session reads the account's own language (AU-13; RB-10).
    $this->flushSession();
    $this->actingAs(User::query()->findOrFail($this->admin->id))->get('/akun')->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'en'));
    $this->actingAs($other)->get('/akun')->assertInertia(fn (AssertableInertia $page) => $page->where('locale', Locales::DEFAULT));

    $this->actingAs($this->admin)->from('/akun')->put('/akun/bahasa', ['locale' => 'xx'])->assertSessionHasErrors(['locale' => __('account.locale.unknown')]);
    expect(DB::table('users')->where('id', $this->admin->id)->value('locale'))->toBe('en');
});
