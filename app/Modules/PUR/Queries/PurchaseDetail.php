<?php

namespace App\Modules\PUR\Queries;

use App\Modules\CST\Services\CostPosting;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Services\PurchaseCorrections;
use App\Modules\PUR\Services\PurchaseRecords;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * PG-016 *Detail Pembelian*'s read (ROUTE-016; NAV-016; SCR-16; CALC-03): the lines with ordered, received and remaining quantities derived from the guard, never stored as a status; the charges with their allocation; receipts; disbursements to `finance.view`; documents; the timeline. A control is named only with its capability (H7-03), the server deciding either way (AZ-01).
 */
final class PurchaseDetail
{
    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly PurchaseForm $form,
    ) {}

    /** The purchase by public id, to `cost.view` of its company; otherwise unknown (AZ-11; CS-04). */
    public function find(int $viewerId, string $publicId): ?Purchase
    {
        $purchase = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $publicId) === 1 ? Purchase::query()->where('public_id', strtolower($publicId))->first() : null;

        return $purchase === null || ! $this->capabilities->for($viewerId)->allows('cost.view', $purchase->company_id) ? null : $purchase;
    }

    /** @return array<string, mixed> */
    public function screen(int $viewerId, Purchase $purchase): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $company = $purchase->company_id;
        $lines = $this->lines($purchase);
        $effect = PurchaseCorrections::hasFirstEffect($purchase->id);
        $open = $purchase->state === 'OPEN';
        $header = DB::table('companies')->where('id', $company)->first(['code', 'legal_name']);
        $project = $purchase->project_id === null ? null : DB::table('projects')->where('id', $purchase->project_id)->first(['public_id', 'project_number', 'title']);
        $supplier = $purchase->supplier_snapshot;
        $received = DB::table('purchase_lines as l')->join('purchase_line_balances as b', 'b.purchase_line_id', '=', 'l.id')->where('l.purchase_id', $purchase->id)->whereRaw('b.normal_received_net_qty_base + b.dropship_confirmed_net_qty_base + b.replacement_received_net_qty_base > 0')->exists();
        $performed = array_filter($lines, fn (array $line): bool => $line['performed'] !== null);
        $nonStock = array_filter($lines, fn (array $line): bool => $line['kind'] === 'NONSTOCK');
        $paid = PurchaseCorrections::paid($purchase->id);
        $cancellable = $open && ! $received && $performed === [] && ! ($nonStock !== [] && $paid);

        return [
            'purchase' => [
                'id' => $purchase->public_id, 'company_id' => self::companyKey($company), 'number' => $purchase->purchase_number, 'state' => $purchase->state, 'lock_version' => $purchase->lock_version, 'date' => $purchase->business_date->format('Y-m-d'),
                'company' => is_object($header) ? Row::text($header, 'code').' · '.Row::text($header, 'legal_name') : '', 'supplier' => is_string($supplier['name'] ?? null) ? $supplier['name'] : '', 'reference' => $purchase->supplier_reference,
                'project' => $project === null ? null : ['id' => Row::text($project, 'public_id'), 'number' => Row::text($project, 'project_number'), 'title' => Row::text($project, 'title')],
                'cancelled_reason' => $purchase->cancelled_reason, 'cancelled_at' => $purchase->cancelled_at?->toIso8601String(),
            ],
            'lines' => $lines,
            'charges' => $this->charges($purchase),
            'receipts' => $this->receipts($purchase),
            'disbursements' => $viewer->allows('finance.view', $company) ? $this->disbursements($purchase) : null,
            'documents' => $this->documents($purchase),
            'cascade' => $viewer->allows('purchase.correct_cost', $company) ? $this->cascade($purchase) : ['lots' => [], 'consumptions' => [], 'projects' => []],
            'timeline' => $this->timeline($purchase, $viewer),
            'bills' => $viewer->allows('purchase.manage', $company) ? $this->formBills($viewerId, $purchase) : [],
            'taxes' => $viewer->allows('purchase.manage', $company) ? ($this->form->screen($viewerId, null, self::companyKey($company), null, null)['taxes'] ?? []) : [],
            'first_effect' => $effect,
            'today' => BusinessDate::today(),
            'can' => [
                'amend' => $open && ! $effect && $viewer->allows('purchase.manage', $company),
                'supplement' => $purchase->state !== 'CANCELLED' && $viewer->allows('purchase.manage', $company),
                'charge' => $purchase->state !== 'CANCELLED' && $viewer->allows('purchase.manage', $company),
                'mark' => $open && $viewer->allows('purchase.manage', $company),
                'unmark' => $open && $viewer->allows('purchase.correct_cost', $company),
                'cancel' => $cancellable && $viewer->allows('purchase.cancel', $company),
                'close' => $open && $viewer->allows('purchase.close_remainder', $company),
                'correct' => $purchase->state !== 'CANCELLED' && $viewer->allows('purchase.correct_cost', $company),
                'issue' => $purchase->state !== 'CANCELLED' && $viewer->allows('documents.issue', $company),
                'evidence' => $viewer->allows('evidence.upload', $company),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lines(Purchase $purchase): array
    {
        $rows = DB::table('purchase_lines as l')->join('purchase_line_balances as b', 'b.purchase_line_id', '=', 'l.id')->join('products as p', 'p.id', '=', 'l.product_id')->leftJoin('units as u', 'u.id', '=', 'l.entered_unit_id')
            ->leftJoin('project_lines as d', 'd.id', '=', 'l.project_line_id')->where('l.purchase_id', $purchase->id)->orderBy('l.line_no')
            ->get(['l.id', 'l.line_no', 'l.kind', 'l.delivery_mode', 'l.description', 'l.entered_qty', 'l.ratio_to_base', 'l.unit_price', 'l.line_value', 'l.performed_at', 'l.product_id', 'p.public_id as product', 'p.name', 'p.sku', 'p.kind as product_kind', 'p.quantity_scale', 'u.code as unit', 'd.line_no as demand_no', 'd.id as demand_id',
                'b.ordered_qty_base', 'b.normal_received_net_qty_base', 'b.dropship_confirmed_net_qty_base', 'b.closed_qty_base', 'b.goods_value', 'b.noncreditable_tax_value',
                DB::raw("case when l.kind = 'GOODS' then greatest(b.ordered_qty_base - b.normal_received_net_qty_base - b.dropship_confirmed_net_qty_base - b.closed_qty_base, 0) else 0 end as remaining"),
                DB::raw('b.normal_received_net_qty_base + b.dropship_confirmed_net_qty_base as received')])->all();
        $taxes = DB::table('purchase_line_tax_components as c')->join('tax_types as t', 't.id', '=', 'c.tax_type_id')->whereIn('c.purchase_line_id', array_map(fn (object $row): int => (int) Row::text($row, 'id'), $rows))->orderBy('c.id')
            ->get(['c.purchase_line_id', 't.code', 't.name', 'c.treatment', 'c.rate_percent', 'c.amount'])->all();
        $base = DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')->where('pu.is_base', true)->whereIn('pu.product_id', array_map(fn (object $row): int => (int) Row::text($row, 'product_id'), $rows))->pluck('u.code', 'pu.product_id')->all();
        $products = $this->form->products(array_values(array_unique(array_map(fn (object $row): string => Row::text($row, 'product'), $rows))));
        $lines = [];

        foreach ($rows as $row) {
            $id = (int) Row::text($row, 'id');
            $unit = $base[(int) Row::text($row, 'product_id')] ?? null;
            $lines[] = [
                'handle' => RowHandle::of('purchase_lines', $id), 'no' => (int) Row::text($row, 'line_no'), 'kind' => Row::text($row, 'kind'), 'mode' => Row::text($row, 'delivery_mode'), 'description' => Row::text($row, 'description'),
                'product' => $products[Row::text($row, 'product')] ?? ['id' => Row::text($row, 'product'), 'name' => Row::text($row, 'name'), 'sku' => Row::nullable($row, 'sku'), 'kind' => Row::text($row, 'product_kind'), 'scale' => (int) Row::text($row, 'quantity_scale'), 'units' => []],
                'quantity' => Row::text($row, 'entered_qty'), 'unit' => Row::text($row, 'unit'), 'base_unit' => is_string($unit) ? $unit : Row::text($row, 'unit'), 'price' => Row::text($row, 'unit_price'), 'value' => Row::text($row, 'goods_value'), 'tax' => Row::text($row, 'noncreditable_tax_value'), 'demand' => Row::nullable($row, 'demand_id') === null ? null : RowHandle::of('project_lines', (int) Row::text($row, 'demand_id')), 'demand_no' => Row::nullable($row, 'demand_no'),
                'ordered' => Row::text($row, 'ordered_qty_base'), 'received' => Row::text($row, 'received'), 'closed' => Row::text($row, 'closed_qty_base'), 'remaining' => Row::text($row, 'remaining'), 'performed' => Row::nullable($row, 'performed_at'),
                'taxes' => array_values(array_map(fn (object $tax): array => ['code' => Row::text($tax, 'code'), 'name' => Row::text($tax, 'name'), 'treatment' => Row::text($tax, 'treatment'), 'rate' => Row::text($tax, 'rate_percent'), 'amount' => Row::text($tax, 'amount')], array_filter($taxes, fn (object $tax): bool => (int) Row::text($tax, 'purchase_line_id') === $id))),
            ];
        }

        return $lines;
    }

    /** @return list<array<string, mixed>> */
    private function charges(Purchase $purchase): array
    {
        $charges = [];
        $rows = DB::table('purchase_charges as c')->join('evidence_documents as e', 'e.id', '=', 'c.source_bill_evidence_id')->leftJoin('purchase_charge_balances as b', 'b.purchase_charge_id', '=', 'c.id')->where('c.purchase_id', $purchase->id)->orderBy('c.id')
            ->get(['c.id', 'c.classification', 'c.allocation_basis', 'c.amount', 'b.amount as current', 'b.allocated_amount', 'b.expensed_amount', 'e.public_id as bill', 'e.declared_issuer', 'e.issuer_reference'])->all();

        foreach ($rows as $row) {
            $id = (int) Row::text($row, 'id');
            $shares = DB::table('purchase_charge_share_balances as b')->join('purchase_lines as l', 'l.id', '=', 'b.purchase_line_id')->where('b.purchase_charge_id', $id)->orderBy('l.line_no')->get(['l.line_no', 'b.share_amount', 'b.posted_amount', 'b.respread_out_amount', 'b.expensed_out_amount']);
            $current = Row::nullable($row, 'current') ?? Row::text($row, 'amount');
            $charges[] = [
                'handle' => RowHandle::of('purchase_charges', $id), 'classification' => Row::text($row, 'classification'), 'basis' => Row::nullable($row, 'allocation_basis') === null ? null : PurchaseRecords::effectiveBasis($id, $purchase->id, Row::text($row, 'allocation_basis')), 'amount' => $current, 'allocated' => Row::nullable($row, 'allocated_amount') ?? '0', 'expensed' => Row::nullable($row, 'expensed_amount') ?? '0',
                'bill' => Row::text($row, 'bill'), 'issuer' => Row::text($row, 'declared_issuer'), 'reference' => Row::nullable($row, 'issuer_reference'),
                'shares' => array_values(array_map(fn (object $share): array => ['line' => (int) Row::text($share, 'line_no'), 'share' => Row::text($share, 'share_amount'), 'posted' => Row::text($share, 'posted_amount')], $shares->all())),
            ];
        }

        return $charges;
    }

    /** @return list<array{line: int, quantity: string, date: string, lot: ?string}> */
    private function receipts(Purchase $purchase): array
    {
        $receipts = [];

        foreach (DB::table('receiving_lines as r')->join('purchase_lines as l', 'l.id', '=', 'r.purchase_line_id')->join('inventory_lots as i', 'i.receiving_line_id', '=', 'r.id')->where('l.purchase_id', $purchase->id)->orderBy('r.id')->limit(100)->get(['l.line_no', 'i.acquired_qty_base', 'i.recorded_at']) as $row) {
            $receipts[] = ['line' => (int) Row::text($row, 'line_no'), 'quantity' => Row::text($row, 'acquired_qty_base'), 'date' => Row::text($row, 'recorded_at'), 'lot' => null];
        }

        return $receipts;
    }

    /** @return list<array{id: string, amount: string, date: string}> */
    private function disbursements(Purchase $purchase): array
    {
        $rows = [];

        foreach (DB::table('disbursements')->where('purchase_id', $purchase->id)->orderByDesc('id')->limit(50)->get(['public_id', 'amount', 'business_date']) as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'amount' => Row::text($row, 'amount'), 'date' => Row::text($row, 'business_date')];
        }

        return $rows;
    }

    /** @return list<array{id: string, type: string, state: ?string, number: ?string}> */
    private function documents(Purchase $purchase): array
    {
        $rows = [];

        foreach (DB::table('documents as d')->where('d.purchase_id', $purchase->id)->orderBy('d.id')
            ->selectRaw("d.public_id, d.document_type_code, (select v.state from document_versions v where v.document_id = d.id order by v.version_no desc limit 1) as state, (select v.formatted_number from document_versions v where v.document_id = d.id and v.state = 'ISSUED' limit 1) as number")->get() as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'type' => Row::text($row, 'document_type_code'), 'state' => Row::nullable($row, 'state'), 'number' => Row::nullable($row, 'number')];
        }

        return $rows;
    }

    /**
     * Criterion 14: every lot, consumption and project a cost correction would re-attribute, listed first (ADMIN_FLOW §11), from the holders CST's own cascade locks (§6.3 step 3): a return's or a replacement's lot among them. A row of one line names it, for a correction of that line; one reached through a dependent names none and is always listed.
     *
     * @return array{lots: list<array{code: string, line: ?int}>, consumptions: list<array{lot: string, line: ?int, kind: string, quantity: string, project: ?string}>, projects: list<array{number: string, line: ?int}>}
     */
    private function cascade(Purchase $purchase): array
    {
        $ids = [];
        $bearers = [];

        foreach (CostPosting::purchaseLocks($purchase->id)->ordered() as $row) {
            if ($row['table'] === 'lot_cost_balances') {
                $ids[] = (int) $row['key']['lot_id'];
            }

            if ($row['table'] === 'projects') {
                $bearers[] = (int) $row['key']['id'];
            }
        }

        $lines = [];
        $lots = [];

        foreach (DB::table('inventory_lots as i')->leftJoin('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->leftJoin('purchase_lines as l', fn (JoinClause $join) => $join->on('l.id', '=', 'r.purchase_line_id')->where('l.purchase_id', $purchase->id))->whereIn('i.id', $ids)->orderBy('i.id')->get(['i.id', 'i.lot_code', 'l.line_no']) as $lot) {
            $line = Row::nullable($lot, 'line_no');
            $lines[(int) Row::text($lot, 'id')] = [Row::text($lot, 'lot_code'), $line === null ? null : (int) $line];
            $lots[] = ['code' => Row::text($lot, 'lot_code'), 'line' => $line === null ? null : (int) $line];
        }

        $consumptions = [];
        $projects = [];

        foreach (DB::table('stock_consumptions as c')->leftJoin('projects as p', 'p.id', '=', 'c.bearer_project_id')->whereIn('c.lot_id', $ids)->orderBy('c.id')->get(['c.lot_id', 'c.kind', 'c.qty_base', 'p.project_number']) as $row) {
            [$code, $line] = $lines[(int) Row::text($row, 'lot_id')] ?? ['', null];
            $number = Row::nullable($row, 'project_number');
            $consumptions[] = ['lot' => $code, 'line' => $line, 'kind' => Row::text($row, 'kind'), 'quantity' => Row::text($row, 'qty_base'), 'project' => $number];

            if ($number !== null) {
                $projects[$number] = isset($projects[$number]) && $projects[$number]['line'] !== $line ? ['number' => $number, 'line' => null] : ['number' => $number, 'line' => $line];
            }
        }

        foreach (DB::table('projects')->whereIn('id', $bearers)->orderBy('project_number')->pluck('project_number') as $number) {
            if (is_string($number) && ! isset($projects[$number])) {
                $projects[$number] = ['number' => $number, 'line' => null];
            }
        }

        return ['lots' => $lots, 'consumptions' => $consumptions, 'projects' => array_values($projects)];
    }

    /** @return list<array{action: string, by: ?string, at: string, reason: ?string}> */
    private function timeline(Purchase $purchase, Authority $viewer): array
    {
        $rows = [];
        $query = DB::table('audit_events as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')->where('a.entity_type', 'purchase')->where('a.entity_public_id', $purchase->public_id)->orderByDesc('a.id')->limit(50);

        foreach ($viewer->scope->apply($query, 'a.company_id')->get(['a.action', 'a.reason', 'a.occurred_at', 'u.name']) as $row) {
            $rows[] = ['action' => Row::text($row, 'action'), 'by' => Row::nullable($row, 'name'), 'at' => Row::text($row, 'occurred_at'), 'reason' => Row::nullable($row, 'reason')];
        }

        return $rows;
    }

    private static function companyKey(int $companyId): ?string
    {
        $key = DB::table('companies')->where('id', $companyId)->value('public_id');

        return is_string($key) ? $key : null;
    }

    private function formBills(int $viewerId, Purchase $purchase): mixed
    {
        return $this->form->screen($viewerId, null, self::companyKey($purchase->company_id), null, null)['bills'] ?? [];
    }
}
