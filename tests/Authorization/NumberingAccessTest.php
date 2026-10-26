<?php

use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-021; OD-03; PERMISSIONS_MATRIX §7.2 rows 10–12; criterion 5): PG-061 and both numbering commands are
// the Owner's alone (`companies.manage`, OWNER_ONLY): an Admin holding every other capability and the company's grant
// is answered by the not-found page, identical to an unknown address, and changes nothing; a guest goes to sign-in.

uses(RefreshDatabase::class);

// The not-found page renders through the shell's layout: a hot file stands in for the build, which this suite precedes.
beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
});

afterEach(function () {
    @unlink($this->hotFile);
});

it('declares companies.manage, OWNER_ONLY, on ROUTE-061, ROUTE-136 and ROUTE-137', function () {
    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'pengaturan/penomoran'));

    expect($routes)->toHaveCount(3)
        ->and($routes->every(fn ($route) => in_array('capability:companies.manage', $route->gatherMiddleware(), true)))->toBeTrue()
        ->and(CapabilityCatalogue::isOwnerOnly('companies.manage'))->toBeTrue();
});

it('answers an Admin with every other capability as not found, writing nothing, and sends a guest to sign-in (OD-03; IP-21)', function () {
    Authorization::catalogue();
    $company = Company::factory()->create();
    $others = array_values(array_filter(CapabilityCatalogue::codes(), fn (string $code): bool => ! CapabilityCatalogue::isOwnerOnly($code)));
    $admin = Authorization::admin([$company->id], $others);
    $payload = ['command_id' => (string) Str::uuid(), 'company' => $company->public_id, 'mode' => 'start', 'first_numbers' => ['2026' => 1], 'business_date' => '2026-10-26', 'business_date_prefilled' => true];

    $this->actingAs($admin)->get('/pengaturan/penomoran')->assertNotFound();
    $this->actingAs($admin)->put('/pengaturan/penomoran/doc-01', $payload)->assertNotFound();
    $this->actingAs($admin)->post('/pengaturan/penomoran/doc-01/mulai', $payload)->assertNotFound();
    expect(DB::table('numbering_schemes')->count() + DB::table('number_sequences')->count() + DB::table('command_log')->count())->toBe(0);

    auth()->logout();
    $this->get('/pengaturan/penomoran')->assertRedirect('/masuk');
});
