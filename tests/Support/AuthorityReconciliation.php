<?php

namespace Tests\Support;

use App\Modules\IAM\Data\CapabilityCatalogue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\AssertionFailedError;
use ReflectionClass;

/**
 * SM-05's OD-16 reconciliation (H8-05; TASK-069; PERMISSIONS_MATRIX OD-16, AZ-10, RG-02; HO-15), run after every
 * test of the authorization and integration suites — the concurrency suites among them —: it returns no row when
 * no OWNER_ONLY capability sits in a user grant or in the `role_capabilities` of a role other than the built-in
 * OWNER, no OWNER_ONLY audit event names an account without the OWNER role — an event naming no account is LG-01's,
 * which the audit recorder refuses for every class but SYSTEM —, and no audit event of a committed
 * command is recorded below the class of the capability its command type exercises (AZ-10). A refused command's
 * events — ST-03's and SQ-23's SYSTEM records with a human actor among them — are records of a refusal, never
 * anomalies (HO-15): only a committed command's events are judged.
 */
final class AuthorityReconciliation
{
    private const RANK = ['SYSTEM' => 0, 'ADM' => 1, 'ADM_PLUS' => 2, 'OWNER_ONLY' => 3];

    /**
     * The tests not reconciled: Integration/Schema, whose tests write rows directly to prove each constraint, as for the
     * guards (GuardReconciliation), and EffectiveCapabilitiesTest, whose OD-16 case seeds each anomaly the resolver
     * must security-log.
     */
    private const UNRECONCILED = ['Integration/Schema/', 'Integration/Authorization/EffectiveCapabilitiesTest.php'];

    /** @var array<string, string>|null command type => the capability it exercises */
    private static ?array $capabilities = null;

    /**
     * Each anomaly the reconciliation finds, one line each.
     *
     * @return list<string>
     */
    public static function anomalies(): array
    {
        if (! Schema::hasTable('audit_events')) {
            return [];
        }

        $found = [];

        foreach (DB::select(<<<'SQL'
            select 'OWNER_ONLY_IN_USER_GRANT' as kind, g.user_id as subject, c.code
            from user_capability_grants g join capabilities c on c.id = g.capability_id
            where c.authority_class = 'OWNER_ONLY' and g.revoked_at is null
            union all
            select 'OWNER_ONLY_IN_ROLE', rc.role_id, c.code
            from role_capabilities rc join capabilities c on c.id = rc.capability_id join roles r on r.id = rc.role_id
            where c.authority_class = 'OWNER_ONLY' and not (r.is_builtin and r.code = 'OWNER')
            union all
            select 'OWNER_ONLY_EVENT_WITHOUT_OWNER', a.actor_user_id, a.action
            from audit_events a
            where a.authority_class = 'OWNER_ONLY' and a.actor_user_id is not null and not exists (
                select 1 from user_roles ur join roles r on r.id = ur.role_id
                where ur.user_id = a.actor_user_id and r.is_builtin and r.code = 'OWNER')
            SQL) as $row) {
            $found[] = "{$row->kind}: {$row->subject} {$row->code}";
        }

        foreach (DB::select(<<<'SQL'
            select a.id, a.action, a.authority_class, l.command_type
            from audit_events a join command_log l on l.command_id = a.command_id
            where l.state = 'COMMITTED' and a.actor_user_id is not null
            SQL) as $event) {
            $capability = self::capabilities()[$event->command_type] ?? null;

            if ($capability === null || ! CapabilityCatalogue::has($capability)) {
                continue;
            }

            $exercised = CapabilityCatalogue::classOf($capability)->value;

            if (self::RANK[$event->authority_class] < self::RANK[$exercised]) {
                $found[] = "AUTHORITY_CLASS_BELOW_EXERCISED: audit event {$event->id} {$event->action} recorded {$event->authority_class}, its command {$event->command_type} exercising {$capability} ({$exercised})";
            }
        }

        return $found;
    }

    public static function assertNone(string $testFile, object $test): void
    {
        $traits = class_uses_recursive($test);
        $file = str_replace(DIRECTORY_SEPARATOR, '/', $testFile);

        if ((! isset($traits[RefreshDatabase::class]) && ! isset($traits[DatabaseTruncation::class])) || array_filter(self::UNRECONCILED, fn (string $path): bool => str_contains($file, '/tests/'.$path)) !== []) {
            return;
        }

        $anomalies = self::anomalies();

        if ($anomalies !== []) {
            throw new AssertionFailedError("The OD-16 reconciliation (SM-05) found:\n".implode("\n", $anomalies));
        }
    }

    /**
     * Each command type with the capability its action class names — the `TYPE` and `CAPABILITY` of every
     * action of the modules (ARCHITECTURE §§4–5: one class per command).
     *
     * @return array<string, string>
     */
    public static function capabilities(): array
    {
        if (self::$capabilities !== null) {
            return self::$capabilities;
        }

        $map = [];

        foreach (glob(app_path('Modules/*/Actions/*.php')) ?: [] as $file) {
            $class = 'App\\Modules\\'.basename(dirname($file, 2)).'\\Actions\\'.basename($file, '.php');

            if (! class_exists($class)) {
                continue;
            }

            $constants = (new ReflectionClass($class))->getConstants();

            if (is_string($constants['TYPE'] ?? null) && is_string($constants['CAPABILITY'] ?? null)) {
                $map[$constants['TYPE']] = $constants['CAPABILITY'];
            }
        }

        return self::$capabilities = $map;
    }
}
