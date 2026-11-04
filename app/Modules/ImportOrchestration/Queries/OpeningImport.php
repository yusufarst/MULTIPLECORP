<?php

namespace App\Modules\ImportOrchestration\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ImportOrchestration\Kinds\Kinds;
use App\Modules\ImportOrchestration\Kinds\OpeningCreditKind;
use App\Modules\ImportOrchestration\Kinds\OpeningReceivableKind;
use App\Modules\ImportOrchestration\Kinds\OpeningStockKind;
use App\Modules\ImportOrchestration\Services\Batches;
use App\Modules\OPS\Models\ImportBatch;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-058 *Impor Data Awal* (ROUTE-058; SCR-58; ADMIN_FLOW §13): the batches the viewer reaches — the Owner, or
 * `import.prepare` with every company of the batch in scope (PJ-20) — with their states, sign-offs and progress; a
 * chosen batch's report — rows by state, each invalid row with its number and reasons, a row whose twin lies outside
 * scope with MSG-18 alone (UXS-19), the control totals per company by family (PJ-22), the row a refusal stopped — and
 * its committed opening facts with "Koreksi Data Awal" where the viewer may correct them (CM-33).
 */
final class OpeningImport
{
    private const LIMIT = 50;

    private const ROWS = 200;

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    public function viewer(int $viewerId): Authority
    {
        return $this->capabilities->for($viewerId);
    }

    /** @return array<string, mixed> */
    public function page(int $viewerId, ?string $chosen): array
    {
        $viewer = $this->viewer($viewerId);
        $owner = $viewer->allows('import.commit');
        $companies = [];

        foreach (DB::table('companies')->where('is_active', true)->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name as name']) as $company) {
            if ($owner || $viewer->allows('import.prepare', (int) Row::text($company, 'id'))) {
                $companies[] = ['id' => Row::text($company, 'public_id'), 'code' => Row::text($company, 'code'), 'name' => Row::text($company, 'name')];
            }
        }

        $batches = [];
        $selected = null;

        foreach (ImportBatch::query()->orderByDesc('id')->limit(self::LIMIT * 4)->get() as $batch) {
            if (! Batches::reaches($viewer, $batch->company_scope) || count($batches) >= self::LIMIT) {
                continue;
            }

            $batches[] = $this->summary($batch);

            if ($chosen !== null && $batch->public_id === strtolower($chosen)) {
                $selected = $batch;
            }
        }

        return [
            'can' => ['prepare' => $owner || $companies !== [], 'commit' => $owner],
            'kinds' => Kinds::codes(),
            'companies' => $companies,
            'batches' => $batches,
            'batch' => $selected === null ? null : $this->detail($viewer, $selected),
        ];
    }

    /** A batch the viewer reaches, by its public id; null for an unknown or denied one alike (DP-05). */
    public function lookup(int $viewerId, string $publicId): ?ImportBatch
    {
        $batch = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? ImportBatch::query()->where('public_id', strtolower($publicId))->first() : null;

        return $batch !== null && Batches::reaches($this->viewer($viewerId), $batch->company_scope) ? $batch : null;
    }

