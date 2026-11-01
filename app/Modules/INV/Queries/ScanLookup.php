<?php

namespace App\Modules\INV\Queries;

use App\Modules\IAM\Data\Authority;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * `INV ScanLookup` (ROUTE-282; INT-006; INT-282; SF-SCAN; IP-15; QB-06): the exact lookup of a scanned code, the pool's S1 read (CS-05) — a product by its barcode or SKU, a lot, a serial. Nothing is created (never auto-created); an ambiguous code chooses nothing and lists its candidates (MSG-37); an unknown one offers "Daftarkan Barcode" only with `products.edit` (MSG-25).
 */
final class ScanLookup
{
    /** The candidates listed for an ambiguous code at most (UXS-34). */
    public const ROWS = 10;

    /** @return array{kind: string, code: string, matches: list<array<string, mixed>>, register: bool} */
    public function find(Authority $viewer, string $code): array
    {
        $term = trim($code);
        $key = mb_strtoupper((string) preg_replace('/\s+/u', '', $term));
        $matches = [];

        foreach (DB::table('products as p')->leftJoin('product_barcodes as b', fn (JoinClause $join) => $join->on('b.product_id', '=', 'p.id')->where('b.is_active', true)->where('b.code_key', $key))
            ->where('p.is_active', true)->where(fn (Builder $where) => $where->whereRaw('upper(p.sku) = ?', [$key])->orWhereNotNull('b.id'))->orderBy('p.name')->limit(self::ROWS + 1)->distinct()->get(['p.public_id', 'p.name', 'p.sku', 'p.is_serialized']) as $row) {
            $matches[] = ['kind' => 'product', 'product' => Row::text($row, 'public_id'), 'name' => Row::text($row, 'name'), 'sku' => Row::nullable($row, 'sku'), 'serialized' => (bool) $row->is_serialized];
        }

        foreach (DB::table('serial_units as s')->join('products as p', 'p.id', '=', 's.product_id')->where('s.serial_key', mb_strtolower((string) preg_replace('/\s+/u', '', $term)))->orderBy('p.name')->limit(self::ROWS + 1)->get(['s.serial_number', 's.state', 'p.public_id', 'p.name']) as $row) {
            $matches[] = ['kind' => 'serial', 'product' => Row::text($row, 'public_id'), 'name' => Row::text($row, 'name'), 'serial' => Row::text($row, 'serial_number'), 'state' => Row::text($row, 'state')];
        }

        foreach (DB::table('inventory_lots as l')->join('products as p', 'p.id', '=', 'l.product_id')->where('l.lot_code', $key)->limit(self::ROWS + 1)->get(['l.lot_code', 'p.public_id', 'p.name']) as $row) {
            $matches[] = ['kind' => 'lot', 'product' => Row::text($row, 'public_id'), 'name' => Row::text($row, 'name'), 'lot' => Row::text($row, 'lot_code')];
        }

        $matches = array_slice($matches, 0, self::ROWS);

        return ['kind' => match (count($matches)) {
            0 => 'unknown',
            1 => 'match',
            default => 'ambiguous',
        }, 'code' => $term, 'matches' => $matches, 'register' => $matches === [] && $viewer->allows('products.edit')];
    }
}
