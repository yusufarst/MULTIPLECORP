<?php

use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\RouteCrawl;

// TL-06 (TASK-069; SECURITY_TEST_MATRIX SM-04 and §5 — every W4 row's capabilities by class, W4-01–W4-25 and W4-27's
// export; SM-02; AZ-01; AZ-11; RC-03; criterion 3): every route that declares a capability, requested with each of its
// methods by an Admin of the company holding every capability an Admin may hold except the ones the route declares, is
// refused at the server — forbidden, or not found at an Owner-only address (AZ-11) — and nothing but the refusal's
// security event is written; its positive twin, the same Admin holding those too, passes the declaration on every
// route an Admin may use; the Owner's twin at an Owner-only address is the route crawl's (TL-07).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
});

afterEach(fn () => @unlink($this->hotFile));

it('refuses every capability route to an Admin without its capability and admits the Admin holding it (SM-04)', function () {
    $company = Company::factory()->create()->id;
    $adminCodes = array_values(array_filter(CapabilityCatalogue::codes(), fn (string $code): bool => ! CapabilityCatalogue::isOwnerOnly($code)));
    $holding = Authorization::admin([$company], $adminCodes);
    $without = [];
    $results = [];
    $routes = array_filter(Route::getRoutes()->getRoutes(), fn ($route): bool => RouteCrawl::declared($route) !== []);

    foreach ($routes as $route) {
        $declared = RouteCrawl::declared($route);
        $key = implode(',', $declared);
        $without[$key] ??= Authorization::admin([$company], array_values(array_diff($adminCodes, $declared)));
        $results = [...$results, ...RouteCrawl::admit($this, $route, $without[$key], refused: true)];

        if (! CapabilityCatalogue::isOwnerOnly($declared[0])) {
            $results = [...$results, ...RouteCrawl::admit($this, $route, $holding, refused: false)];
        }
    }

    expect(count($routes))->toBeGreaterThan(180)
        ->and(array_values(array_filter($results, fn (array $result): bool => ! $result['expected'])))->toBe([]);
});
