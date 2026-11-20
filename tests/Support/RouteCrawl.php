<?php

namespace Tests\Support;

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Authentication\TwoFactorChallenge;
use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Modules\IAM\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The route crawl of TEST_STRATEGY §8 (TASK-069; SM-02; GAP-035; AICWDF §25): each registered route requested
 * with its own method as an actor its declaration admits — a guest for EN-03's unauthenticated routes, the
 * enrolment-only session for its route, the Owner, who holds every capability, with a password confirmed now, for
 * the rest —, its record from the crawl's world where the world has one. A page answers 200; a command answers an
 * empty submission at its fields, or as not found where its address names no record; a GET never reaches a
 * command; an unknown address answers the system page's not found.
 */
final class RouteCrawl
{
    private const COMMAND_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** What an empty submission may answer: the fields' messages, by redirect or as JSON (RC-05; IP-14). */
    private const AT_FIELDS = [302, 303, 422];

    /**
     * EN-03's unauthenticated pages, each with the answer its contract gives a guest with nothing pending: the
     * challenge without a pending attempt, the Google start and the callback without its state all answer on
     * PG-001 (ROUTE-002; ROUTE-102; ROUTE-103).
     *
     * @var array<string, list<int>>
     */
    private const GUEST_PAGES = [
        'login' => [200],
        'password.set.show' => [200],
        'health' => [200],
        'two-factor.challenge' => [302],
        'google.redirect' => [302],
        'google.callback' => [302],
    ];

    /**
     * Each request made for one route, with its answer and whether that answer is the expected one.
     *
     * @return list<array{route: string, check: string, method: string, uri: string, status: int, expected: bool, record: bool, error: ?string}>
     */
    public static function visit(TestCase $test, Route $route, User $owner, ?CrawlWorld $world = null): array
    {
        $methods = array_values(array_diff($route->methods(), ['HEAD']));
        $name = implode('|', $methods).' '.$route->uri();
        [$uri, $record] = self::address($route, $world?->parameters($route->uri()) ?? []);
        $actor = self::actor($route);
        $result = fn (string $check, string $method, string $address, TestResponse $response, bool $expected): array => [
            'route' => $name, 'check' => $check, 'method' => $method, 'uri' => $address, 'status' => $response->getStatusCode(), 'expected' => $expected, 'record' => $record,
            'error' => $expected || $response->exception === null ? null : $response->exception::class.': '.Str::limit($response->exception->getMessage(), 240)
                .' at '.str_replace(base_path().'/', '', $response->exception->getFile()).':'.$response->exception->getLine(),
        ];

        if ($route->isFallback) {
            $response = self::request($test, 'GET', '/alamat-yang-tidak-ada/'.Str::lower(Str::random(8)), $owner, 'owner');

            return [$result('system page', 'GET', '/alamat-yang-tidak-ada', $response, $response->getStatusCode() === 404)];
        }

        $commands = array_values(array_intersect($methods, self::COMMAND_METHODS));

        if (in_array('GET', $methods, true)) {
            $address = $uri.($world?->queries[(string) $route->getName()] ?? '');
            $response = self::request($test, 'GET', $address, $owner, $actor);

            // SM-08: no GET changes state — a route answering GET is never also a command.
            if ($commands !== []) {
                return [$result('refuses GET', 'GET', $address, $response, false)];
            }

            $expected = $actor === 'guest' ? (self::GUEST_PAGES[(string) $route->getName()] ?? [200]) : ($record || $route->parameterNames() === [] ? [200] : [404]);

            return [$result('page', 'GET', $address, $response, in_array($response->getStatusCode(), $expected, true))];
        }

        // A GET at a command's address never reaches the command: it reaches the page sharing the address — crawled
        // as that page —, or the system page, which answers not found and sends a guest's document request to sign-in.
        $response = self::request($test, 'GET', $uri, $owner, $actor);
        $page = self::pageAt($uri);
        $results = [$result('refuses GET', 'GET', $uri, $response, $page !== null
            ? array_intersect($page->methods(), self::COMMAND_METHODS) === [] && ! in_array($response->getStatusCode(), [405, 500], true)
            : $response->getStatusCode() === ($actor === 'guest' ? 302 : 404))];

        foreach ($commands as $method) {
            $response = self::request($test, $method, $uri, $owner, $actor, $world?->bodies[(string) $route->getName()] ?? []);
            $expected = $record || $route->parameterNames() === [] ? self::AT_FIELDS : [...self::AT_FIELDS, 404];
            $results[] = $result('answers', $method, $uri, $response, in_array($response->getStatusCode(), $expected, true));
        }

        return $results;
    }

