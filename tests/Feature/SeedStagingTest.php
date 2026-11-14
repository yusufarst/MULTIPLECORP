<?php

use App\Console\Commands\SeedStaging;
use App\Modules\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\FixtureSets;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-072; PRQ-02; TEST_STRATEGY FX-02; DIR-079 CB-04): the staging seed makes FX-02's group and base personas through
// the product's commands as the installed Owner, on staging only, once — a second run leaves everything as it is — and shows
// each new account's password-setting link once.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
});

it('refuses to run anywhere but staging', function () {
    $owner = Authorization::owner();

    $this->artisan('staging:seed', ['--owner' => $owner->email])
        ->expectsOutputToContain('runs on staging only')
        ->assertExitCode(1);
    expect(DB::table('companies')->whereIn('code', array_keys(SeedStaging::COMPANIES))->count())->toBe(0);
});

it('refuses when only the console\'s --env names staging, the stack\'s APP_ENV another', function () {
    $owner = Authorization::owner();
    // What `php artisan staging:seed --env=staging` does on production: the framework's environment, not APP_ENV.
    $this->app['env'] = 'staging';

    $this->artisan('staging:seed', ['--owner' => $owner->email])
        ->expectsOutputToContain('runs on staging only')
        ->assertExitCode(1);
    expect(DB::table('companies')->whereIn('code', array_keys(SeedStaging::COMPANIES))->count())->toBe(0);
});

it('makes FX-02\'s companies and base personas on staging, once, each with its link', function () {
    $this->app['env'] = 'staging';
    config(['app.env' => 'staging']);
    $owner = Authorization::owner();

    $this->artisan('staging:seed', ['--owner' => $owner->email])
        ->expectsOutputToContain('created company ARJ')
        ->expectsOutputToContain('created account admin-ab — set its password once at')
        ->assertExitCode(0);

    expect(DB::table('companies')->whereIn('code', ['ARJ', 'BTN', 'CKP'])->count())->toBe(3);
    $adminAb = User::query()->where('email', 'admin-ab@example.com')->sole();
    expect(DB::table('user_company_grants')->where('user_id', $adminAb->id)->whereNull('revoked_at')->count())->toBe(2)
        ->and(DB::table('user_capability_grants')->where('user_id', $adminAb->id)->whereNull('revoked_at')->count())->toBeGreaterThan(0)
        ->and(DB::table('user_company_grants')->where('user_id', User::query()->where('email', 'admin-new@example.com')->sole()->id)->count())->toBe(0);

    $this->artisan('staging:seed', ['--owner' => $owner->email])
        ->expectsOutputToContain('exists  company ARJ')
        ->expectsOutputToContain('exists  account admin-ab')
        ->assertExitCode(0);
    expect(DB::table('companies')->whereIn('code', ['ARJ', 'BTN', 'CKP'])->count())->toBe(3);
});

it('holds FX-02\'s companies and base personas as the fixture set states them', function () {
    $set = FixtureSets::load('FX-02')['entries'];

    foreach (SeedStaging::COMPANIES as $code => $name) {
        expect($set['companies'][$code]['name'] ?? null)->toBe($name);
    }

    foreach (SeedStaging::ACCOUNTS as $persona => $account) {
        expect($set['accounts'][$persona]['email'] ?? null)->toBe($account['email'])
            ->and($set['accounts'][$persona]['role'] ?? null)->toBe($account['owner'] ? 'OWNER' : 'ADMIN_OPERASIONAL')
            ->and($set['accounts'][$persona]['companies'] ?? null)->toBe($account['companies']);
    }
});
