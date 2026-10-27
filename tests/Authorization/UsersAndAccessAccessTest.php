<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-06 (TASK-024; OD-01; OD-02; RG-02; RG-05; RG-07; SM-04; criterion 1, 2): PG-060 and every command of it are the
// Owner's alone — `users.manage`, or `permissions.manage` for the grants, both OWNER_ONLY —: an Admin holding every
// other capability, its step-up confirmed, is answered by the not-found page on every route, identical to an unknown
// address, and changes nothing; nobody grants to itself, an OWNER_ONLY capability never reaches an Admin, and a
// capability granted to an Admin of companies A and B applies to both (RG-07; GAP-032).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->a = Company::factory()->create();
    $this->b = Company::factory()->create();
});

afterEach(fn () => @unlink($this->hotFile));

it('declares users.manage — permissions.manage for the grants —, both OWNER_ONLY, on every route; no ROUTE-129', function () {
    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'pengaturan/pengguna'));
    $declared = $routes->mapWithKeys(fn ($route) => [implode('|', $route->methods()).' '.$route->uri() => array_values(array_filter($route->gatherMiddleware(), fn (string $entry): bool => str_starts_with($entry, 'capability:')))]);

    expect($routes)->toHaveCount(10)
        ->and($declared['PUT pengaturan/pengguna/{id}/akses'])->toBe(['capability:permissions.manage'])
        ->and($declared->except('PUT pengaturan/pengguna/{id}/akses')->unique()->values()->all())->toBe([['capability:users.manage']])
        ->and(CapabilityCatalogue::isOwnerOnly('users.manage') && CapabilityCatalogue::isOwnerOnly('permissions.manage'))->toBeTrue()
        ->and($routes->filter(fn ($route) => str_contains($route->uri(), 'kunci') || str_contains($route->uri(), 'insiden')))->toHaveCount(0);
});

it('answers an Admin with every other capability as not found on every route, writing nothing (OD-01; OD-02; IP-21)', function () {
    $others = array_values(array_filter(CapabilityCatalogue::codes(), fn (string $code): bool => ! CapabilityCatalogue::isOwnerOnly($code)));
    $admin = TwoFactor::enrol(Authorization::admin([$this->a->id, $this->b->id], $others));
    $target = Authorization::admin([$this->a->id]);
    $id = $target->public_id;
    $before = DB::table('audit_events')->count();

    $requests = [
        ['GET', '/pengaturan/pengguna'], ['GET', "/pengaturan/pengguna?akun={$id}"],
        ['POST', '/pengaturan/pengguna'], ['PUT', "/pengaturan/pengguna/{$id}"], ['POST', "/pengaturan/pengguna/{$id}/status"],
        ['PUT', "/pengaturan/pengguna/{$id}/akses"], ['POST', "/pengaturan/pengguna/{$id}/tautan"], ['POST', "/pengaturan/pengguna/{$id}/tautan/cabut"],
        ['POST', "/pengaturan/pengguna/{$id}/reset-verifikasi"], ['POST', "/pengaturan/pengguna/{$id}/lepas-google"],
        ['POST', "/pengaturan/pengguna/{$id}/sesi/".str_repeat('a', 32).'/akhiri'],
    ];

    foreach ($requests as [$method, $path]) {
        $this->actingAs($admin)->withSession([RequireStepUp::CONFIRMED_AT => time()])
            ->call($method, $path, ['command_id' => (string) Str::uuid(), 'name' => 'X', 'email' => 'x@contoh.test', 'active' => false, 'reason' => 'x', 'purpose' => 'RESET'])
            ->assertNotFound();
    }

    expect(DB::table('command_log')->count())->toBe(0)
        ->and(DB::table('audit_events')->count())->toBe($before)
        ->and(DB::table('users')->where('email', 'x@contoh.test')->exists())->toBeFalse();

    auth()->logout();
    $this->get('/pengaturan/pengguna')->assertRedirect('/masuk');
});

it('refuses the Owner\'s grant to itself and an OWNER_ONLY capability for an Admin (OD-02; RG-02; SM-04)', function () {
    $owner = TwoFactor::enrol(Authorization::owner());
    $target = Authorization::admin([$this->a->id]);

    $this->actingAs($owner)->withSession([RequireStepUp::CONFIRMED_AT => time()])
        ->put("/pengaturan/pengguna/{$owner->public_id}/akses", ['command_id' => (string) Str::uuid(), 'role' => 'ADMIN_OPERASIONAL', 'companies' => [], 'capabilities' => [], 'lock_version' => 0]);
    expect(session('inertia.flash_data')['answer']['kind'] ?? null)->toBe('REJECTED');

    foreach (array_filter(CapabilityCatalogue::codes(), fn (string $code): bool => CapabilityCatalogue::isOwnerOnly($code)) as $code) {
        $this->actingAs($owner)->withSession([RequireStepUp::CONFIRMED_AT => time()])
            ->put("/pengaturan/pengguna/{$target->public_id}/akses", ['command_id' => (string) Str::uuid(), 'role' => 'ADMIN_OPERASIONAL', 'companies' => [$this->a->public_id], 'capabilities' => [$code], 'lock_version' => 0])
            ->assertSessionHasErrors('capabilities.0');
    }

    expect(DB::table('user_capability_grants')->join('capabilities', 'capabilities.id', '=', 'capability_id')->where('authority_class', 'OWNER_ONLY')->exists())->toBeFalse();
});

it('applies a capability granted to an Admin of companies A and B in both, for each class (RG-07; GAP-032; SM-04)', function (string $capability, array $with) {
    $owner = TwoFactor::enrol(Authorization::owner());
    $target = Authorization::admin();

    $this->actingAs($owner)->withSession([RequireStepUp::CONFIRMED_AT => time()])
        ->put("/pengaturan/pengguna/{$target->public_id}/akses", ['command_id' => (string) Str::uuid(), 'role' => 'ADMIN_OPERASIONAL', 'companies' => [$this->a->public_id, $this->b->public_id], 'capabilities' => [...$with, $capability], 'lock_version' => 0, 'reason' => 'Uji kelas']);

    expect(session('inertia.flash_data')['answer']['kind'] ?? null)->toBe('COMMITTED');
    app(EffectiveCapabilities::class)->forget();
    $authority = app(EffectiveCapabilities::class)->for($target->id);

    expect($authority->allows($capability, $this->a->id))->toBeTrue()
        ->and($authority->allows($capability, $this->b->id))->toBeTrue()
        ->and($authority->allows($capability, Company::factory()->create()->id))->toBeFalse();
})->with([
    'ADM' => ['projects.manage', ['projects.view']],
    'ADM_PLUS' => ['stock.allocate_intercompany', ['stock.view']],
]);