    /** @return array<string, mixed> */
    private function summary(ImportBatch $batch): array
    {
        $counts = DB::table('import_rows')->where('import_batch_id', $batch->id)->groupBy('validation_state')->selectRaw('validation_state, count(*) as total')->pluck('total', 'validation_state')->all();

        return [
            'id' => $batch->public_id, 'kind' => $batch->import_kind, 'cutoff' => $batch->cutoff_date->format('Y-m-d'), 'state' => ImportStatus::display($batch),
            'companies' => array_values(array_map(fn (mixed $code): string => is_string($code) ? $code : '', DB::table('companies')->whereIn('id', $batch->company_scope)->orderBy('code')->pluck('code')->all())),
            'counts' => array_map(fn (mixed $total): int => is_numeric($total) ? (int) $total : 0, $counts),
            'prepared_by' => self::name($batch->prepared_by), 'recorded_at' => $batch->recorded_at->toIso8601String(),
            'admin_signoff' => $batch->admin_signoff_by === null ? null : ['by' => self::name($batch->admin_signoff_by), 'at' => $batch->admin_signoff_at?->toIso8601String()],
            'owner_signoff' => $batch->owner_signoff_by === null ? null : ['by' => self::name($batch->owner_signoff_by), 'at' => $batch->owner_signoff_at?->toIso8601String()],
            'committed_by' => $batch->committed_by === null ? null : self::name($batch->committed_by),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Authority $viewer, ImportBatch $batch): array
    {
        $codes = DB::table('companies')->whereIn('id', $batch->company_scope)->pluck('code', 'id')->all();
        $problems = [];

        foreach (DB::table('import_rows')->where('import_batch_id', $batch->id)->whereIn('validation_state', ['INVALID', 'DUPLICATE'])->orWhere(fn (Builder $rows) => $rows
            ->where('import_batch_id', $batch->id)->where('validation_state', 'VALID')->whereNotNull('errors'))->orderBy('row_number')->limit(self::ROWS)
            ->get(['row_number', 'import_kind', 'import_identity', 'validation_state', 'errors']) as $row) {
            $errors = json_decode(Row::nullable($row, 'errors') ?? '[]', true);
            $problems[] = ['row' => (int) Row::text($row, 'row_number'), 'kind' => Row::text($row, 'import_kind'), 'identity' => Row::text($row, 'import_identity'),
                'state' => Row::text($row, 'validation_state'), 'errors' => is_array($errors) ? array_values(array_filter($errors, 'is_array')) : []];
        }

        $totals = [];

        foreach (is_array($batch->control_totals) ? $batch->control_totals : [] as $company => $values) {
            if (! is_array($values)) {
                continue;
            }

            $id = (int) $company;
            $totals[] = [
                'company' => is_string($codes[$id] ?? null) ? $codes[$id] : '',
                'stock_qty' => $viewer->allows('stock.view', $id) ? self::scalar($values['stock_qty'] ?? null) : null,
                'stock_cost' => $viewer->allows('cost.view', $id) ? self::scalar($values['stock_cost'] ?? null) : null,
                'receivables' => $viewer->allows('finance.view', $id) ? self::scalar($values['receivables'] ?? null) : null,
                'credits' => $viewer->allows('finance.view', $id) ? self::scalar($values['credits'] ?? null) : null,
            ];
        }

        $report = is_array($batch->dry_run_report) ? $batch->dry_run_report : [];

        return [
            ...$this->summary($batch),
            'report' => ['state' => is_string($report['state'] ?? null) ? $report['state'] : null, 'finished_at' => is_string($report['finished_at'] ?? null) ? $report['finished_at'] : null],
            'problems' => $problems,
            'totals' => $totals,
            'facts' => $batch->state === 'COMMITTED' || $batch->committed_at !== null ? $this->facts($viewer, $batch) : [],
        ];
    }

    /**
     * The batch's committed opening facts the viewer may correct (CM-33): an opening lot's movement by `stock.reverse`, a
     * receivable or a credit by `finance.contra`, each within its company.
     *
     * @return list<array{row: int, kind: string, identity: string, target: string}>
     */
    private function facts(Authority $viewer, ImportBatch $batch): array
    {
        $facts = [];

        foreach (DB::table('import_rows')->where('import_batch_id', $batch->id)->where('validation_state', 'COMMITTED')
            ->whereIn('import_kind', [OpeningStockKind::CODE, OpeningReceivableKind::CODE, OpeningCreditKind::CODE])->orderBy('row_number')->limit(self::ROWS)
            ->get(['id', 'row_number', 'import_kind', 'import_identity', 'company_id', 'result_entity_id']) as $row) {
            $company = (int) Row::text($row, 'company_id');
            $kind = Row::text($row, 'import_kind');
            $target = match ($kind) {
                OpeningStockKind::CODE => $viewer->allows('stock.reverse', $company)
                    ? DB::table('stock_movement_entries as e')->join('stock_movements as m', 'm.id', '=', 'e.stock_movement_id')->where('e.lot_id', (int) Row::text($row, 'result_entity_id'))
                        ->where('m.movement_type', 'OPENING')->value('m.public_id') : null,
                default => $viewer->allows('finance.contra', $company) ? $batch->public_id : null,
            };

            if (is_string($target)) {
                $facts[] = ['row' => (int) Row::text($row, 'row_number'), 'kind' => $kind, 'identity' => Row::text($row, 'import_identity'), 'target' => $target];
            }
        }

        return $facts;
    }

    private static function name(?int $userId): string
    {
        $name = $userId === null ? null : DB::table('users')->where('id', $userId)->value('name');

        return is_string($name) ? $name : '';
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '0';
    }
}
