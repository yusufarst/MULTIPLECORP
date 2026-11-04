<?php

namespace App\Modules\ImportOrchestration\Services;

use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Modules\ImportOrchestration\Kinds\Kinds;
use App\Modules\ImportOrchestration\Kinds\SeedRows;
use App\Modules\OPS\Models\ImportBatch;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A batch's rows in commit order — its seed rows first, so a period is seeded before a row numbers in it (IMP-03;
 * IMP-07), then by row number — read in keyed chunks (PF-11), and the control totals per company of its rows.
 */
final class BatchRows
{
    public function __construct(private readonly Kinds $kinds) {}

    /**
     * @param  list<string>  $states
     * @return list<ImportRowData>
     */
    public function rows(ImportBatch $batch, array $states, ?int $limit = null, bool $withRefusals = true): array
    {
        $query = DB::table('import_rows')->where('import_batch_id', $batch->id)->whereIn('validation_state', $states)
            ->orderByRaw("case when import_kind like 'NUMBER_SEED:%' then 0 else 1 end")->orderBy('row_number');

        if (! $withRefusals) {
            $query->where(fn (Builder $rows) => $rows->whereNull('errors')->orWhereRaw('errors @> ?::jsonb', [json_encode([['reason' => RowRunner::IN_PROGRESS]])]));
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $cutoff = $batch->cutoff_date->format('Y-m-d');
        $rows = [];

        foreach ($query->get(['id', 'row_number', 'import_kind', 'import_identity', 'company_id', 'normalized_payload']) as $row) {
            $payload = json_decode(Row::text($row, 'normalized_payload'), true);
            $rows[] = new ImportRowData((int) Row::text($row, 'id'), $batch->id, $batch->public_id, $cutoff, (int) Row::text($row, 'row_number'), Row::text($row, 'import_kind'),
                Row::text($row, 'import_identity'), Row::nullable($row, 'company_id') === null ? null : (int) Row::text($row, 'company_id'), is_array($payload) ? self::keyed($payload) : []);
        }

        return $rows;
    }

    /**
     * The control totals per company of the rows given — stock quantity and cost, receivables, credits (IMP-04; IMP-11).
     *
     * @param  list<ImportRowData>  $rows
     * @return array<int, array<string, string>>
     */
    public function totals(array $rows): array
    {
        $totals = [];

        foreach ($rows as $row) {
            if ($row->companyId === null || str_starts_with($row->kind, SeedRows::PREFIX)) {
                continue;
            }

            foreach ($this->kinds->of($row->kind)->totals($row->payload) as $measure => $value) {
                $current = $totals[$row->companyId][$measure] ?? '0';
                $totals[$row->companyId][$measure] = (string) BigDecimal::of($current)->plus(BigDecimal::of($value));
            }
        }

        return $totals;
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<string, mixed>
     */
    private static function keyed(array $payload): array
    {
        $keyed = [];

        foreach ($payload as $key => $value) {
            $keyed[(string) $key] = $value;
        }

        return $keyed;
    }
}
