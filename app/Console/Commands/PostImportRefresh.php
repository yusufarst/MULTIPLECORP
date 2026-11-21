<?php

namespace App\Console\Commands;

use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Support\Backup\PostgresTools;
use App\Support\Performance\PostImportRefresh as Refresh;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * CUT-06, IMP-10 (TASK-077; PRQ-08; HO-36): after the opening import commits and before go-live, the planner statistics
 * refreshed and each registered hot query's plan reported on the imported data — `EXPLAIN` only, no data value printed.
 * It runs under the owner's login given to the run, as the backup tooling's (`BACKUP_DB_USERNAME`, `BACKUP_DB_PASSWORD`),
 * which `ANALYZE` needs. Exit 1 when a plan reads a relation over 10,000 rows in full, for the operator's review.
 */
class PostImportRefresh extends Command
{
    protected $signature = 'ops:post-import-refresh';

    protected $description = 'Refresh the planner statistics and report the hot queries\' plans on the imported data (operator only; before go-live)';

    public function handle(Refresh $refresh): int
    {
        OperatorIdentity::ensureConsole();

        try {
            $report = $refresh->run(PostgresTools::login());
        } catch (RuntimeException $refused) {
            $this->error($refused->getMessage());

            return self::FAILURE;
        }

        $review = 0;

        foreach ($report as $plan) {
            $flag = $plan['large_scans'] !== [] ? 'REVIEW' : ($plan['uses_index'] ? 'OK' : 'NO INDEX');
            $review += $plan['large_scans'] !== [] ? 1 : 0;
            $this->line(sprintf('%-8s %s — %s; expects %s; cost %s%s', $flag, $plan['use'], $plan['top'], $plan['index'], $plan['cost'],
                $plan['large_scans'] === [] ? '' : '; full scan of '.implode(', ', $plan['large_scans'])));
        }

        $this->info('Statistics refreshed; '.count($report).' plans reported, '.$review.' to review (PERFORMANCE §12 step 4).');

        return $review === 0 ? self::SUCCESS : self::FAILURE;
    }
}
