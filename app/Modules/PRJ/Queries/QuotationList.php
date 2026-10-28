<?php

namespace App\Modules\PRJ\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\PRJ\Services\QuotationRecords;
use App\Support\BusinessDate;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-037 *Daftar Penawaran*'s reads (ROUTE-037; SCR-12; NAV-037; J-QUO-01; CS-07; CS-08): the quotation revisions of
 * one company at a time — a branch of the viewer's scope with `projects.view` —, newest first with a cursor, each with
 * its project, revision, state — *Kedaluwarsa* derived (L-25) —, validity and DOC-01 number; the value only with
 * `finance.view` (PJ-15), never a total across companies.
 */
final class QuotationList
{
    public const PAGE = 50;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /** @return array<string, mixed> */
    public function screen(int $viewerId, ?string $branch, ?string $after): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $branches = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']) as $company) {
            if ($viewer->allows('projects.view', (int) Row::text($company, 'id'))) {
                $branches[] = ['id' => Row::text($company, 'public_id'), 'code' => Row::text($company, 'code'), 'name' => Row::text($company, 'legal_name'), 'internal' => (int) Row::text($company, 'id')];
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
            'money' => $chosen !== null && $viewer->allows('finance.view', $chosen['internal']),
            'rows' => $page['rows'],
            'more' => $page['more'],
        ];
    }

    /**
     * One page of a company's revisions after the cursor, newest first, one more read to know whether more follow.
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string}
     */
    public function page(Authority $viewer, int $companyId, ?string $after): array
    {
        $money = $viewer->allows('finance.view', $companyId);
        $today = BusinessDate::today();
        $cursor = is_string($after) && preg_match(self::UUID, $after) === 1
            ? DB::table('quotation_revisions')->where('public_id', strtolower($after))->where('company_id', $companyId)->value('id') : null;
        $found = $viewer->scope->apply(DB::table('quotation_revisions as r'), 'r.company_id')
            ->where('r.company_id', $companyId)
            ->join('projects as p', 'p.id', '=', 'r.project_id')
            ->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->leftJoin('document_versions as v', 'v.quotation_revision_id', '=', 'r.id')
            ->when(is_numeric($cursor), fn (Builder $where) => $where->where('r.id', '<', is_numeric($cursor) ? (int) $cursor : 0))
            ->when($money, fn (Builder $select) => $select->selectSub(DB::table('quotation_revision_lines as l')->whereColumn('l.quotation_revision_id', 'r.id')
                ->selectRaw('coalesce(sum(l.sales_value), 0)'), 'value'))
            ->orderByDesc('r.id')
            ->limit(self::PAGE + 1)
            ->addSelect(['r.public_id', 'r.revision_no', 'r.state', 'r.valid_until', 'p.public_id as project', 'p.project_number', 'p.title', 'o.name as client', 'v.formatted_number'])
            ->get()->all();
        $more = count($found) > self::PAGE;
        $rows = [];

        foreach (array_slice($found, 0, self::PAGE) as $row) {
            $rows[] = [
                'id' => Row::text($row, 'public_id'),
                'project' => Row::text($row, 'project'),
                'project_number' => Row::text($row, 'project_number'),
                'title' => Row::text($row, 'title'),
                'client' => Row::text($row, 'client'),
                'no' => (int) Row::text($row, 'revision_no'),
                'state' => QuotationRecords::shown(Row::text($row, 'state'), Row::nullable($row, 'valid_until'), $today),
                'valid_until' => Row::nullable($row, 'valid_until'),
                'number' => Row::nullable($row, 'formatted_number'),
                ...($money ? ['value' => Row::text($row, 'value')] : []),
            ];
        }

        return ['rows' => $rows, 'more' => $more && $rows !== [] ? $rows[count($rows) - 1]['id'] : null];
    }
}