    /**
     * The route's address with the parameters given, every other one a value its constraint accepts naming no record.
     *
     * @param  array<string, string>  $given
     */
    public static function uri(Route $route, array $given = []): string
    {
        return self::address($route, $given)[0];
    }

    /**
     * The capabilities a route declares, any one of which admits (RC-03); none for EN-03's declared requirements.
     *
     * @return list<string>
     */
    public static function declared(Route $route): array
    {
        foreach (array_map('strval', $route->gatherMiddleware()) as $middleware) {
            if (str_starts_with($middleware, 'capability:')) {
                return explode(',', substr($middleware, strlen('capability:')));
            }
        }

        return [];
    }

    /**
     * SM-04 at a capability route (AZ-01; AZ-11): requested with each of its methods by the account given, its
     * answer, and whether it is the expected one — refused, forbidden or as not found for an Owner-only address,
     * when $refused; past the declaration otherwise, neither forbidden nor failing.
     *
     * @return list<array{route: string, check: string, method: string, uri: string, status: int, expected: bool, record: bool, error: ?string}>
     */
    public static function admit(TestCase $test, Route $route, User $account, bool $refused): array
    {
        $methods = array_values(array_diff($route->methods(), ['HEAD']));
        [$uri] = self::address($route, []);
        $results = [];

        foreach ($methods as $method) {
            $test->flushSession();
            // The same guard throughout: a controller keeps the guard it was built with (Fortify's logout).
            Auth::guard()->forgetUser();
            $test->actingAs($account)->withSession([RequireStepUp::CONFIRMED_AT => now()->getTimestamp()]);
            // A refusal writes nothing but its security event: no command, no audit event (SECURITY_TEST_MATRIX §1).
            $written = fn (): array => [DB::table('command_log')->count(), DB::table('audit_events')->count()];
            [$response, $wrote] = self::isolated(function () use ($test, $method, $uri, $written): array {
                $before = $written();
                $response = $test->call($method, $uri);

                return [$response, $written() !== $before];
            });
            $status = $response->getStatusCode();
            $expected = $refused ? ! $wrote && $status === (CapabilityCatalogue::isOwnerOnly(self::declared($route)[0]) ? 404 : 403) : ! in_array($status, [403, 405, 500], true);
            $results[] = [
                'route' => implode('|', $methods).' '.$route->uri(), 'check' => $refused ? 'refused' : 'admitted', 'method' => $method, 'uri' => $uri, 'status' => $status, 'expected' => $expected, 'record' => false,
                'error' => $expected || $response->exception === null ? null : $response->exception::class.': '.Str::limit($response->exception->getMessage(), 240),
            ];
        }

        return $results;
    }

