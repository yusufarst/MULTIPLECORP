<?php

use App\Modules\IAM\Authentication\Landing;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\OPS\Data\AuditCodes;
use App\Modules\Reporting\Queries\HomeGroups;
use App\Modules\Reporting\Signals\QS01UnreservedDemand;
use App\Modules\Reporting\Signals\QS02Shortage;
use App\Modules\Reporting\Signals\QS23OverdueProjects;
use App\Modules\Reporting\Signals\QS24DispatchReady;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReservationWorld;

// TL-04, TL-06 (TASK-058; ROUTE-006–ROUTE-008; IA §9; PJ-13; OD-14; CS-07; CS-08; RG-04): the home pages per persona and
// the line signals on real facts — each signal one bounded statement read with the scope first, a company's count
// bounded and never summed, a signal absent without its read class.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->hot = (string) tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hot, 'http://localhost:5173');
    Vite::useHotFile($this->hot);
});

afterEach(function () {
    @unlink($this->hot);
});

function homeContext(int $userId, ?string $view = null): App\Modules\Reporting\Signals\Context
{
    return app(HomeGroups::class)->context(app(EffectiveCapabilities::class)->for($userId), $view);
}

it('lands the Owner on Beranda and every other account on Pekerjaan (ROUTE-104; NAV-006; NAV-007)', function () {
    $owner = User::query()->findOrFail(Authorization::owner()->id);
    $admin = User::query()->findOrFail(Authorization::admin([], ['stock.view'])->id);

    expect(Landing::of($owner))->toBe('/beranda')->and(Landing::of($admin))->toBe('/pekerjaan')->and(Landing::of(null))->toBe('/pekerjaan');
});

it('answers an Admin\'s Beranda and Tinjauan as the not-found page and shows the Owner both (OD-14; AZ-11; UXS-25)', function () {
    $world = ReservationWorld::make();
    $admin = User::query()->findOrFail($world->actorId);
    $owner = User::query()->findOrFail(Authorization::owner()->id);

    $this->actingAs($admin)->get('/beranda')->assertNotFound();
    $this->actingAs($admin)->get('/tinjauan')->assertNotFound();
    $this->actingAs($owner)->get('/beranda')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Reporting/Beranda')->has('frame.views'));
    $this->actingAs($owner)->get('/tinjauan')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('OPS/Tinjauan')->where('tab', 'aktivitas')->has('items'));
});

it('gives Pekerjaan the groups of the account\'s read classes, billing only with finance.view, and MSG-38 without a grant (PJ-13; RG-04)', function () {
    $world = ReservationWorld::make();
    $stockOnly = User::query()->findOrFail(Authorization::admin([$world->company->id], ['stock.view'])->id);
    $ungranted = User::query()->findOrFail(Authorization::admin([], ['stock.view'])->id);

    $this->actingAs(User::query()->findOrFail($world->actorId))->get('/pekerjaan')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Reporting/Pekerjaan')->where('frame.ungranted', false)->where('frame.groups.billing', true));
    $this->actingAs($stockOnly)->get('/pekerjaan')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('frame.groups.billing', false)->where('frame.groups.goods', true));
    $this->actingAs($ungranted)->get('/pekerjaan')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('frame.ungranted', true));
});

it('reads confirmed demand not yet reserved, its shortage and, once reserved, the dispatch-ready line (QS-01; QS-02; QS-24)', function () {
    $world = ReservationWorld::make();
    $project = $world->project(['WAREHOUSE'], '10');
    $line = ReservationWorld::line($project);
    $context = homeContext($world->actorId);

    $demand = app(QS01UnreservedDemand::class)->read($context);
    $shortage = app(QS02Shortage::class)->read($context);
    expect($demand['rows'][0]['value'] ?? null)->toBe('10')->and($demand['rows'][0]['ref'] ?? null)->toBe($project->project_number)
        ->and($demand['counts'])->toBe([['company' => $world->company->code, 'count' => '1']])->and($demand['rows'][0]['href'] ?? null)->toBe('/gudang/reservasi')
        ->and($shortage['rows'][0]['value'] ?? null)->toBe('10')->and(app(QS24DispatchReady::class)->read($context)['rows'] ?? null)->toBe([]);

    $world->stock('20');
    expect(ReservationWorld::reserve($world->actorId, $line, '10'))->toBe('COMMITTED');
    $context = homeContext($world->actorId);

    expect(app(QS01UnreservedDemand::class)->read($context)['rows'] ?? null)->toBe([])
        ->and(app(QS24DispatchReady::class)->read($context)['rows'][0]['value'] ?? null)->toBe('10');
});

it('reads no row of a company outside the scope, and hides the project part without projects.view (CS-07; PF-24; RD-03)', function () {
    $mine = ReservationWorld::make();
    $theirs = ReservationWorld::make($mine);
    $theirs->project(['WAREHOUSE'], '7');
    $mine->project(['WAREHOUSE'], '3');
    $stockOnly = Authorization::admin([$mine->company->id], ['stock.view'])->id;

    $read = app(QS01UnreservedDemand::class)->read(homeContext($mine->actorId));
    $masked = app(QS01UnreservedDemand::class)->read(homeContext($stockOnly));

    expect(array_column($read['rows'] ?? [], 'value'))->toBe(['3'])->and(array_column($read['counts'] ?? [], 'company'))->toBe([$mine->company->code])
        ->and($masked['rows'][0] ?? [])->toMatchArray(['value' => '3', 'ref' => null, 'href' => null]);
});

it('keeps QS-23 the Owner\'s alone and lists an ACTIVE project past its deadline (DIR-077 CA-10; PJ-13)', function () {
    $world = ReservationWorld::make();
    $project = $world->project(['WAREHOUSE'], '1');
    DB::table('projects')->where('id', $project->id)->update(['deadline' => now()->subDays(3)->toDateString()]);
    $owner = Authorization::owner()->id;

    expect(app(QS23OverdueProjects::class)->read(homeContext($world->actorId)))->toBeNull()
        ->and(app(QS23OverdueProjects::class)->read(homeContext($owner))['rows'][0]['subject'] ?? null)->toBe($project->project_number);
});

it('builds IX-13\'s partial indexes over the very codes the review signals read (DIR-088; PF-10)', function () {
    $predicate = fn (string $index): string => (string) DB::table('pg_indexes')->where('indexname', $index)->value('indexdef');
    $listed = fn (array $codes): string => implode(', ', array_map(fn (string $code): string => "'{$code}'", $codes));
    $routed = AuditCodes::ROUTED;
    sort($routed);

    expect(AuditCodes::ACKNOWLEDGED_SQL)->toBe($listed(AuditCodes::acknowledged()))->and(AuditCodes::ROUTED_SQL)->toBe($listed($routed))
        ->and(count(AuditCodes::acknowledged()))->toBe(18)->and(count(AuditCodes::ROUTED))->toBe(5);

    foreach ([...AuditCodes::acknowledged()] as $code) {
        expect($predicate('audit_events_ix13_acknowledged'))->toContain("'{$code}'");
    }

    foreach (AuditCodes::ROUTED as $code) {
        expect($predicate('audit_events_ix13_routed'))->toContain("'{$code}'")->and($predicate('audit_events_ix13_routed_command'))->toContain("'{$code}'");
    }
});
