<?php

namespace App\Console\Commands;

use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\CorrelationId;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * `ReconcileGuards` (ARCHITECTURE §7; DATABASE §3.1; GAP-031; H9-03): recomputes each registered guard family
 * from its formula, each family in its own read-only snapshot (TX-05; JB-06), and reports every difference —
 * never repairing one; a repair is `GuardMaintenance`'s, by the operator. Each run is audited, its differences
 * and unverified families logged, its exit code 1 when any is found, so the operator sees it. Its nightly
 * schedule is JB-06's job, registered with the jobs (TASK-022); here it runs on demand.
 */
class ReconcileGuards extends Command
{
    protected $signature = 'guards:reconcile {--operator= : the operator\'s stated name}';

    protected $description = 'Recompute every registered guard from its facts and report each difference, repairing nothing';

    public function handle(Reconciler $reconciler, AuditRecorder $audit): int
    {
        $differences = [];
        $unverified = [];

        // JB-06: each family inside its own TX-05 snapshot, facts and guard compared at one instant; a family
        // that fails is tried once more, then reported as not verified.
        foreach (Families::all() as $family) {
            for ($attempt = 1; ; $attempt++) {
                try {
                    $differences = [...$differences, ...DB::transaction(function () use ($reconciler, $family): array {
                        DB::statement('set transaction isolation level repeatable read read only');
                        DB::statement("set local statement_timeout = '300s'");

                        return $reconciler->differences(DB::connection(), $family);
                    })];

                    break;
                } catch (QueryException $error) {
                    if ($attempt === 2) {
                        $unverified[] = $family->table();
                        Log::error('A guard family could not be verified (ReconcileGuards; JB-06).', ['family' => $family->table(), 'state' => (string) $error->getCode(), 'correlation_id' => CorrelationId::current()]);
                        $this->error("{$family->table()}: not verified");

                        break;
                    }
                }
            }
        }

        foreach ($differences as $difference) {
            Log::warning('A guard differs from its facts (ReconcileGuards; GAP-031).', [...$difference, 'correlation_id' => CorrelationId::current()]);
            $this->line(json_encode($difference, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        }

        DB::transaction(fn () => $audit->record('guards.reconcile', AuthorityClass::System, null, null, metadata: [
            'families' => array_map(fn ($family) => $family->table(), Families::all()),
            'differences' => count($differences),
            'unverified' => $unverified,
            'operator' => self::operator($this->option('operator')),
        ]));

        $this->info(sprintf('%d difference(s) found; nothing repaired.', count($differences)));

        return $differences === [] && $unverified === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The operator's identity as SECURITY AU-14 records it: the host account and the stated name.
     *
     * @return array{host_account: string, stated_name: string|null}
     */
    public static function operator(mixed $statedName): array
    {
        return ['host_account' => (string) get_current_user(), 'stated_name' => is_string($statedName) && $statedName !== '' ? $statedName : null];
    }
}
