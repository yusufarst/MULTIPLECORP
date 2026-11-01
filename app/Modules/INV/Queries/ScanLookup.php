<?php

namespace App\Modules\INV\Queries;

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * `INV ScanLookup` (ROUTE-282; INT-006; INT-282; SF-SCAN; IP-15; QB-06): the exact lookup of a scanned code, the pool's S1 read (CS-05) — a product by its barcode or SKU, a lot, a serial. Nothing is created (never auto-created); an ambiguous code chooses nothing and lists its candidates (MSG-37); an unknown one offers "Daftarkan Barcode" only with `products.edit` (MSG-25).
 */
final class ScanLookup
{
    /** The candidates listed for an ambiguous code at most (UXS-34). */
    public const ROWS = 10;

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /** @return array{kind: string, code: string, matches: list<array<string, mixed>>, register: bool} */
    public function find(int $viewerId, string $code): array
    {
        $term = trim($code);
        $key = mb_strtoupper((string) preg_replace('/\s+/u', '', $term));
        $matches = [];

        $products = DB::table('products as p')->where('p.is_active', true)->whereRaw('lower(p.sku) = ?', [mb_strtolower($key)])->select(['p.public_id', 'p.name', 'p.sku', 'p.is_serialized'])
            ->union(DB::table('product_barcodes as b')->join('products as p', 'p.id', '=', 'b.product_id')->where('b.is_active', true)->where('b.code_key', $key)->where('p.is_active', true)->select(['p.public_id', 'p.name', 'p.sku', 'p.is_serialized']));

        foreach ($products->orderBy('name')->limit(self::ROWS + 1)->get() as $row) {
            $matches[] = ['kind' => 'product', 'product' => Row::text($row, 'public_id'), 'name' => Row::text($row, 'name'), 'sku' => Row::nullable($row, 'sku'), 'serialized' => (bool) $row->is_serialized];
        }

        $units = DB::table('serial_units as s')->join('products as p', 'p.id', '=', 's.product_id')->where('s.serial_key', mb_strtolower((string) preg_replace('/\s+/u', '', $term)))->selectRaw("'serial' as kind, s.serial_number as code, s.state = 'IN_STOCK' as in_stock, p.public_id, p.name")
            ->unionAll(DB::table('inventory_lots as l')->join('products as p', 'p.id', '=', 'l.product_id')->where('l.lot_code', $key)->selectRaw("'lot' as kind, l.lot_code as code, false as in_stock, p.public_id, p.name"));

        foreach ($units->limit(self::ROWS + 1)->get() as $row) {
            $code = Row::text($row, 'code');
            // Whether it is in stock, no more: any other state is said only beside the movement explaining it (PERMISSIONS_MATRIX row 49).
            $matches[] = Row::text($row, 'kind') === 'serial'
                ? ['kind' => 'serial', 'product' => Row::text($row, 'public_id'), 'name' => Row::text($row, 'name'), 'serial' => $code, 'in_stock' => (bool) $row->in_stock]
                : ['kind' => 'lot', 'product' => Row::text($row, 'public_id'), 'name' => Row::text($row, 'name'), 'lot' => $code];
        }

        $matches = array_slice($matches, 0, self::ROWS);

        return ['kind' => match (count($matches)) {
            0 => 'unknown',
            1 => 'match',
            default => 'ambiguous',
        }, 'code' => $term, 'matches' => $matches, 'register' => $matches === [] && $this->capabilities->for($viewerId)->allows('products.edit')];
    }
}
