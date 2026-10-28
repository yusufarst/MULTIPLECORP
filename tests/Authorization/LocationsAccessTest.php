<?php

use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-06 and TL-14 (TASK-028; AZ-01; AZ-11; IG-01; SM-04; criterion 1): PG-057 to `stock.view` only, its one command to
// `warehouse.manage` only — positive and denial cases —, the control present with the capability and absent without.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Company::factory()->create();
});

afterEach(fn () => @unlink($this->hotFile));

it('denies the page without stock.view and shows it with it (criterion 1; AZ-11)', function () {
    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view']))->get('/data-master/lokasi-rak')->assertForbidden();
    $this->actingAs(Authorization::admin([$this->company->id], ['stock.view']))->get('/data-master/lokasi-rak')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.manage', false));
    $this->actingAs(Authorization::admin([$this->company->id], ['stock.view', 'warehouse.manage']))->get('/data-master/lokasi-rak')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.manage', true));
});

it('refuses the command without warehouse.manage and writes nothing (criterion 1; SM-04)', function () {
    $viewer = Authorization::admin([$this->company->id], ['stock.view']);

    $this->actingAs($viewer)->post('/data-master/lokasi-rak', ['command_id' => (string) Str::uuid(), 'kind' => 'create', 'code' => 'X-01', 'name' => 'X'])->assertForbidden();
    expect(DB::table('locations')->count())->toBe(0)->and(DB::table('command_log')->count())->toBe(0);

    $manager = Authorization::admin([$this->company->id], ['stock.view', 'warehouse.manage']);
    $this->actingAs($manager)->post('/data-master/lokasi-rak', ['command_id' => (string) Str::uuid(), 'kind' => 'create', 'code' => 'X-01', 'name' => 'X'])->assertRedirect();
    expect(DB::table('locations')->count())->toBe(1);
});

it('drops warehouse.manage without a company in scope, so a scope-less account commands nothing (CS-05)', function () {
    $scopeless = Authorization::admin([], ['stock.view', 'warehouse.manage']);

    $this->actingAs($scopeless)->post('/data-master/lokasi-rak', ['command_id' => (string) Str::uuid(), 'kind' => 'create', 'code' => 'Y-01', 'name' => 'Y'])->assertForbidden();
});
