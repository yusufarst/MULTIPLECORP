<?php

namespace App\Modules\ImportOrchestration\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ImportOrchestration\Kinds\Kinds;
use App\Modules\ImportOrchestration\Kinds\OpeningCreditKind;
use App\Modules\ImportOrchestration\Kinds\OpeningReceivableKind;
use App\Modules\ImportOrchestration\Kinds\OpeningStockKind;
use App\Modules\ImportOrchestration\Models\Batch as ImportBatch;
use App\Modules\ImportOrchestration\Services\Batches;
use App\Modules\ImportOrchestration\Services\BatchRows;
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

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly BatchRows $rows,
    ) {}

    public function viewer(int $viewerId): Authority
    {
        return $this->capabilities->for($viewerId);
    }

    /** @return array<string, mixed> */
    public function page(int $viewerId, ?string $chosen, ?string $after = null): array
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
        $query = ImportBatch::query()->orderByDesc('id');

        // PJ-20 in the query, as Batches::reaches decides one batch: an Admin's batches are those whose every company lies
        // in the scope of the Admin's `import.prepare` — by the grants, never by the active companies a batch is prepared
        // for, so a deactivated company's batches stay listed while its grants stand (DeactivateCompany; CS-02).
        if (! $owner) {
            if (! $viewer->has('import.prepare')) {
                $query->whereRaw('false');
            } elseif ($viewer->scope->all) {
                $query->whereRaw('cardinality(company_scope) > 0');
            } else {
                $query->whereRaw('cardinality(company_scope) > 0 and company_scope <@ ?::bigint[]', ['{'.implode(',', $viewer->scope->companyIds).'}']);
            }
        }

        // An unknown or malformed cursor starts from the newest (IP-18); only a well-formed one reaches the uuid column.
        $before = $after === null || preg_match(self::UUID, $after) !== 1 ? null : DB::table('import_batches')->where('public_id', strtolower($after))->value('id');

        if (is_numeric($before)) {
            $query->where('id', '<', (int) $before);
        }

        $page = $query->limit(self::LIMIT + 1)->get()->all();

        foreach (array_slice($page, 0, self::LIMIT) as $batch) {
            $batches[] = $this->summary($batch);
        }

        $selected = $chosen === null ? null : $this->lookup($viewerId, $chosen);
        $last = $batches === [] ? null : $batches[array_key_last($batches)]['id'];

        return [
            'can' => ['prepare' => $owner || $companies !== [], 'commit' => $owner],
            'kinds' => Kinds::codes(),
            'companies' => $companies,
            'batches' => $batches,
            'more' => count($page) > self::LIMIT ? $last : null,
            'batch' => $selected === null ? null : $this->detail($viewer, $selected),
        ];
    }

    /** A batch the viewer reaches, by its public id; null for an unknown or denied one alike (DP-05). */
    public function lookup(int $viewerId, string $publicId): ?ImportBatch
    {
        $batch = preg_match(self::UUID, $publicId) === 1 ? ImportBatch::query()->where('public_id', strtolower($publicId))->first() : null;

        return $batch !== null && Batches::reaches($this->viewer($viewerId), $batch->company_scope) ? $batch : null;
    }

    /** A batch's public id by its row id. */
    public function publicIdOf(?int $id): ?string
    {
        $publicId = $id === null ? null : DB::table('import_batches')->where('id', $id)->value('public_id');

        return is_string($publicId) ? $publicId : null;
    }

    /**
     * An OPENING stock movement by its public id (ROUTE-213).
     *
     * @return array{id: int, company: int}|null
     */
    public function openingMovement(string $publicId): ?array
    {
        $row = DB::table('stock_movements')->where('public_id', strtolower($publicId))->where('movement_type', 'OPENING')->first(['id', 'company_id']);

        return $row === null ? null : ['id' => (int) Row::text($row, 'id'), 'company' => (int) Row::text($row, 'company_id')];
    }

    /**
     * An opening receivable or credit (ROUTE-214): an OPENING invoice by its public id, or a batch's committed row by its number;
     * its kind, company, record and batch. Authorized by its command's `finance.contra` in that company, never by the import's scope.
     *
     * @return array{kind: string, company: int, entity: int, batch: string|null}|null
     */
    public function openingFact(string $id, int $rowNumber): ?array
    {
        $invoice = DB::table('invoices')->where('public_id', strtolower($id))->where('origin', 'OPENING')->value('import_row_id');
        $rows = DB::table('import_rows')->where('validation_state', 'COMMITTED')->whereIn('import_kind', [OpeningReceivableKind::CODE, OpeningCreditKind::CODE]);
        $row = (is_numeric($invoice)
            ? $rows->where('id', (int) $invoice)
            : $rows->where('import_batch_id', DB::table('import_batches')->where('public_id', strtolower($id))->value('id'))->where('row_number', $rowNumber))
            ->first(['import_kind', 'company_id', 'result_entity_id', 'import_batch_id']);

        return $row === null ? null : ['kind' => Row::text($row, 'import_kind'), 'company' => (int) Row::text($row, 'company_id'), 'entity' => (int) Row::text($row, 'result_entity_id'),
            'batch' => $this->publicIdOf((int) Row::text($row, 'import_batch_id'))];
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

        $totals = $this->projected($viewer, is_array($batch->control_totals) ? $batch->control_totals : [], $codes);
        $committed = $batch->state === 'COMMITTED' || $batch->committed_at !== null ? $this->projected($viewer, $this->rows->factTotals($batch->id), $codes) : [];
        $report = is_array($batch->dry_run_report) ? $batch->dry_run_report : [];

        return [
            ...$this->summary($batch),
            'report' => ['state' => is_string($report['state'] ?? null) ? $report['state'] : null, 'finished_at' => is_string($report['finished_at'] ?? null) ? $report['finished_at'] : null],
            'problems' => $problems,
            'totals' => $totals,
            'committed_totals' => $committed,
            'facts' => $batch->state === 'COMMITTED' || $batch->committed_at !== null ? $this->facts($viewer, $batch) : [],
        ];
    }

    /**
     * Totals per company by family (PJ-22): stock quantity with `stock.view`, its cost with `cost.view`, receivables and credits with `finance.view`.
     *
     * @param  array<mixed>  $source
     * @param  array<mixed>  $codes
     * @return list<array{company: string, stock_qty: string|null, stock_cost: string|null, receivables: string|null, credits: string|null}>
     */
    private function projected(Authority $viewer, array $source, array $codes): array
    {
        $totals = [];

        foreach ($source as $company => $values) {
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

        return $totals;
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
