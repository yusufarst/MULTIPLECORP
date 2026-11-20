<?php

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\CrawlWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\RouteCrawl;

// TL-07 (TASK-069; TEST_STRATEGY §8; SM-02; SM-08; GAP-035; AICWDF §25; criteria 6 and 8): the crawl of every
// registered route as an actor allowed to use it — a page answers 200 with its record, a command refuses GET and
// answers an empty submission at its fields, an unknown address answers the system page, and nothing answers an
// unexpected 404, 405 or 500. Its counts are written for the release integrity report's ROUTES section
// (RELEASE_PLAN §11) in storage/logs/verify/route-crawl.json.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
});

afterEach(fn () => @unlink($this->hotFile));

it('crawls every registered route with no unexpected 404, 405 or 500 (TEST_STRATEGY §8)', function () {
    $owner = Authorization::owner();
    $world = CrawlWorld::build($this, $owner);
    $results = array_map(fn (string $failure): array => ['route' => 'world', 'check' => 'world', 'method' => '', 'uri' => '', 'status' => 0, 'expected' => false, 'record' => false, 'error' => $failure], $world->failures);

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $results = [...$results, ...RouteCrawl::visit($this, $route, $owner, $world)];
    }

    RouteCrawl::write($results);

    expect(count(array_unique(array_column($results, 'route'))) - ($world->failures === [] ? 0 : 1))->toBe(count(Route::getRoutes()->getRoutes()))
        ->and(array_values(array_filter($results, fn (array $result): bool => ! $result['expected'])))->toBe([]);
});

it('answers a command to an unknown address, or naming a malformed record, as the system page\'s not found; a registered route keeps its 405 (AZ-11; IP-21; TASK-069 F-02)', function () {
    $owner = Authorization::owner();

    $this->actingAs($owner)->post('/alamat-yang-tidak-ada')->assertNotFound();
    $this->actingAs($owner)->post('/keuangan/pembayaran/bukan-pengenal/terapkan', ['command_id' => '00000000-0000-7000-8000-000000000000'])->assertNotFound();
    $this->actingAs($owner)->put('/cari')->assertStatus(405);
    $this->actingAs($owner)->post('/aktivitas/riwayat')->assertStatus(405);
});

it('counts a page answering 500 and a route answering GET as a command as unexpected', function () {
    Route::get('__crawl/rusak', fn () => abort(500))->middleware(['web', 'auth', 'access:s0']);
    Route::match(['GET', 'POST'], '__crawl/perintah', fn () => 'dijalankan')->middleware(['web', 'auth', 'access:s0']);
    $owner = Authorization::owner();
    $routes = array_values(array_filter(Route::getRoutes()->getRoutes(), fn (RouteDefinition $route): bool => str_starts_with($route->uri(), '__crawl/')));

    $results = array_merge(...array_map(fn (RouteDefinition $route): array => RouteCrawl::visit($this, $route, $owner), $routes));

    expect(collect($results)->where('expected', false)->pluck('check')->all())->toEqualCanonicalizing(['page', 'refuses GET']);
});
