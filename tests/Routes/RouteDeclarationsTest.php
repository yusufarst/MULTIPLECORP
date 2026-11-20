<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Authentication\EnforceSessionLimits;
use App\Modules\IAM\Authentication\TwoFactorChallenge;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Tests\Support\Authorization;
use Tests\Support\CrawlWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\RouteCrawl;

// TL-07 (TASK-069; SECURITY_TEST_MATRIX SM-02; H8-02; EN-03; RG-04; AU-13; AU-18; criterion 6): the declared requirements
// behave as declared, beside the route audit's reading of them — a route declaring S0 answers the new Admin of RG-04 with
// nothing above S0: no record of the crawl's world in a page, its files and statuses as not found, its search empty —; a
// SELF route names no record, so it reaches the account's own row alone; an enrolment-only route answers no full session,
// and the enrolment-only session reaches nothing else: a page sends it to PG-004, anything else is forbidden.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
});

afterEach(fn () => @unlink($this->hotFile));

/** The routes declaring the access requirement given (DeclareAccess). */
function routesDeclaring(string $requirement): array
{
    return array_values(array_filter(Route::getRoutes()->getRoutes(), fn (RouteDefinition $route): bool => in_array("access:{$requirement}", array_map('strval', $route->gatherMiddleware()), true)));
}

/** One request with a fresh session, isolated in a savepoint. */
function declarationRequest(object $test, string $method, string $uri, ?object $account, array $session = [], array $body = []): TestResponse
{
    $test->flushSession();
    // The same guard throughout: a controller keeps the guard it was built with (Fortify's logout).
    Auth::guard()->forgetUser();

    if ($account !== null) {
        $test->actingAs($account)->withSession($session);
    }

    DB::beginTransaction();

    try {
        return $test->call($method, $uri, $body);
    } finally {
        DB::rollBack();
    }
}

it('names no record in a SELF route, so it reaches the account\'s own row alone (SM-02; AU-13)', function () {
    $self = routesDeclaring('self');

    expect($self)->not->toBeEmpty();

    foreach ($self as $route) {
        expect($route->parameterNames())->toBe([], "{$route->uri()} names a record");
    }
});

it('answers the new Admin of RG-04 on every S0 route with nothing above S0 (SM-02; RG-04)', function () {
    $world = CrawlWorld::build($this, Authorization::owner());
    $newAdmin = Authorization::admin();
    $above = array_filter($world->records, fn (string $record): bool => ! in_array($record, ['company', 'client', 'supplier', 'product'], true), ARRAY_FILTER_USE_KEY);
    $session = [RequireStepUp::CONFIRMED_AT => now()->getTimestamp()];
    $answers = [];

    expect($world->failures)->toBe([])->and(count($above))->toBeGreaterThan(8);

    foreach (routesDeclaring('s0') as $route) {
        if ($route->isFallback || ! in_array('GET', $route->methods(), true)) {
            continue;
        }

        $address = RouteCrawl::uri($route, $world->parameters($route->uri()));
        $response = declarationRequest($this, 'GET', $address, $newAdmin, $session);
        $content = (string) $response->getContent();
        // A record named by the address itself is the request's own, echoed as its address; any other is a leak.
        $leaked = array_keys(array_filter($above, fn (string $id): bool => ! str_contains($address, $id) && str_contains($content, $id)));
        $answers[$route->uri()] = [$response->getStatusCode(), $leaked];
    }

    foreach ($answers as $uri => [$status, $leaked]) {
        expect($leaked)->toBe([], "{$uri} answered a record above S0")
            ->and($status)->toBe(str_contains($uri, '{') ? 404 : 200, "{$uri} answered {$status}");
    }

    $number = (string) DB::table('projects')->where('public_id', $world->records['project'])->value('project_number');
    $search = declarationRequest($this, 'POST', '/cari', $newAdmin, $session, ['q' => $number]);

    expect($search->getStatusCode())->toBe(200)
        ->and((string) $search->getContent())->not->toContain($world->records['project'])->not->toContain($number);
});

it('answers no full session at an enrolment-only route (SM-02; AU-18)', function () {
    $owner = Authorization::owner();

    foreach (routesDeclaring('enrolment') as $route) {
        $status = declarationRequest($this, $route->methods()[0], '/'.$route->uri(), $owner, [RequireStepUp::CONFIRMED_AT => now()->getTimestamp()])->getStatusCode();

        expect($status)->toBeIn($route->methods()[0] === 'GET' ? [302] : [403], "{$route->uri()} answered a full session {$status}");
    }
});

it('reaches nothing but its own routes from the enrolment-only session: a page sends it to PG-004, anything else is forbidden (SM-02; AU-18)', function () {
    $owner = Authorization::owner();
    $session = [TwoFactorChallenge::ENROLMENT_ONLY => true, RequireStepUp::CONFIRMED_AT => now()->getTimestamp()];
    $answers = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $access = array_map('strval', $route->gatherMiddleware());

        if ($route->isFallback || in_array($route->getName(), EnforceSessionLimits::ENROLMENT_ROUTES, true) || array_filter($access, fn (string $entry): bool => str_starts_with($entry, 'access:none')) !== []) {
            continue;
        }

        $method = array_values(array_diff($route->methods(), ['HEAD']))[0];
        $response = declarationRequest($this, $method, RouteCrawl::uri($route), $owner, $session);
        $sent = $response->isRedirect() && str_ends_with((string) $response->headers->get('Location'), '/verifikasi-dua-langkah');
        $answers["{$method} {$route->uri()}"] = $method === 'GET' ? $sent : $response->getStatusCode() === 403;
    }

    expect(count($answers))->toBeGreaterThan(200)
        ->and(array_keys(array_filter($answers, fn (bool $kept): bool => ! $kept)))->toBe([]);
});
