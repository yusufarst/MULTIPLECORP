<?php

namespace Tests\Support;

use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use Closure;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;

/**
 * TEST_STRATEGY TS-11: after every test of the Integration suite each registered reconciliation runs,
 * and any difference between a guard and its facts fails the test; ARCHITECTURE §7's ReconcileGuards
 * families are reconciled after each such test that has a database. Two directories are not reconciled:
 * Integration/Schema, whose tests write guard and fact rows directly to prove each constraint, and
 * Integration/Command, whose test commands move guards with no facts to prove the envelope (TASK-013).
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

    /** The directories, under tests/, whose tests are not reconciled. */
    private const UNRECONCILED = ['Integration/Schema/', 'Integration/Command/'];

    /**
     * Fails on any difference: those of the registered checks, and — for an integration test with a database
     * of its own — every ReconcileGuards family's.
     */
    public static function assertNone(string $testFile = '', ?object $test = null): void
    {
        $file = str_replace(DIRECTORY_SEPARATOR, '/', $testFile);

        foreach (self::UNRECONCILED as $directory) {
            if (str_contains($file, '/tests/'.$directory)) {
                return;
            }
        }

        $differences = [...self::differences(), ...($test !== null && self::hasDatabase($test) ? self::familyDifferences() : [])];

        if ($differences !== []) {
            throw new AssertionFailedError("Guards differ from their facts:\n".implode("\n", $differences));
        }
    }

    /** @return list<string> */
    public static function familyDifferences(): array
    {
        $found = [];

        foreach (Families::all() as $family) {
            foreach ((new Reconciler)->differences(DB::connection(), $family) as $difference) {
                $found[] = 'ReconcileGuards: '.json_encode($difference, JSON_THROW_ON_ERROR);
            }
        }

        foreach (Families::verifications() as $verification) {
            foreach ((new Reconciler)->violations(DB::connection(), $verification) as $violation) {
                $found[] = 'ReconcileGuards: '.json_encode($violation, JSON_THROW_ON_ERROR);
            }
        }

        return $found;
    }

    private static function hasDatabase(object $test): bool
    {
        $traits = class_uses_recursive($test);

        return isset($traits[RefreshDatabase::class]) || isset($traits[DatabaseTruncation::class]);
    }
}
