<?php

namespace App\Modules\PUR\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\PUR\Services\PurchaseTaxes;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * PG-017 *Buat Pembelian*'s reads (ROUTE-017; NAV-017; SCR-17; J-MD-05; SH-01): opened empty, prefilled with a project's shortage by its public id, with a product from QS-17's advice, or from PG-016's "Tambahkan Pembelian Susulan". The company of a project purchase is shown and never chosen (L-05; CS-03).
 */
final class PurchaseForm
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly SupplierPrices $prices,
    ) {}

    /** @return array<string, mixed> */
    public function screen(int $viewerId, ?string $project, ?string $company, ?string $product, ?string $from): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $origin = $from === null ? null : $this->origin($viewer, $from);
        $chosenProject = $this->project($viewer, $origin['project'] ?? $project);
        $companies = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->where('is_active', true)->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']) as $row) {
            if ($viewer->allows('purchase.manage', (int) Row::text($row, 'id'))) {
                $companies[] = ['id' => Row::text($row, 'public_id'), 'code' => Row::text($row, 'code'), 'name' => Row::text($row, 'legal_name'), 'internal' => (int) Row::text($row, 'id')];
            }
        }

        $chosen = $chosenProject['company'] ?? ($company ?? ($companies[0]['id'] ?? null));
        $internal = null;

        foreach ($companies as $candidate) {
            if ($candidate['id'] === $chosen) {
                $internal = $candidate['internal'];
            }
        }

        $products = $product === null ? [] : $this->products([$product]);

        return [
            'companies' => array_map(fn (array $item): array => array_diff_key($item, ['internal' => true]), $companies),
            'company' => $internal === null ? null : $chosen,
            'project' => $chosenProject === null ? null : array_diff_key($chosenProject, ['internal' => true]),
            'supplement' => $origin === null ? null : ['number' => $origin['number'], 'supplier' => $origin['supplier']],
            'prefill' => $chosenProject['shortage'] ?? [],
            'product' => $products[$product === null ? '' : strtolower($product)] ?? null,
            'taxes' => $internal === null ? [] : $this->taxes($internal),
            'bills' => $internal === null ? [] : $this->bills($internal),
            'today' => BusinessDate::today(),
            'can' => ['new_supplier' => $viewer->allows('parties.manage'), 'bill' => $internal !== null && $viewer->allows('evidence.upload', $internal)],
        ];
    }

    /**
     * SH-01: the previous actual price and date of the supplier and each product, per company and only with `cost.view`; no score, rank or choice.
     *
     * @param  list<string>  $products  public ids
     * @return array<string, array{price: string, date: string, purchase: string}>
     */
    public function previous(int $viewerId, ?string $company, ?string $supplier, array $products): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $companyId = $company !== null && preg_match(self::UUID, $company) === 1 ? DB::table('companies')->where('public_id', strtolower($company))->value('id') : null;
        $supplierId = $supplier !== null && preg_match(self::UUID, $supplier) === 1 ? DB::table('suppliers')->where('public_id', strtolower($supplier))->value('id') : null;

        return is_numeric($companyId) && is_numeric($supplierId) ? $this->prices->previous($viewer, (int) $companyId, (int) $supplierId, $products) : [];
    }

    /**
     * Active products by public id, with their kind, scale and units.
     *
     * @param  list<string>  $publicIds
     * @return array<string, array{id: string, name: string, sku: ?string, kind: string, scale: int, units: list<array{code: string, name: string, ratio: int}>}>
     */
    public function products(array $publicIds): array
    {
        $publicIds = array_values(array_map('strtolower', array_filter($publicIds, fn (string $id): bool => preg_match(self::UUID, $id) === 1)));

        if ($publicIds === []) {
            return [];
        }

        $rows = DB::table('products')->whereIn('public_id', $publicIds)->where('is_active', true)->get(['id', 'public_id', 'name', 'sku', 'kind', 'quantity_scale'])->all();
        $units = DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')->whereIn('pu.product_id', array_map(fn (object $row): int => (int) Row::text($row, 'id'), $rows))->where('pu.is_active', true)
            ->orderByDesc('pu.is_base')->orderBy('u.code')->get(['pu.product_id', 'u.code', 'u.name', 'pu.ratio_to_base'])->all();
        $products = [];

        foreach ($rows as $row) {
            $id = (int) Row::text($row, 'id');
            $products[Row::text($row, 'public_id')] = ['id' => Row::text($row, 'public_id'), 'name' => Row::text($row, 'name'), 'sku' => Row::nullable($row, 'sku'), 'kind' => Row::text($row, 'kind'), 'scale' => (int) Row::text($row, 'quantity_scale'),
                'units' => array_values(array_map(fn (object $unit): array => ['code' => Row::text($unit, 'code'), 'name' => Row::text($unit, 'name'), 'ratio' => (int) Row::text($unit, 'ratio_to_base')], array_filter($units, fn (object $unit): bool => (int) Row::text($unit, 'product_id') === $id)))];
        }

        return $products;
    }

    /** @return array{project: ?string, number: string, supplier: array{id: string, name: string}}|null */
    private function origin(Authority $viewer, string $publicId): ?array
    {
        $row = preg_match(self::UUID, $publicId) === 1 ? DB::table('purchases as x')->join('suppliers as s', 's.id', '=', 'x.supplier_id')->leftJoin('projects as p', 'p.id', '=', 'x.project_id')->where('x.public_id', strtolower($publicId))
            ->first(['x.company_id', 'x.purchase_number', 's.public_id as supplier', 's.name', 'p.public_id as project']) : null;

        if ($row === null || ! $viewer->allows('purchase.manage', (int) Row::text($row, 'company_id'))) {
            return null;
        }

        return ['project' => Row::nullable($row, 'project'), 'number' => Row::text($row, 'purchase_number'), 'supplier' => ['id' => Row::text($row, 'supplier'), 'name' => Row::text($row, 'name')]];
    }

    /** @return array<string, mixed>|null */
    private function project(Authority $viewer, ?string $publicId): ?array
    {
        $row = $publicId !== null && preg_match(self::UUID, $publicId) === 1 ? DB::table('projects as p')->join('companies as c', 'c.id', '=', 'p.company_id')->where('p.public_id', strtolower($publicId))
            ->first(['p.id', 'p.public_id', 'p.project_number', 'p.title', 'p.company_id', 'p.state', 'c.public_id as company', 'c.code', 'c.legal_name']) : null;

        if ($row === null || ! $viewer->allows('purchase.manage', (int) Row::text($row, 'company_id'))) {
            return null;
        }

        $id = (int) Row::text($row, 'id');
        $lines = DB::table('project_lines as l')->join('confirmation_line_pins as p', 'p.id', '=', 'l.current_pin_id')->join('project_line_balances as b', 'b.project_line_id', '=', 'l.id')->join('products as r', 'r.id', '=', 'l.product_id')
            ->where('l.project_id', $id)->where('l.fulfillment_mode', '<>', 'SERVICE')->orderBy('l.line_no')
            ->get(['l.id', 'l.line_no', 'l.fulfillment_mode', 'p.description', 'r.public_id as product', 'r.name',
                DB::raw("case when l.fulfillment_mode = 'WAREHOUSE' then greatest(b.demand_qty_base - b.dispatched_net_qty_base - b.cancelled_qty_base - b.reserved_qty_base, 0) else greatest(b.demand_qty_base - b.dropship_confirmed_net_qty_base - b.cancelled_qty_base, 0) end as shortage")])->all();
        $products = $this->products(array_values(array_unique(array_map(fn (object $line): string => Row::text($line, 'product'), $lines))));
        $demand = [];
        $shortage = [];

        foreach ($lines as $line) {
            $handle = RowHandle::of('project_lines', (int) Row::text($line, 'id'));
            $label = Row::nullable($line, 'description') ?? Row::text($line, 'name');
            $demand[] = ['handle' => $handle, 'label' => $label, 'product' => Row::text($line, 'product'), 'no' => (int) Row::text($line, 'line_no'), 'shortage' => Row::text($line, 'shortage')];
            $product = $products[Row::text($line, 'product')] ?? null;

            if ($product !== null && preg_match('/^0+(\.0+)?$/', Row::text($line, 'shortage')) !== 1) {
                $shortage[] = ['demand' => $handle, 'product' => $product, 'quantity' => Row::text($line, 'shortage'), 'mode' => Row::text($line, 'fulfillment_mode'), 'label' => $label];
            }
        }

        return ['id' => Row::text($row, 'public_id'), 'number' => Row::text($row, 'project_number'), 'title' => Row::text($row, 'title'), 'state' => Row::text($row, 'state'), 'company' => Row::text($row, 'company'), 'company_code' => Row::text($row, 'code'), 'company_name' => Row::text($row, 'legal_name'), 'demand' => $demand, 'shortage' => $shortage];
    }

    /**
     * The tax types a purchase line may state in this company today, each with its treatment and current rate.
     *
     * @return list<array{code: string, name: string, treatment: string, rate: string}>
     */
    private function taxes(int $companyId): array
    {
        $today = BusinessDate::today();
        $taxes = [];

        foreach (DB::table('tax_treatment_rules as r')->join('tax_types as t', 't.id', '=', 'r.tax_type_id')->where('r.transaction_context', PurchaseTaxes::CONTEXT)->whereRaw('r.effective @> ?::date', [$today])->whereRaw('(r.company_id is null or r.company_id = ?)', [$companyId])
            ->orderByRaw('r.company_id is null')->orderBy('t.code')->get(['t.id', 't.code', 't.name', 'r.treatment']) as $row) {
            $code = Row::text($row, 'code');
            $rate = DB::table('tax_rate_versions')->where('tax_type_id', (int) Row::text($row, 'id'))->whereRaw('effective @> ?::date', [$today])->value('rate_percent');
            $taxes[$code] ??= ['code' => $code, 'name' => Row::text($row, 'name'), 'treatment' => Row::text($row, 'treatment'), 'rate' => is_string($rate) ? $rate : '0'];
        }

        return array_values($taxes);
    }

    /**
     * The company's supplier bills with what is left to cite (C-20; PJ-08).
     *
     * @return list<array{id: string, issuer: string, reference: ?string, date: string, total: string, left: string}>
     */
    private function bills(int $companyId): array
    {
        $bills = [];

        foreach (DB::table('evidence_documents as e')->leftJoin('evidence_balances as b', 'b.evidence_document_id', '=', 'e.id')->where('e.company_id', $companyId)->where('e.evidence_type', 'SUPPLIER_BILL')->orderByDesc('e.id')->limit(100)
            ->selectRaw('e.public_id, e.declared_issuer, e.issuer_reference, e.document_date, coalesce(b.proven_amount, 0) as total, coalesce(b.proven_amount, 0) - coalesce(b.cited_amount, 0) as remaining')->get() as $row) {
            $bills[] = ['id' => Row::text($row, 'public_id'), 'issuer' => Row::text($row, 'declared_issuer'), 'reference' => Row::nullable($row, 'issuer_reference'), 'date' => Row::text($row, 'document_date'), 'total' => Row::text($row, 'total'), 'left' => Row::text($row, 'remaining')];
        }

        return $bills;
    }
}
