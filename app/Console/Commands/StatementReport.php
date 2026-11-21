<?php

namespace App\Console\Commands;

use App\Support\Monitoring\StatementStatistics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * PERFORMANCE §12 item 6, GT-07 and PFM-18's monthly report (TASK-075): the heaviest statements by `pg_stat_statements`
 * — normalized, so no value appears — and the largest tables and indexes; an entry past half of its threshold opens a
 * measured review, logged, never an automatic change. Run monthly by the scheduler.
 */
class StatementReport extends Command
{
    protected $signature = 'ops:statement-report {--top=10 : the statements listed}';

    protected $description = 'Report the heaviest statements and the table and index sizes for the monthly review';

    public function handle(StatementStatistics $statistics): int
    {
        $report = $statistics->report(max(1, is_numeric($this->option('top')) ? (int) $this->option('top') : 10));

        if (! $report['available']) {
            $this->warn('pg_stat_statements is not installed in this database: the statements are not reported (INFRASTRUCTURE §4.2).');
        }

        foreach ($report['statements'] as $statement) {
            $this->line(sprintf('%s %8d calls  %9.1f ms mean  %s', $statement['review'] ? 'REVIEW' : 'OK    ', $statement['calls'], $statement['mean_us'] / 1000, $statement['query']));
        }

        foreach ($report['relations'] as $relation) {
            $this->line(sprintf('%s %-50s %12d bytes', $relation['review'] ? 'REVIEW' : 'OK    ', $relation['name'], $relation['bytes']));
        }

        $reviews = count(array_filter([...$report['statements'], ...$report['relations']], fn (array $entry): bool => $entry['review']));

        if ($reviews > 0) {
            Log::warning('The monthly statement report opens a measured review (PFM-18).', ['entries' => $reviews]);
        }

        $this->info("Monthly report: {$reviews} entr".($reviews === 1 ? 'y' : 'ies').' past half of a threshold, for a measured review; nothing was changed.');

        return self::SUCCESS;
    }
}
