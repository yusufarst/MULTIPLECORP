<?php

use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-06 and TL-14 (TASK-031; PERMISSIONS_MATRIX §4, PJ-16; AZ-11; SM-04; criteria 1, 8): PG-040 by `projects.view`; the
// closure by `cases.close` with `projects.view` of the case's company — positive and denial cases —, "Tutup Kasus"
// present with it and absent without; another company's case unknown; no standalone capability opens a case.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->case = CorrectionCase::factory()->create(['company_id' => $this->company->id]);
});

afterEach(fn () => @unlink($this->hotFile));

it('shows PG-040 with projects.view only, the closure control with cases.close only (ROUTE-040; criterion 8)', function () {
    $this->actingAs(Authorization::admin([$this->company->id], ['finance.view']))->get('/dokumen/kasus')->assertForbidden();
    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view']))->get('/dokumen/kasus')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.close', false)->has('rows', 1));
    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view', 'cases.close']))->get('/dokumen/kasus')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.close', true));
    // Another company's case does not exist for the viewer.
    $this->actingAs(Authorization::admin([Company::factory()->create()->id], ['projects.view']))->get('/dokumen/kasus')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0));
});

it('closes only with cases.close and projects.view of the case\'s company, writing nothing otherwise (ROUTE-271; SM-04; AZ-11)', function () {
    $body = fn (): array => ['command_id' => (string) Str::uuid(), 'lock_version' => 0, 'alasan' => 'Kasus selesai ditinjau.'];

    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view']))->post("/dokumen/kasus/{$this->case->public_id}/tutup", $body())->assertForbidden();
    $this->actingAs(Authorization::admin([Company::factory()->create()->id], ['projects.view', 'cases.close']))->post("/dokumen/kasus/{$this->case->public_id}/tutup", $body())->assertNotFound();
    expect($this->case->fresh()?->state)->toBe('OPEN')->and(DB::table('command_log')->count())->toBe(0);

    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view', 'cases.close']))->post("/dokumen/kasus/{$this->case->public_id}/tutup", $body())->assertRedirect();
    expect($this->case->fresh()?->state)->toBe('CLOSED');
});

it('declares no capability that opens a case on its own (PERMISSIONS_MATRIX §4; criterion 1)', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route): bool => str_starts_with($route->uri(), 'dokumen/kasus'));

    expect($routes->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())->values()->all())
        ->toBe(['GET|HEAD dokumen/kasus', 'POST dokumen/kasus/{id}/tutup']);
});
