<?php

namespace App\Modules\OPS\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\OPS\Models\CorrectionCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-040 *Kasus Koreksi*'s reads (ROUTE-040; SCR-33; SF-CASE; PJ-16; XL-3; QB-12): the correction cases of one company
 * at a time — a branch of the viewer's scope with `projects.view` —, open ones first, each with its kind, state, reason,
 * project and the residuals that keep it open (C-46). The residual counters — an unbacked refund, an unresolved
 * deduction — reach the finance family's viewers, the return equation's — returned, refunded, replaced, unrecovered —
 * the cost family's (PJ-16); a causal project of another company shows only the relation marker, a source lot its code
 * (XL-3). The page lists and starts none: a case opens with the correction that records it.
 */
final class CorrectionCaseList
{
    public const PAGE = 50;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private const FAMILIES = [
        'finance.view' => ['unbacked_refund_amount', 'residual_settlement_amount'],
        'cost.view' => ['returned_cost', 'refunded_value', 'replaced_value', 'unrecovered_value'],
    ];

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /** @return array<string, mixed> */
    public function screen(int $viewerId, ?string $branch, ?string $after): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $branches = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']) as $company) {
            $id = self::int($company->id ?? null);

            if ($viewer->allows('projects.view', $id)) {
                $branches[] = ['id' => self::text($company->public_id ?? null), 'code' => self::text($company->code ?? null), 'name' => self::text($company->legal_name ?? null), 'internal' => $id];
            }
        }

        $chosen = $branches[0] ?? null;

        foreach ($branches as $candidate) {
            if ($candidate['id'] === $branch) {
                $chosen = $candidate;
            }
        }

        $page = $chosen === null ? ['rows' => [], 'more' => null] : $this->page($viewer, $chosen['internal'], $after);

        return [
            'branches' => array_map(fn (array $item): array => array_diff_key($item, ['internal' => true]), $branches),
            'branch' => $chosen['id'] ?? null,
            'rows' => $page['rows'],
            'more' => $page['more'],
            'can' => ['close' => $chosen !== null && $viewer->allows('cases.close', $chosen['internal'])],
        ];
    }

    /**
     * One page of a company's cases after the cursor: open ones first, newest first within each state.
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string}
     */
    public function page(Authority $viewer, int $companyId, ?string $after): array
    {
        // The cursor names the last case shown with the state it had then, so a case closed or reopened since skips or repeats nothing.
        [$afterState, $afterId] = is_string($after) && preg_match('/^(OPEN|CLOSED)~([0-9a-f-]{36})$/i', $after, $parts) === 1 ? [strtoupper($parts[1]), strtolower($parts[2])] : [null, null];
        $cursorId = $afterId === null ? null : DB::table('correction_cases')->where('public_id', $afterId)->where('company_id', $companyId)->value('id');
        $cursor = is_numeric($cursorId) ? (object) ['id' => (int) $cursorId, 'state' => $afterState] : null;
        $query = $viewer->scope->apply(DB::table('correction_cases as c'), 'c.company_id')
            ->where('c.company_id', $companyId)
            ->leftJoin('projects as p', 'p.id', '=', 'c.project_id')
            ->leftJoin('projects as cp', 'cp.id', '=', 'c.causal_project_id')
            ->leftJoin('inventory_lots as l', 'l.id', '=', 'c.source_lot_id')
            // The keyset of the order below: after an open case, the older open ones and every closed one; after a closed one, the older closed ones.
            ->when(is_object($cursor), fn (Builder $where) => $where->where(fn (Builder $keyset) => $keyset
                ->where(fn (Builder $same) => $same->where('c.state', self::text($cursor->state ?? null))->where('c.id', '<', self::int($cursor->id ?? null)))
                ->when(self::text($cursor->state ?? null) === 'OPEN', fn (Builder $later) => $later->orWhere('c.state', 'CLOSED'))))
            ->orderByRaw("c.state = 'OPEN' desc")->orderByDesc('c.id')
            ->limit(self::PAGE + 1)
            ->select([
                'c.id', 'c.public_id', 'c.case_kind', 'c.state', 'c.reason', 'c.closure_outcome', 'c.recorded_at', 'c.closed_at', 'c.reopened_at', 'c.lock_version',
                'c.unbacked_refund_amount', 'c.residual_settlement_amount', 'c.returned_cost', 'c.refunded_value', 'c.replaced_value', 'c.unrecovered_value',
                'p.public_id as project_id', 'p.project_number', 'cp.public_id as causal_id', 'cp.project_number as causal_number', 'cp.company_id as causal_company', 'l.lot_code',
            ])
            // C-46's residuals in the page's one statement, never one per case (QB-12).
            ->selectRaw('c.unbacked_refund_amount <> 0 as r_refund, c.residual_settlement_amount <> 0 as r_settlement')
            ->selectRaw("c.case_kind = 'PURCHASE_RETURN' and c.returned_cost <> c.refunded_value + c.replaced_value + c.unrecovered_value as r_unaccounted");
        $found = $query->get()->all();
        $more = count($found) > self::PAGE;
        $rows = [];

        foreach (array_slice($found, 0, self::PAGE) as $row) {
            $rows[] = $this->row($viewer, $companyId, $row);
        }

        $last = $rows === [] ? null : $rows[count($rows) - 1];

        return ['rows' => $this->withSteps($rows), 'more' => $more && $last !== null ? self::text($last['state'] ?? null).'~'.self::text($last['id'] ?? null) : null];
    }

    /** A case a command names, by public identifier, or unknown to a viewer without `projects.view` of its company (AZ-11). */
    public function find(int $viewerId, string $publicId): CorrectionCase
    {
        $viewer = $this->capabilities->for($viewerId);
        $case = preg_match(self::UUID, $publicId) === 1 ? CorrectionCase::query()->where('public_id', strtolower($publicId))->first() : null;

        if ($case === null || ! $viewer->scope->includes($case->company_id) || ! $viewer->allows('projects.view', $case->company_id)) {
            throw (new ModelNotFoundException)->setModel(CorrectionCase::class, [$publicId]);
        }

        return $case;
    }

    /** @return array<string, mixed> */
    private function row(Authority $viewer, int $companyId, object $row): array
    {
        $counters = [];

        foreach (self::FAMILIES as $family => $columns) {
            if ($viewer->allows($family, $companyId)) {
                foreach ($columns as $column) {
                    $counters[$column] = self::text($row->{$column} ?? '0');
                }
            }
        }

        $residuals = array_keys(array_filter(['unbacked_refund' => $row->r_refund ?? false, 'unresolved_deduction' => $row->r_settlement ?? false, 'unaccounted_return' => $row->r_unaccounted ?? false], fn (mixed $flag): bool => $flag === true));
        $causalCompany = $row->causal_company ?? null;
        // XL-3: another company's causal project is named only when that company is in the viewer's projects scope.
        $foreign = is_numeric($causalCompany) && (int) $causalCompany !== $companyId && ! $viewer->allows('projects.view', (int) $causalCompany);

        return [
            'id' => self::text($row->public_id ?? null),
            'kind' => self::text($row->case_kind ?? null),
            'state' => self::text($row->state ?? null),
            'reason' => is_string($row->reason ?? null) ? $row->reason : null,
            'outcome' => is_string($row->closure_outcome ?? null) ? $row->closure_outcome : null,
            'opened_at' => self::text($row->recorded_at ?? null),
            'reopened' => is_string($row->reopened_at ?? null),
            'lock_version' => self::int($row->lock_version ?? null),
            'project' => is_string($row->project_id ?? null) ? ['id' => $row->project_id, 'number' => self::text($row->project_number ?? null)] : null,
            // XL-3: another company's causal project only as the relation marker.
            'causal' => is_string($row->causal_id ?? null) ? ($foreign ? ['foreign' => true] : ['foreign' => false, 'id' => $row->causal_id, 'number' => self::text($row->causal_number ?? null)]) : null,
            // A lot's code is stock data (XL-3; S1): to `stock.view` only.
            'lot' => is_string($row->lot_code ?? null) && $viewer->has('stock.view') ? $row->lot_code : null,
            // The residuals by name for every viewer; their amounts only to the family that sees them (PJ-16).
            'residuals' => $residuals,
            'counters' => $counters,
        ];
    }

    /**
     * The cases' open steps (CM-10, CM-12; ADMIN_FLOW §11): the companion corrections their opening named, each done once a
     * correction recorded for the case completes it — two statements for the page, never one per case.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withSteps(array $rows): array
    {
        $ids = array_values(array_filter(array_map(fn (array $row): mixed => $row['id'] ?? null, $rows), is_string(...)));

        if ($ids === []) {
            return $rows;
        }

        $named = [];

        foreach (DB::table('audit_events')->where('entity_type', 'correction_case')->where('action', 'OPS.CorrectionCaseOpened')->whereIn('entity_public_id', $ids)->get(['entity_public_id', 'metadata']) as $event) {
            $metadata = is_string($event->metadata ?? null) ? json_decode($event->metadata, true) : null;
            $named[self::text($event->entity_public_id ?? null)] = is_array($metadata) && is_array($metadata['steps'] ?? null) ? array_values(array_filter($metadata['steps'], is_string(...))) : [];
        }

        $done = [];

        foreach (DB::table('audit_events')->whereRaw("metadata->>'correction_case_public_id' = any(?::text[])", ['{'.implode(',', $ids).'}'])->whereRaw("metadata->>'step' is not null")->get(['metadata']) as $event) {
            $metadata = is_string($event->metadata ?? null) ? json_decode($event->metadata, true) : null;

            if (is_array($metadata) && is_string($metadata['correction_case_public_id'] ?? null) && is_string($metadata['step'] ?? null)) {
                $done[$metadata['correction_case_public_id']][$metadata['step']] = true;
            }
        }

        return array_map(function (array $row) use ($named, $done): array {
            $id = self::text($row['id'] ?? null);
            $row['steps'] = array_map(fn (string $step): array => ['key' => $step, 'done' => isset($done[$id][$step])], $named[$id] ?? []);

            return $row;
        }, $rows);
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
