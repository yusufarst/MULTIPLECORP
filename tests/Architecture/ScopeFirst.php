<?php

namespace Tests\Architecture;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

/**
 * SM-03's scope-first rule over a module's query classes (SECURITY AZ-04, EN-04; PERMISSIONS_MATRIX CS-07) with SM-25's
 * family rule (PJ-22), read from the source: every public read taking a viewer resolves that viewer's authority, holds
 * the family named, and applies the company scope to its query before any query runs.
 */
final class ScopeFirst
{
    /** The calls that run a query: none may come before the family and the scope. */
    private const RUNS = ['->get(', '->first(', '->sole(', '->value(', '->pluck(', '->sum(', '->count(', '->exists(', '->paginate(', '->cursor(', '->chunk', '->lazy(', 'DB::select', 'DB::scalar'];

    /**
     * @param  list<class-string>  $classes
     * @return list<string> each read breaking the rule, as "Class::method: what it lacks"
     */
    public static function violations(array $classes, string $family): array
    {
        $violations = [];

        foreach ($classes as $class) {
            foreach (self::reads($class) as $method => $body) {
                $runs = array_filter(array_map(fn (string $run): int|false => strpos($body, $run), self::RUNS), fn (int|false $at): bool => $at !== false);
                $run = $runs === [] ? PHP_INT_MAX : min($runs);
                $held = strpos($body, "->has('{$family}')");
                $scoped = strpos($body, '->scope->apply(');

                if (strpos($body, '->for($viewerId)') === false) {
                    $violations[] = "{$class}::{$method}: resolves no viewer";

                    continue;
                }

                if ($held === false || $held > $run) {
                    $violations[] = "{$class}::{$method}: holds no {$family} before its query runs";
                }

                if ($scoped === false || $scoped > $run) {
                    $violations[] = "{$class}::{$method}: applies no company scope before its query runs";
                }
            }
        }

        return $violations;
    }

    /**
     * @param  list<class-string>  $classes
     * @return list<class-string> the classes with no viewer read — system checks, to be named one by one
     */
    public static function viewerless(array $classes): array
    {
        return array_values(array_filter($classes, fn (string $class): bool => self::reads($class) === []));
    }

    /** @return list<class-string> the classes of a folder of query classes */
    public static function classes(string $directory, string $namespace): array
    {
        return array_map(fn (string $file): string => $namespace.'\\'.basename($file, '.php'), glob($directory.'/*.php') ?: []);
    }

    /**
     * @param  class-string  $class
     * @return array<string, string> each public method of the class taking a viewer, with its source
     */
    private static function reads(string $class): array
    {
        $reflection = new ReflectionClass($class);
        $lines = file((string) $reflection->getFileName()) ?: [];
        $reads = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $parameters = array_map(fn (ReflectionParameter $parameter): string => $parameter->getName(), $method->getParameters());

            if ($method->getDeclaringClass()->getName() === $class && in_array('viewerId', $parameters, true)) {
                $start = (int) $method->getStartLine();
                $reads[$method->getName()] = implode('', array_slice($lines, $start - 1, (int) $method->getEndLine() - $start + 1));
            }
        }

        return $reads;
    }
}
