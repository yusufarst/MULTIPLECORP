<?php

namespace App\Modules\PUR\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-015 *Pembelian*'s read (ROUTE-015; NAV-015; SCR-15; PF-13; CS-07; CS-08): purchases one company at a time, the scope inside the query, by business date, with no total across companies; "Muat Lagi" by cursor (IP-18).
 */
final class PurchaseList
{
    public const PAGE = 50;

    public const STATES = ['OPEN', 'CLOSED', 'CANCELLED'];

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /** @return array<string, mixed> */
    public function screen(int $viewerId, ?string $branch, ?string $state, ?string $after): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $branches = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']) as $company) {
            if ($viewer->allows('cost.view', (int) Row::text($company, 'id'))) {
                $branches[] = ['id' => Row::text($company, 'public_id'), 'code' => Row::text($company, 'code'), 'name' => Row::text($company, 'legal_name'), 'internal' => (int) Row::text($company, 'id')];
            }
        }

        $chosen = $branches[0] ?? null;

        foreach ($branches as $candidate) {
            if ($candidate['id'] === $branch) {
                $chosen = $candidate;
            }
        }

        $state = in_array($state, self::STATES, true) ? $state : null;
        $page = $chosen === null ? ['rows' => [], 'more' => null] : $this->page($viewer, $chosen['internal'], $chosen['code'], $state, $after);

        return [
            'branches' => array_map(fn (array $item): array => array_diff_key($item, ['internal' => true]), $branches),
            'branch' => $chosen['id'] ?? null,
            'state' => $state,
            'rows' => $page['rows'],
            'more' => $page['more'],
            'can' => ['create' => $viewer->allows('purchase.manage')],
        ];
    }

    /**
     * One page after the cursor, one more read to know whether more follow.
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string}
     */
    private function page(Authority $viewer, int $companyId, string $companyCode, ?string $state, ?string $after): array
    {
        $cursor = is_string($after) && preg_match(self::UUID, $after) === 1 ? DB::table('purchases')->where('public_id', strtolower($after))->where('company_id', $companyId)->first(['id', 'business_date']) : null;
        $found = $viewer->scope->apply(DB::table('purchases as x'), 'x.company_id')
            ->where('x.company_id', $companyId)
            ->leftJoin('projects as p', 'p.id', '=', 'x.project_id')
            ->when($state !== null, fn (Builder $where) => $where->where('x.state', $state))
            ->when($cursor !== null, fn (Builder $where) => $where->whereRaw('(x.business_date, x.id) < (?::date, ?)', [is_object($cursor) ? Row::text($cursor, 'business_date') : '', is_object($cursor) ? (int) Row::text($cursor, 'id') : 0]))
            ->orderByDesc('x.business_date')->orderByDesc('x.id')
            ->limit(self::PAGE + 1)
            ->get(['x.public_id', 'x.purchase_number', 'x.supplier_snapshot', 'x.state', 'x.business_date', 'p.public_id as project', 'p.project_number'])->all();
        $more = count($found) > self::PAGE;
        $rows = [];

        foreach (array_slice($found, 0, self::PAGE) as $row) {
            $supplier = json_decode(Row::text($row, 'supplier_snapshot'), true);
            $rows[] = [
                'id' => Row::text($row, 'public_id'),
                'company' => $companyCode,
                'number' => Row::text($row, 'purchase_number'),
                'supplier' => is_array($supplier) && is_string($supplier['name'] ?? null) ? $supplier['name'] : '',
                'project' => Row::nullable($row, 'project_number'),
                'state' => Row::text($row, 'state'),
                'date' => Row::text($row, 'business_date'),
                'href' => '/pembelian/'.Row::text($row, 'public_id'),
            ];
        }

        return ['rows' => $rows, 'more' => $more && $rows !== [] ? $rows[count($rows) - 1]['id'] : null];
    }
}
