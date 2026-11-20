<?php

namespace Tests\Support;

use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Support\Command\CommandRequest;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\AssertionFailedError;
use ReflectionClass;

/**
 * SM-05's OD-16 reconciliation (H8-05; TASK-069; PERMISSIONS_MATRIX OD-16, AZ-10, RG-02; HO-15), run after every
 * test of the authorization and integration suites — the concurrency suites among them —: it returns no row when
 * no OWNER_ONLY capability sits in a user grant or in the `role_capabilities` of a role other than the built-in
 * OWNER, no OWNER_ONLY audit event names an account without the OWNER role — an event naming no account is LG-01's,
 * which the audit recorder refuses for every class but SYSTEM —, and no audit event of a committed command is recorded
 * below the class of the capability that command exercised (AZ-10). The capability is the command's own request's,
 * observed as each command claims its identity (CI-03), whatever form the command takes — an action naming its
 * capability, or a module service told it by the action —; a command not observed — a seeded row, or the second session
 * of a race, which Laravel's concurrency runs in a process of its own — is judged by its action's `TYPE` and
 * `CAPABILITY` where the action names both; the same command types run in this process across the suites are judged by
 * their requests. A refused command's events — ST-03's and SQ-23's SYSTEM records with a human actor among them — are records of a
 * refusal, never anomalies (HO-15): only a committed command's events are judged.
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

    /**
     * The consequences a command records below the class it exercised — a project's revalidation (SF-REVAL), a case
     * reopened, a reservation restored inside a reversal or a restoration, a requirement's evidence unlinked by a void,
     * the document a void issues —, held open as TASK-069's finding F-04 for the Owner's decision: AZ-10 asks every event
     * of a command to record the highest class the command exercised, while these keep their own. By the consequence's
     * action and the class it records; any other event below its command's class is an anomaly.
     *
     * @var array<string, string>
     */
    public const DERIVED = [
        'PRJ.ProjectRevalidated' => 'SYSTEM',
        'OPS.CorrectionCaseReopened' => 'SYSTEM',
        'INV.ReservationCreated' => 'ADM',
        'ADM.RequirementUnlinked' => 'ADM',
        'DOC.DocumentIssued' => 'ADM',
    ];

    /** @var array<string, string>|null command type => the capability its action class names */
    private static ?array $capabilities = null;

    /** @var array<string, string> command id => the capability its request exercised, in this test */
    private static array $exercised = [];

    /**
     * Observes, for the test about to run, each command's request as it claims its identity in `command_log`: the claim
     * is the request's own insert (CommandLog::claim), so the request is on the stack that runs it.
     */
    public static function observe(): void
    {
        self::$exercised = [];

        DB::listen(function (QueryExecuted $query): void {
            if (! str_starts_with(ltrim($query->sql), 'insert') || ! str_contains($query->sql, '"command_log"')) {
                return;
            }

            foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 64) as $frame) {
                foreach ($frame['args'] ?? [] as $argument) {
                    if ($argument instanceof CommandRequest) {
                        self::$exercised[$argument->commandId] = $argument->capability;

                        return;
                    }
                }
            }
        });
    }

    /** The capability a command exercised: its request's, observed, else its action's. */
    public static function exercised(string $commandId, string $commandType): ?string
    {
        return self::$exercised[$commandId] ?? self::capabilities()[$commandType] ?? null;
    }

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
            select a.id, a.action, a.authority_class, l.command_type, l.command_id
            from audit_events a join command_log l on l.command_id = a.command_id
            where l.state = 'COMMITTED' and a.actor_user_id is not null
            SQL) as $event) {
            $capability = self::exercised((string) $event->command_id, (string) $event->command_type);

            if ($capability === null || ! CapabilityCatalogue::has($capability)) {
                continue;
            }

            $exercised = CapabilityCatalogue::classOf($capability)->value;

            if (self::RANK[$event->authority_class] < self::RANK[$exercised] && (self::DERIVED[$event->action] ?? null) !== $event->authority_class) {
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
     * Each command type with the capability its action class names — the `TYPE` and `CAPABILITY` of every action of
     * the modules that names both (ARCHITECTURE §§4–5) —, for a command whose request was not observed.
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
