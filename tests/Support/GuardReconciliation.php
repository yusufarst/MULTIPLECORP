<?php

namespace Tests\Support;

use Closure;
use PHPUnit\Framework\AssertionFailedError;

/**
 * TEST_STRATEGY TS-11: after every test of the Integration suite each registered reconciliation runs,
 * and any difference between a guard and its facts fails the test. ARCHITECTURE §7's ReconcileGuards
 * registers here once its guards exist; until then nothing is registered.
 */
final class GuardReconciliation
{
    /** @var array<string, Closure(): list<string>> */
    private static array $checks = [];

    /**
     * @param  Closure(): list<string>  $check  returns each difference it finds
     */
    public static function register(string $name, Closure $check): void
    {
        self::$checks[$name] = $check;
    }

    public static function forget(string $name): void
    {
        unset(self::$checks[$name]);
    }

    /**
     * @return list<string>
     */
    public static function differences(): array
    {
        $found = [];

        foreach (self::$checks as $name => $check) {
            foreach ($check() as $difference) {
                $found[] = "{$name}: {$difference}";
            }
        }

        return $found;
    }

    public static function assertNone(): void
    {
        $differences = self::differences();

        if ($differences !== []) {
            throw new AssertionFailedError("Guards differ from their facts:\n".implode("\n", $differences));
        }
    }
}
