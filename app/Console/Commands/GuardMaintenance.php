<?php

namespace App\Console\Commands;

use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Database\DatabaseRole;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `GuardMaintenance` (ARCHITECTURE §7; DATABASE §3.1; GU-07; H9-03; GAP-031): the one audited exceptional writer
 * of guards, an operator's console command — never scheduled, never part of normal operation — run under the
 * administration role in a maintenance window (TX-08) after a verified defect or a restore. It recomputes the
 * differing rows of one family in one transaction, in key order, and records an audit event per row with its
 * before/after, the reason and the defect or restore reference.
 */
class GuardMaintenance extends Command
{
    protected $signature = 'guards:maintain {family : the guard table} {--reason= : why the guard is rebuilt} {--reference= : the verified defect or the restore} {--operator= : the operator\'s stated name}';

    protected $description = 'Rebuild the differing rows of one guard family from their facts, audited per row (maintenance window only)';

    public function handle(Reconciler $reconciler, AuditRecorder $audit): int
    {
        $named = $this->argument('family');
        $family = is_string($named) ? Families::named($named) : null;
        $reason = $this->text('reason');
        $reference = $this->text('reference');

        if ($family === null || $reason === '' || $reference === '') {
            $this->error('Name a registered guard family, the reason and the defect or restore reference.');

            return self::INVALID;
        }

        $connection = DB::connection(DatabaseRole::ADMINISTRATION_CONNECTION);

        $repaired = $connection->transaction(function () use ($connection, $family, $reconciler, $audit, $reason, $reference) {
            // One snapshot for the read and the writes: a posting that commits a row of the family after it fails
            // the rebuild with a serialization error rather than being overwritten (TX-08); the operator reruns it.
            $connection->statement('set transaction isolation level repeatable read');
            $differences = $reconciler->differences($connection, $family);
            usort($differences, fn (array $a, array $b): int => array_values($a['key']) <=> array_values($b['key']));

            if ($family->carried() === null && array_filter($differences, fn (array $difference): bool => $difference['stored'] === null) !== []) {
                return null;
            }

            foreach ($differences as $difference) {
                $where = $connection->table($family->table());

                foreach ($difference['key'] as $column => $value) {
                    $where->where($column, $value);
                }

                $difference['stored'] === null
                    ? $connection->table($family->table())->insert([...$difference['key'], ...$difference['carried'], ...$difference['expected']])
                    : $where->update($difference['expected']);

                $audit->record('guards.maintain', AuthorityClass::System, null, null,
                    entityType: $family->table(),
                    reason: $reason,
                    change: AuditChange::updated($difference['stored'] ?? [], $difference['expected']),
                    metadata: ['key' => $difference['key'], 'reference' => $reference, 'operator' => ReconcileGuards::operator($this->option('operator'))],
                    connection: $connection,
                );
            }

            return count($differences);
        });

        if ($repaired === null) {
            $this->error("A {$family->table()} row is missing and holds a column this family does not recompute; nothing was rebuilt.");

            return self::FAILURE;
        }

        $this->info("{$repaired} row(s) of {$family->table()} rebuilt from their facts, each audited.");

        return self::SUCCESS;
    }

    private function text(string $option): string
    {
        $value = $this->option($option);

        return is_string($value) ? trim($value) : '';
    }
}
