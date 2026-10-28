<?php

namespace App\Modules\PRJ\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-010 *Proyek*'s reads (ROUTE-010; SCR-09; NAV-010; QB-01; `DIR-077` CA-06): the projects of the companies in the
 * viewer's `projects.view` scope, newest first, each row with its company code, number, title, client, state and
 * deadline at once — one bounded branch per company merged by (`id`) in one statement, no total or count across
 * companies (PF-41; CS-07; CS-08) —, filtered by company and by state over IX-11's index (PF-42). The journey summary —
 * each row's stage, blocker marker and next action — is a deferred group over the page's projects (QB-01), QS-15's money
 * items omitted where the viewer lacks `finance.view` of the row's company (PJ-13). No stage filter: a project's stage
 * is derived, never stored (L-01), and no plan bounded by PF-42 exists for one (PFM-04; D-IA-19).
 */
final class ProjectList
{
    public const PAGE = 25;

    public const STATES = ['DRAFT', 'ACTIVE', 'CANCELLED', 'COMPLETED_NORMAL', 'COMPLETED_FORCED'];

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly ProjectJourneys $journeys,
    ) {}

    /**
     * @param  list<string>  $codes  the company filter, by code
     * @return array{companies: list<array{code: string, name: string}>, filter: array{perusahaan: list<string>, status: ?string}, rows: list<array{id: string, company: string, number: string, title: string, client: string, state: string, deadline: ?string, channel: string}>, more: ?string, can: array{create: bool}}
     */
    public function screen(int $viewerId, array $codes, ?string $state, ?string $after): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $companies = $this->companies($viewer);
        $chosen = array_values(array_filter($companies, fn (array $company): bool => $codes === [] || in_array($company['code'], $codes, true)));
        $state = in_array($state, self::STATES, true) ? $state : null;
        $page = $this->page($viewer, array_map(fn (array $company): int => $company['internal'], $chosen), $state, $after);

        return [
            'companies' => array_map(fn (array $company): array => ['code' => $company['code'], 'name' => $company['name']], $companies),
            'filter' => ['perusahaan' => array_map(fn (array $company): string => $company['code'], $codes === [] ? [] : $chosen), 'status' => $state],
            'rows' => $page['rows'],
            'more' => $page['more'],
            'can' => ['create' => array_filter($companies, fn (array $company): bool => $viewer->allows('projects.manage', $company['internal'])) !== []],
        ];
    }

    /**
     * One page after the cursor: a branch per company of page size + 1 rows, merged newest first (PF-41).
     *
     * @param  list<int>  $companyIds
     * @return array{rows: list<array{id: string, company: string, number: string, title: string, client: string, state: string, deadline: ?string, channel: string}>, more: ?string}
     */
    public function page(Authority $viewer, array $companyIds, ?string $state, ?string $after): array
    {
        if ($companyIds === []) {
            return ['rows' => [], 'more' => null];
        }

        $cursor = is_string($after) && preg_match(self::UUID, $after) === 1
            ? $viewer->scope->apply(DB::table('projects'), 'company_id')->where('public_id', strtolower($after))->value('id') : null;
        $before = is_numeric($cursor) ? (int) $cursor : null;
        $branch = fn (int $companyId): Builder => DB::table('projects')->select('id')->where('company_id', $companyId)
            ->when($state !== null, fn (Builder $where) => $where->where('state', $state))
            ->when($before !== null, fn (Builder $where) => $where->where('id', '<', $before))
            ->orderByDesc('id')->limit(self::PAGE + 1);
        $branches = $branch($companyIds[0]);

        foreach (array_slice($companyIds, 1) as $companyId) {
            $branches->unionAll($branch($companyId));
        }

        $found = $viewer->scope->apply(DB::table('projects as p'), 'p.company_id')
            ->joinSub($branches, 'b', 'b.id', '=', 'p.id')
            ->join('companies as c', 'c.id', '=', 'p.company_id')
            ->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->orderByDesc('p.id')
            ->limit(self::PAGE + 1)
            ->get(['p.id', 'p.public_id', 'p.project_number', 'p.title', 'p.state', 'p.deadline', 'p.channel', 'c.code as company', 'o.name as client'])
            ->all();
        $rows = array_map(fn (object $row): array => [
            'id' => Row::text($row, 'public_id'),
            'company' => Row::text($row, 'company'),
            'number' => Row::text($row, 'project_number'),
            'title' => Row::text($row, 'title'),
            'client' => Row::text($row, 'client'),
            'state' => Row::text($row, 'state'),
            'deadline' => Row::nullable($row, 'deadline'),
            'channel' => Row::text($row, 'channel'),
        ], array_slice($found, 0, self::PAGE));

        return ['rows' => $rows, 'more' => count($found) > self::PAGE && $rows !== [] ? $rows[count($rows) - 1]['id'] : null];
    }

    /**
     * The journey summary group of the page's projects (`DIR-077` CA-06): per row its current stage and its place among
     * the applicable stages, whether a blocker stands, and the next step — or a closed project's outcome.
     *
     * @param  list<string>  $publicIds
     * @return array<string, array<string, mixed>>
     */
    public function journeys(int $viewerId, array $publicIds): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $publicIds = array_values(array_filter(array_map('strtolower', $publicIds), fn (string $id): bool => preg_match(self::UUID, $id) === 1));

        if ($publicIds === []) {
            return [];
        }

        $projects = $viewer->scope->apply(DB::table('projects'), 'company_id')->whereIn('public_id', array_slice($publicIds, 0, self::PAGE * 4))
            ->get(['id', 'public_id', 'company_id'])->all();
        $ids = [];
        $money = [];
        $edit = [];
        $public = [];

        foreach ($projects as $project) {
            $id = (int) Row::text($project, 'id');
            $company = (int) Row::text($project, 'company_id');

            if (! $viewer->allows('projects.view', $company)) {
                continue;
            }

            $ids[] = $id;
            $public[$id] = Row::text($project, 'public_id');
            $money[$id] = $viewer->allows('finance.view', $company);
            $edit[$id] = $viewer->allows('projects.manage', $company) ? '/proyek/'.$public[$id].'/ubah' : null;
        }

        $summaries = [];

        foreach ($this->journeys->of($ids, $money, $edit) as $id => $journey) {
            $summaries[$public[$id]] = [
                'outcome' => $journey['outcome'],
                'stage' => $journey['current'],
                'position' => $journey['position'],
                'count' => count($journey['stages']),
                'blocked' => $journey['blocked'],
                'next' => $journey['next'],
            ];
        }

        return $summaries;
    }

    /**
     * The companies in the viewer's `projects.view` scope, by code (CS-07).
     *
     * @return list<array{code: string, name: string, internal: int}>
     */
    private function companies(Authority $viewer): array
    {
        $companies = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'code', 'legal_name']) as $company) {
            $id = (int) Row::text($company, 'id');

            if ($viewer->allows('projects.view', $id)) {
                $companies[] = ['code' => Row::text($company, 'code'), 'name' => Row::text($company, 'legal_name'), 'internal' => $id];
            }
        }

        return $companies;
    }
}