    /**
     * The crawl's counts, for the release integrity report's ROUTES section (RELEASE_PLAN §11).
     *
     * @param  list<array{route: string, check: string, method: string, uri: string, status: int, expected: bool, record: bool, error: ?string}>  $results
     */
    public static function write(array $results): void
    {
        $unexpected = array_values(array_filter($results, fn (array $result): bool => ! $result['expected']));
        $count = fn (int $status): int => count(array_filter($unexpected, fn (array $result): bool => $result['status'] === $status));
        $where = fn (callable $test): int => count(array_filter($results, $test));
        $directory = storage_path('logs/verify');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($directory.'/route-crawl.json', json_encode([
            'routes' => count(array_unique(array_filter(array_column($results, 'route'), fn (string $route): bool => $route !== 'world'))),
            'requests' => $where(fn (array $result): bool => $result['check'] !== 'world'),
            'pages_answering' => $where(fn (array $result): bool => $result['check'] === 'page' && $result['expected']),
            'pages_with_their_record' => $where(fn (array $result): bool => $result['check'] === 'page' && $result['expected'] && $result['record']),
            'commands_refusing_get' => $where(fn (array $result): bool => $result['check'] === 'refuses GET' && $result['expected']),
            'commands_answering_at_fields' => $where(fn (array $result): bool => $result['check'] === 'answers' && $result['expected'] && $result['status'] !== 404),
            'commands_with_their_record' => $where(fn (array $result): bool => $result['check'] === 'answers' && $result['expected'] && $result['record']),
            'commands_not_found_without_record' => $where(fn (array $result): bool => $result['check'] === 'answers' && $result['expected'] && $result['status'] === 404),
            'broken' => count(array_unique(array_column($unexpected, 'route'))),
            'unexpected_404' => $count(404),
            'unexpected_405' => $count(405),
            'unexpected_500' => $count(500),
            'unexpected' => $unexpected,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * The route's address, its parameters from the world where it gives them, and whether it gave every one.
     *
     * @param  array<string, string>  $given
     * @return array{0: string, 1: bool}
     */
    private static function address(Route $route, array $given): array
    {
        $uri = '/'.$route->uri();
        $record = $route->parameterNames() !== [];

        foreach ($route->parameterNames() as $parameter) {
            $record = $record && isset($given[$parameter]);
            $uri = str_replace('{'.$parameter.'}', $given[$parameter] ?? self::generated($route->wheres[$parameter] ?? null), $uri);
        }

        return [$uri, $record];
    }

    /** A value its constraint accepts, naming no record. */
    private static function generated(?string $where): string
    {
        return match (true) {
            $where === null, str_contains($where, '{36}'), str_contains($where, '{8}-') => (string) Str::uuid(),
            str_contains($where, '{32') => bin2hex(random_bytes(16)),
            preg_match('/^\(?([a-z][a-z-]*)(\|[a-z][a-z-]*)*\)?$/', $where, $first) === 1 => $first[1],
            default => 'x',
        };
    }

    /** guest, enrolment or owner — the actor the route's declaration admits. */
    private static function actor(Route $route): string
    {
        foreach (array_map('strval', $route->gatherMiddleware()) as $middleware) {
            if (str_starts_with($middleware, 'access:none')) {
                return 'guest';
            }

            if ($middleware === 'access:enrolment') {
                return 'enrolment';
            }
        }

        return 'owner';
    }

    /** The route a GET at this address reaches, unless only the fallback does. */
    private static function pageAt(string $uri): ?Route
    {
        try {
            $route = Router::getRoutes()->match(Request::create($uri, 'GET'));

            return $route->isFallback ? null : $route;
        } catch (HttpException) {
            return null;
        }
    }

    /**
     * One request with a fresh session: no account for a guest, the enrolment-only session for its route, the
     * Owner with a password confirmed now for the rest (AU-15).
     *
     * @param  array<string, string>  $body
     */
    private static function request(TestCase $test, string $method, string $uri, User $owner, string $actor, array $body = []): TestResponse
    {
        $test->flushSession();
        // The same guard throughout: a controller keeps the guard it was built with (Fortify's logout).
        Auth::guard()->forgetUser();

        if ($actor !== 'guest') {
            $test->actingAs($owner)->withSession($actor === 'enrolment' ? [TwoFactorChallenge::ENROLMENT_ONLY => true] : [RequireStepUp::CONFIRMED_AT => now()->getTimestamp()]);
        }

        return self::isolated(fn (): TestResponse => $test->call($method, $uri, $body));
    }

    /**
     * A request inside its own savepoint, rolled back after it: each route meets the same world, and a request that
     * fails in the database leaves the test's transaction usable for the next.
     *
     * @template T
     *
     * @param  callable(): T  $request
     * @return T
     */
    private static function isolated(callable $request): mixed
    {
        DB::beginTransaction();

        try {
            return $request();
        } finally {
            DB::rollBack();
        }
    }
}
