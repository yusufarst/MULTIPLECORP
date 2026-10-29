<?php

namespace App\Modules\ADM\Queries;

use App\Modules\ADM\Services\Readiness;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * PG-039 *Dokumen Administrasi*'s reads (ROUTE-039; SCR-37; NAV-039; J-ADM-01; CS-07; CS-08; RD-03): the unmet items of
 * the open projects of one company at a time — a branch of the viewer's scope with `projects.view`, the scope applied
 * inside the query before ordering and paging —, each with its project, client and company, its derived status and
 * what it takes; filters, sort and cursor from the address (IP-18). No count is shown, so none can include an item the
 * viewer cannot see. "Tandai Tidak Berlaku" is offered on an eligible item to `requirements.waive`; waiver eligibility
 * itself only to the Owner (row 96; OD-05).
 */
final class AdministrativeDocuments
{
    public const PAGE = 50;

    public const TABS = ['wajib', 'semua'];

    /** The address's status filter: *Belum Ada* or *Disiapkan* (IP-18). */
    public const STATUSES = ['belum-ada' => Readiness::MISSING, 'disiapkan' => Readiness::PREPARED];

    public const SORTS = ['terbaru', 'terlama'];

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /**
     * @param  array{cabang: ?string, tab: ?string, status: ?string, urut: ?string, setelah: ?string}  $query
     * @return array<string, mixed>
     */
    public function screen(int $viewerId, array $query): array
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
            if ($candidate['id'] === $query['cabang']) {
                $chosen = $candidate;
            }
        }

        $filters = [
            'tab' => in_array($query['tab'], self::TABS, true) ? $query['tab'] : 'wajib',
            'status' => is_string($query['status']) && isset(self::STATUSES[$query['status']]) ? $query['status'] : null,
            'urut' => in_array($query['urut'], self::SORTS, true) ? $query['urut'] : 'terbaru',
        ];
        $page = $chosen === null ? ['rows' => [], 'more' => null] : $this->page($viewer, $chosen, $filters, $query['setelah']);

        return [
            'branches' => array_map(fn (array $item): array => array_diff_key($item, ['internal' => true]), $branches),
            'branch' => $chosen['id'] ?? null,
            'filters' => $filters,
            'rows' => $page['rows'],
            'more' => $page['more'],
            'can' => [
                'waive' => $chosen !== null && $viewer->allows('requirements.waive', $chosen['internal']),
                'relax' => $chosen !== null && $viewer->allows('requirements.relax', $chosen['internal']),
                'owner' => $chosen !== null && $viewer->allows('requirements.set_waiver_eligibility', $chosen['internal']),
            ],
        ];
    }

    /**
     * One page of the company's unmet items after the cursor — the project's public identifier and the item's handle —,
     * one more read to know whether more follow.
     *
     * @param  array{id: string, code: string, name: string, internal: int}  $company
     * @param  array{tab: string, status: ?string, urut: string}  $filters
     * @return array{rows: list<array<string, mixed>>, more: ?string}
     */
    private function page(Authority $viewer, array $company, array $filters, ?string $after): array
    {
        $companyId = $company['internal'];
        $newest = $filters['urut'] === 'terbaru';
        $where = ["p.state in ('DRAFT', 'ACTIVE')", Readiness::UNMET];
        $bindings = [$companyId];

        if ($filters['tab'] === 'wajib') {
            $where[] = 's.is_required';
        }

        if ($filters['status'] !== null) {
            $where[] = self::STATUSES[$filters['status']] === Readiness::PREPARED ? 's.drafted' : 'not s.drafted';
        }

        $cursor = $this->cursor($companyId, $after);

        if ($cursor !== null) {
            $where[] = '(p.id '.($newest ? '<' : '>').' ? or (p.id = ? and s.id > ?))';
            array_push($bindings, $cursor[0], $cursor[0], $cursor[1]);
        }

        // The scope is the branch's company, which the viewer's scope and `projects.view` admitted above (CS-07).
        $sql = 'select s.*, p.public_id as project_public, p.project_number, p.title, o.name as client from ('.Readiness::select()
            ." where r.state = 'ACTIVE' and r.company_id = ?) s join projects p on p.id = s.project_id"
            .' join client_organizations o on o.id = p.client_organization_id where '.implode(' and ', $where)
            .' order by p.id '.($newest ? 'desc' : 'asc').', s.id limit '.(self::PAGE + 1);
        $found = DB::select($sql, $bindings);
        $waive = $viewer->allows('requirements.waive', $companyId);
        $owner = $viewer->allows('requirements.set_waiver_eligibility', $companyId);
        $rows = [];

        foreach (array_slice($found, 0, self::PAGE) as $row) {
            if (! is_object($row)) {
                continue;
            }

            $item = Readiness::item($row);
            $rows[] = [
                'handle' => RowHandle::of('admin_requirements', $item['id']),
                'project' => ['id' => Row::text($row, 'project_public'), 'number' => Row::text($row, 'project_number'), 'title' => Row::text($row, 'title'), 'client' => Row::text($row, 'client')],
                'company' => $company['code'],
                'kind' => $item['kind'],
                'document_type' => $item['document_type'],
                'evidence_type' => $item['evidence_type'],
                'description' => $item['description'],
                'required' => $item['required'],
                'mode' => $item['mode'],
                'client_original' => $item['client_original'],
                'default_invoice' => $item['default_invoice'],
                'lock_version' => $item['lock_version'],
                'status' => $item['status'],
                'may_waive' => $waive && $item['waiver_eligible'] && ! $item['default_invoice'],
                ...($owner ? ['waiver_eligible' => $item['waiver_eligible']] : []),
            ];
        }

        $last = $rows === [] ? null : $rows[count($rows) - 1];

        return ['rows' => $rows, 'more' => count($found) > self::PAGE && $last !== null ? "{$last['project']['id']}.{$last['handle']}" : null];
    }

    /**
     * The cursor's project and item, resolved inside the company among the project's own items.
     *
     * @return ?array{0: int, 1: int}
     */
    private function cursor(int $companyId, ?string $after): ?array
    {
        $parts = is_string($after) ? explode('.', $after, 2) : [];

        if (count($parts) !== 2 || preg_match(self::UUID, $parts[0]) !== 1 || ! RowHandle::valid($parts[1])) {
            return null;
        }

        $project = DB::table('projects')->where('public_id', strtolower($parts[0]))->where('company_id', $companyId)->value('id');

        if (! is_numeric($project)) {
            return null;
        }

        foreach (DB::table('admin_requirements')->where('project_id', (int) $project)->pluck('id') as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('admin_requirements', (int) $id), $parts[1])) {
                return [(int) $project, (int) $id];
            }
        }

        return null;
    }
}
