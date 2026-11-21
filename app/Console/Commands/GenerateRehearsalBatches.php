<?php

namespace App\Console\Commands;

use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Modules\ImportOrchestration\Services\RehearsalBatches;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

/**
 * IMP-13 (TASK-077): the import rehearsal's synthetic batches, written from a plan of counts alone — the data steward's
 * counts per kind, never a real row — for the rehearsal on staging (RL-05; DEP-05). The plan is JSON:
 * `{"companies": ["CTH"], "cutoff": "2025-12-31", "rows": {"PRODUCT": 5000, ...}, "seeds": {"PROJECT": 120, "PURCHASE": 300}}`.
 */
class GenerateRehearsalBatches extends Command
{
    protected $signature = 'import:rehearsal-batches {plan : the JSON plan of counts} {--into= : a new directory for the batch files}';

    protected $description = 'Write the import rehearsal\'s synthetic batches from counts alone (operator only; staging)';

    public function handle(): int
    {
        OperatorIdentity::ensureConsole();
        $plan = $this->argument('plan');
        $into = $this->option('into');

        if (! is_string($plan) || ! is_file($plan) || ! is_string($into) || $into === '' || file_exists($into)) {
            $this->error('Give the plan\'s file and a new directory with --into.');

            return self::INVALID;
        }

        try {
            $data = json_decode((string) file_get_contents($plan), true, 8, JSON_THROW_ON_ERROR);
            $data = is_array($data) ? $data : [];
            $files = RehearsalBatches::generate(
                self::counts($data['rows'] ?? null),
                array_values(array_filter(is_array($data['companies'] ?? null) ? $data['companies'] : [], 'is_string')),
                is_string($data['cutoff'] ?? null) ? $data['cutoff'] : '',
                self::seeds($data['seeds'] ?? null),
            );
        } catch (JsonException|InvalidArgumentException $refused) {
            $this->error($refused->getMessage());

            return self::INVALID;
        }

        mkdir($into, 0700, true);

        foreach ($files as $name => $csv) {
            file_put_contents($into.'/'.$name, $csv);
            $this->line(sprintf('%s  %d rows', $name, substr_count($csv, "\n") - 1));
        }

        $this->info(count($files).' synthetic batch file(s) written; upload each as an import source in this order (IMP-03).');

        return self::SUCCESS;
    }

    /**
     * The plan's seeds: `{"PROJECT": 120}` for the cutoff's year, or each row as the schemes period it —
     * `[{"company": "CTH", "type": "PROJECT", "period": "2025-12", "last": 120}]`.
     *
     * @return array<string, int>|list<array{company: string, type: string, period: string, last: int}>
     */
    private static function seeds(mixed $values): array
    {
        if (! is_array($values)) {
            return ['PROJECT' => 0, 'PURCHASE' => 0];
        }

        if (! array_is_list($values)) {
            return self::counts($values);
        }

        $rows = [];

        foreach ($values as $seed) {
            if (! is_array($seed) || ! is_string($seed['company'] ?? null) || ! is_string($seed['type'] ?? null) || ! is_string($seed['period'] ?? null) || ! is_int($seed['last'] ?? null)) {
                throw new InvalidArgumentException('Each seed names its company, type, period and last number.');
            }

            $rows[] = ['company' => $seed['company'], 'type' => $seed['type'], 'period' => $seed['period'], 'last' => $seed['last']];
        }

        return $rows;
    }

    /** @return array<string, int> */
    private static function counts(mixed $values): array
    {
        $counts = [];

        foreach (is_array($values) ? $values : [] as $key => $value) {
            $counts[(string) $key] = is_int($value) ? $value : 0;
        }

        return $counts;
    }
}
