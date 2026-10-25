<?php

namespace App\Support\Command;

use Illuminate\Contracts\Database\LostConnectionDetector;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * The failure mapping of CONCURRENCY_IDEMPOTENCY §12: a database error raised by a command's statements becomes
 * its answer. A constraint or trigger names its rule — the C- or L- row it is named after, its constraint or
 * function name read from the server's error (`constraint "…"`, `PL/pgSQL function …`) — so a refusal carries
 * that rule; a name without a registered rule is a defect (SQ-19). The retryable classes are SQ-12, SQ-13 and
 * SQ-16, and SQ-17's commit once the probe shows it did not happen (RY-01); timeouts are never retried by the
 * server (RY-07).
 */
final class FailureMapper
{
    public const RETRY = 'retry';

    public const NOT_FOUND = 'not_found';

    /** A connection that ended before COMMIT was sent (SQ-16). */
    private const SESSION_ENDED = ['57P01', '57P02', '57P03', '53300', '08000', '08001', '08003', '08004', '08006'];

    /**
     * The decision for an error: an Outcome to answer, RETRY to run the whole action again from phase A, or
     * NOT_FOUND for the AZ-11 answer of a reference that does not resolve (SQ-09).
     */
    public static function map(Throwable $error): Outcome|string
    {
        if (self::lostSession($error)) {
            return self::RETRY;
        }

        if (! $error instanceof QueryException) {
            return Outcome::failed('SQ-19 '.$error::class);
        }

        $state = (string) $error->getCode();
        $rule = self::ruleOf($error);
        $name = self::nameOf($error);

        return match (true) {
            $state === '40001', $state === '40P01' => self::RETRY,
            $state === '55P03' => Outcome::failed('SQ-14 lock timeout'),
            in_array($state, ['57014', '25P03', '25P04'], true) => Outcome::failed('SQ-15 statement timeout'),
            // A row still referenced is the referenced side of a key: a defect, never a missing record (SQ-09).
            $state === '23503' && str_contains($error->getMessage(), 'is still referenced from table') => Outcome::failed('SQ-19 23503'.($name === null ? '' : " {$name}")),
            // The scale key of ST-09 is a stale product scale (SQ-03); every other key is a reference that does
            // not resolve (SQ-09).
            $state === '23503' && $name !== null && str_contains($name, '_scale') => Outcome::conflict('ST-09'),
            $state === '23503' => self::NOT_FOUND,
            $state === '23505' && $name !== null && str_ends_with($name, '_c48_command_identity') => Outcome::conflict('SQ-07'),
            $state === '23505' => Outcome::conflict($rule ?? 'SQ-08'),
            $state === '23P01' => Outcome::rejected('C-52'),
            $state === '23514' && $rule !== null => Outcome::rejected($rule),
            default => Outcome::failed("SQ-19 {$state}".($name === null ? '' : " {$name}")),
        };
    }

    /**
     * Whether the session ended under the command (SQ-16, SQ-17): a server state that ends it, or a lost
     * connection as the framework's own detector reads it — a driver error such as HY000 "server closed the
     * connection unexpectedly" carries no server state.
     */
    public static function lostSession(Throwable $error): bool
    {
        if ($error instanceof QueryException && in_array((string) $error->getCode(), self::SESSION_ENDED, true)) {
            return true;
        }

        return app(LostConnectionDetector::class)->causedByLostConnection($error);
    }

    /** The constraint or function an error names, as the server reports it. */
    public static function nameOf(QueryException $error): ?string
    {
        $message = $error->getMessage();

        if (preg_match('/constraint "([a-z0-9_]+)"/', $message, $match) === 1) {
            return $match[1];
        }

        if (preg_match('/PL\/pgSQL function ([a-z0-9_]+)\(/', $message, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    /** The rule a constraint or function is named after — `…_c56_…` is C-56, `…_l31_…` L-31 — if any. */
    public static function ruleOf(QueryException $error): ?string
    {
        $name = self::nameOf($error);

        if ($name === null || preg_match('/(?:^|_)([cl])(\d{2})(?:_|$)/', $name, $match) !== 1) {
            return null;
        }

        return strtoupper($match[1]).'-'.$match[2];
    }
}
