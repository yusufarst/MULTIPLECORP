<?php

namespace Tests\Support;

use Illuminate\Routing\Route;

/**
 * ROUTE_CONTRACTS read as a register (TASK-069; TEST_STRATEGY §8; GAP-035): each ROUTE- row's method and the
 * paths of its *Method · route* cell, held one to one against the registered routes. A row with no path answers
 * the address no route resolves — the fallback (ROUTE-005) —, and a row registered on staging only is absent from
 * every other environment, as its contract states.
 */
final class RouteContracts
{
    private const DOCUMENT = 'docs/03-workflows/ROUTE_CONTRACTS.md';

    /**
     * The differences the record holds open, each by its row and the path registered in its place, with the
     * finding that sends it to its owner (TASK-069's security record, F-01). A difference not listed here
     * fails, and so does a listed one that is gone.
     */
    public const OPEN = [
    ];

    /**
     * @return list<array{id: string, method: string, paths: list<string>, staging: bool}>
     */
    public static function rows(): array
    {
        $rows = [];

        foreach (explode("\n", (string) file_get_contents(base_path(self::DOCUMENT))) as $line) {
            if (preg_match('/^\| (ROUTE-\d{3}) \|/', $line, $id) !== 1) {
                continue;
            }

            $cells = array_map('trim', explode('|', $line));
            preg_match('/^(GET|POST|PUT|PATCH|DELETE)\b/', $cells[3], $method);
            preg_match_all('/`(\/[^`]*)`/', $cells[3], $paths);

            $rows[] = [
                'id' => $id[1],
                'method' => $method[1] ?? '',
                'paths' => array_map(self::normalized(...), $paths[1]),
                'staging' => str_contains($line, 'registered on staging only'),
            ];
        }

        return $rows;
    }

    /**
     * "METHOD path" with each parameter written `{}`: a contract's `{id}` and a route's `{project}` alike.
     */
    public static function key(string $method, string $path): string
    {
        return $method.' '.self::normalized($path);
    }

    /**
     * The key of a registered route, or `GET *` for the fallback.
     */
    public static function routeKey(Route $route): string
    {
        return $route->isFallback ? 'GET *' : self::key($route->methods()[0], $route->uri());
    }

    /**
     * Each difference between the register and the registered routes, beside the open ones.
     *
     * @param  iterable<Route>  $routes
     * @return list<string>
     */
    public static function differences(iterable $routes): array
    {
        $registered = [];

        foreach ($routes as $route) {
            $registered[self::routeKey($route)] = $route;
        }

        $claimed = [];
        $differences = [];

        foreach (self::rows() as $row) {
            if ($row['method'] === '') {
                $differences[] = "{$row['id']}: no method in its Method · route cell";

                continue;
            }

            $keys = $row['paths'] === [] ? ['GET *'] : array_map(fn (string $path): string => "{$row['method']} {$path}", $row['paths']);
            $open = self::OPEN[$row['id']] ?? null;

            if ($open !== null) {
                $present = array_filter($keys, fn (string $key): bool => isset($registered[$key])) !== [];
                $differences = [...$differences, ...($present || ! isset($registered[$open]) ? ["{$row['id']}: the open difference is gone; remove it from RouteContracts::OPEN"] : [])];
                $claimed[$open] = $row['id'];

                continue;
            }

            foreach ($keys as $key) {
                if ($row['staging']) {
                    if (isset($registered[$key])) {
                        $differences[] = "{$row['id']}: {$key} is registered outside staging";
                        $claimed[$key] = $row['id'];
                    }

                    continue;
                }

                if (! isset($registered[$key])) {
                    $differences[] = "{$row['id']}: no registered route answers {$key}";

                    continue;
                }

                if (isset($claimed[$key])) {
                    $differences[] = "{$row['id']}: {$key} is already {$claimed[$key]}'s";
                }

                $claimed[$key] = $row['id'];
            }
        }

        foreach (array_keys($registered) as $key) {
            if (! isset($claimed[$key])) {
                $differences[] = "{$key}: a registered route without a contract";
            }
        }

        return $differences;
    }

    private static function normalized(string $path): string
    {
        return (string) preg_replace('/\{[^}]+\}/', '{}', ltrim($path, '/'));
    }
}
