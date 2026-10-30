<?php

namespace App\Modules\FIN\Queries;

use App\Modules\FIN\Data\InvoicePosition;
use App\Modules\FIN\Services\InvoiceSubjects;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-041 *Invoice* (ROUTE-041; NAV-041; SCR-38; J-FIN-01): a branch's invoices, newest first by keyset (D-PF-02) — each its number, project, client,
 * date, shown state and value —, the exact number lookup, "Buat Invoice" for a holder of `invoice.issue`. `finance.view` within its company, one company
 * at a time and no total across them (CS-07; CS-08); a number of another company's invoice is never shown (PJ-23). In at most four statements (QB-08).
 */
final class InvoiceList
{
    public const PAGE = 50;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /** @return array<string, mixed> */
    public function screen(int $viewerId, ?string $branch, ?string $after, ?string $number): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $branches = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']) as $company) {
            if ($viewer->allows('finance.view', (int) Row::text($company, 'id'))) {
                $branches[] = ['id' => Row::text($company, 'public_id'), 'code' => Row::text($company, 'code'), 'name' => Row::text($company, 'legal_name'), 'internal' => (int) Row::text($company, 'id')];
            }
        }

        $chosen = $branches[0] ?? null;

        foreach ($branches as $candidate) {
            if ($candidate['id'] === $branch) {
                $chosen = $candidate;
            }
        }

        $page = $chosen === null ? ['rows' => [], 'more' => null] : $this->page($viewer, $chosen['internal'], $after, $number);

        return [
            'branches' => array_map(fn (array $item): array => array_diff_key($item, ['internal' => true]), $branches),
            'branch' => $chosen['id'] ?? null,
            'number' => $number,
            'rows' => $page['rows'],
            'more' => $page['more'],
            'can' => ['create' => $chosen !== null && $viewer->allows('invoice.issue', $chosen['internal'])],
        ];
    }

    /**
     * One page of a company's invoices after the cursor, one more read to know whether more follow.
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string}
     */
    public function page(Authority $viewer, int $companyId, ?string $after, ?string $number): array
    {
        $cursor = is_string($after) && preg_match(self::UUID, $after) === 1 ? DB::table('invoices')->where('public_id', strtolower($after))->where('company_id', $companyId)->value('id') : null;
        $today = BusinessDate::today();
        $found = $viewer->scope->apply(DB::table('invoices as i'), 'i.company_id')
            ->where('i.company_id', $companyId)
            ->join('invoice_versions as v', 'v.id', '=', 'i.current_version_id')
            ->leftJoin('invoice_balances as b', 'b.invoice_id', '=', 'i.id')
            ->leftJoin('projects as p', 'p.id', '=', 'i.project_id')
            ->join('client_organizations as o', 'o.id', '=', 'i.client_organization_id')
            ->when(is_numeric($cursor), fn (Builder $where) => $where->where('i.id', '<', is_numeric($cursor) ? (int) $cursor : 0))
            ->when($number !== null && trim($number) !== '', fn (Builder $where) => $where->whereExists(fn (Builder $documents) => $documents->from('documents as d')->join('document_versions as n', 'n.document_id', '=', 'd.id')
                ->whereColumn('d.invoice_id', 'i.id')->whereIn('d.document_type_code', InvoiceSubjects::LAYOUTS)->where('n.formatted_number', mb_strtoupper(trim((string) $number)))))
            ->orderByDesc('i.id')
            ->limit(self::PAGE + 1)
            ->selectRaw("i.public_id, i.state, v.business_date, v.sales_value, v.gross_value, b.outstanding, b.written_off_amount, b.current_billing_act_id, b.current_due_date, p.public_id as project, p.project_number, o.name as client,
                exists (select 1 from receivable_dispute_holds h where h.invoice_id = i.id and h.cleared_at is null) as on_hold,
                (select n.formatted_number from documents d join document_versions n on n.document_id = d.id where d.invoice_id = i.id and d.document_type_code in ('DOC-04', 'DOC-06') and n.formatted_number is not null order by n.state = 'ISSUED' desc, d.id, n.id desc limit 1) as number")
            ->get()->all();
        $more = count($found) > self::PAGE;
        $rows = [];

        foreach (array_slice($found, 0, self::PAGE) as $row) {
            $rows[] = [
                'id' => Row::text($row, 'public_id'),
                'number' => Row::nullable($row, 'number'),
                'project' => Row::nullable($row, 'project'),
                'project_number' => Row::nullable($row, 'project_number'),
                'client' => Row::text($row, 'client'),
                'date' => Row::text($row, 'business_date'),
                'value' => Row::text($row, 'gross_value'),
                'state' => InvoicePosition::of(Row::text($row, 'state'), Row::nullable($row, 'current_billing_act_id') !== null, Row::nullable($row, 'current_due_date'), $today, Row::nullable($row, 'outstanding') ?? '0.00', Row::nullable($row, 'written_off_amount') ?? '0.00', ($row->on_hold ?? false) === true),
                'href' => '/keuangan/invoice/'.Row::text($row, 'public_id'),
            ];
        }

        return ['rows' => $rows, 'more' => $more && $rows !== [] ? $rows[count($rows) - 1]['id'] : null];
    }
}
