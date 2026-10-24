<?php

use App\Modules\OPS\Models\SecurityEvent;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-011): denials on sample routes through the request pipeline of SECURITY EN-01 (AZ-01, AZ-11;
// RC-03, RC-05): a guest is unauthenticated, an account without the route's capability forbidden — an
// ACCESS_DENIED security event after the response —, and an account holding it admitted.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    Route::middleware(['web', 'auth', 'capability:finance.view'])->get('/__test/keuangan', fn () => 'keuangan')->name('test.finance');
});

it('refuses a guest before any capability is read', function () {
    $this->getJson('/__test/keuangan')->assertUnauthorized();

    expect(SecurityEvent::query()->count())->toBe(0);
});

it('forbids an account without the capability and records the denial (AZ-11; LG-02)', function () {
    $admin = Authorization::admin([$this->company->id], ['projects.view']);

    $this->actingAs($admin)->getJson('/__test/keuangan')->assertForbidden();

    $event = SecurityEvent::query()->sole();

    expect($event->event_kind)->toBe('ACCESS_DENIED')
        ->and($event->user_id)->toBe($admin->id)
        ->and($event->reason_code)->toBe('CAPABILITY')
        ->and($event->route_name)->toBe('test.finance')
        ->and($event->details)->toBe(['capability' => 'finance.view']);
});

it('admits an account holding the capability, and the Owner', function () {
    $admin = Authorization::admin([$this->company->id], ['finance.view']);

    $this->actingAs($admin)->get('/__test/keuangan')->assertOk()->assertSee('keuangan');
    $this->actingAs(Authorization::owner())->get('/__test/keuangan')->assertOk();
});

it('forbids an Admin whose only company grant is revoked: the capability needs a company (CS-05)', function () {
    $admin = Authorization::admin([$this->company->id], ['finance.view']);
    DB::table('user_company_grants')->where('user_id', $admin->id)->update(['revoked_at' => now()]);

    $this->actingAs($admin)->getJson('/__test/keuangan')->assertForbidden();
});
