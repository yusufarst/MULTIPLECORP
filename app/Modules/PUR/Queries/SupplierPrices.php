<?php

namespace App\Modules\PUR\Queries;

use App\Modules\IAM\Data\Authority;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * A supplier's purchase-price history (PERMISSIONS_MATRIX PJ-09; BR-ACC-02; REF-011; RD-05), PUR's query class that
 * PG-055 reads (ARCHITECTURE §3): derived from the purchases of the companies in the viewer's scope only — the scope
 * inside the one statement, so nothing outside it is read, ranked or cut (CS-07) —, to a holder of `cost.view`, each
 * company's own rows apart, never pooled, never stored on the supplier (DATABASE §4.3). A row is a product's
 * previous actual price — per its entered unit, excluding creditable tax — and the date of the purchase it came from,
 * the latest per company and product; a cancelled purchase is no price.
 */
final class SupplierPrices
{
    public const CAPABILITY = 'cost.view';

    /** The rows shown per company, the most recently bought products first. */
    public const ROWS = 20;

    /**
     * SH-01: the previous actual price and date of the supplier and each product in one company (BR-PUR-01; PJ-09), for PG-017's line, in one query; a project purchase's own company only, never pooled.
     *
     * @param  list<string>  $products  public ids
     * @return array<string, array{price: string, date: string, unit: string, purchase: string}>
     */
    public function previous(Authority $viewer, int $companyId, int $supplierId, array $products): array
    {
        if ($products === [] || ! $viewer->allows(self::CAPABILITY, $companyId)) {
            return [];
        }

        $previous = [];

        foreach ($viewer->scope->apply(DB::table('purchase_lines as pl'), 'pl.company_id')->join('purchases as pu', 'pu.id', '=', 'pl.purchase_id')->join('products as p', 'p.id', '=', 'pl.product_id')->join('units as u', 'u.id', '=', 'pl.entered_unit_id')
            ->where('pl.company_id', $companyId)->where('pu.supplier_id', $supplierId)->whereNull('pu.cancelled_at')->whereIn('p.public_id', array_map('strtolower', $products))
            ->orderBy('p.public_id')->orderByDesc('pu.business_date')->orderByDesc('pl.id')
            ->selectRaw('distinct on (p.public_id) p.public_id, pl.unit_price, pu.business_date, u.code, pu.purchase_number')->get() as $row) {
            $previous[Row::text($row, 'public_id')] = ['price' => Row::text($row, 'unit_price'), 'date' => Row::text($row, 'business_date'), 'unit' => Row::text($row, 'code'), 'purchase' => Row::text($row, 'purchase_number')];
        }

        return $previous;
    }

    /** @return list<array{company: string, product: string, unit: string, unit_price: string, date: string, purchase: string}> */
    public function history(Authority $viewer, int $supplierId): array
    {
        if (! $viewer->allows(self::CAPABILITY) || $viewer->scope->isEmpty()) {
            return [];
        }

        $latest = DB::table('purchase_lines as pl')
            ->join('purchases as pu', 'pu.id', '=', 'pl.purchase_id')
            ->where('pu.supplier_id', $supplierId)
            ->whereNull('pu.cancelled_at')
            ->select(['pl.company_id', 'pl.product_id', 'pl.entered_unit_id', 'pl.unit_price', 'pu.business_date', 'pu.purchase_number'])
            ->selectRaw('row_number() over (partition by pl.company_id, pl.product_id order by pu.business_date desc, pl.id desc) as recency');
        $viewer->scope->apply($latest, 'pu.company_id');

        $ranked = DB::query()->fromSub($latest, 'latest')
            ->where('latest.recency', 1)
            ->select(['latest.*'])
            ->selectRaw('row_number() over (partition by latest.company_id order by latest.business_date desc, latest.product_id) as place');

        $rows = DB::query()->fromSub($ranked, 'ranked')
            ->join('companies as c', 'c.id', '=', 'ranked.company_id')
            ->join('products as p', 'p.id', '=', 'ranked.product_id')
            ->join('units as u', 'u.id', '=', 'ranked.entered_unit_id')
            ->where('ranked.place', '<=', self::ROWS)
            ->orderBy('c.code')
            ->orderByDesc('ranked.business_date')
            ->orderBy('p.name')
            ->get(['c.code as company', 'p.name as product', 'u.code as unit', 'ranked.unit_price', 'ranked.business_date', 'ranked.purchase_number'])
            ->all();

        return array_values(array_map(fn (object $row): array => [
            'company' => Row::text($row, 'company'),
            'product' => Row::text($row, 'product'),
            'unit' => Row::text($row, 'unit'),
            'unit_price' => Row::text($row, 'unit_price'),
            'date' => Row::text($row, 'business_date'),
            'purchase' => Row::text($row, 'purchase_number'),
        ], $rows));
    }
}
