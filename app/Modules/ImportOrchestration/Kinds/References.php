<?php

namespace App\Modules\ImportOrchestration\Kinds;

use App\Modules\ImportOrchestration\Services\RowRefused;
use App\Support\Command\Outcome;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * A row's references, resolved at its commit inside its company (CS-04; DATABASE §24): an earlier batch's committed
 * row by its import identity, or a master by its own key — a product's SKU, a location's code —; a reference that
 * resolves to nothing refuses the row with its column.
 */
final class References
{
    /** The record an earlier batch's committed row of `$kind` created for `$identity`. */
    public static function imported(string $kind, string $identity, string $field, ?int $companyId = null): int
    {
        $query = DB::table('import_rows')->where('import_kind', $kind)->where('import_identity', $identity)->where('validation_state', 'COMMITTED');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $id = $query->value('result_entity_id');

        return is_numeric($id) ? (int) $id : throw new RowRefused($field, 'reference');
    }

    /** A product by its SKU, or by the identity its import row carried. */
    public static function product(string $key, string $field): int
    {
        $id = DB::table('products')->whereRaw('lower(sku) = ?', [mb_strtolower($key)])->value('id');

        return is_numeric($id) ? (int) $id : self::imported(ProductKind::CODE, $key, $field);
    }

    public static function location(string $code, string $field): int
    {
        $id = DB::table('locations')->where('code', $code)->where('is_active', true)->value('id');

        return is_numeric($id) ? (int) $id : throw new RowRefused($field, 'reference');
    }

    /**
     * A unit of the product by its code, or its base unit when none is named: its id and ratio to the base unit.
     *
     * @return array{unit: int, ratio: int}
     */
    public static function unit(int $productId, ?string $code, string $field): array
    {
        $query = DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')->where('pu.product_id', $productId);
        $row = ($code === null || $code === '' ? $query->where('pu.is_base', true) : $query->whereRaw('lower(u.code) = ?', [mb_strtolower($code)]))
            ->first(['pu.unit_id', 'pu.ratio_to_base']) ?? throw new RowRefused($field, 'reference');

        return ['unit' => (int) Row::text($row, 'unit_id'), 'ratio' => (int) Row::text($row, 'ratio_to_base')];
    }

    /**
     * A product's kind, scale, name and base unit.
     *
     * @return array{id: int, kind: string, scale: int, name: string}
     */
    public static function productRow(int $productId, string $field): array
    {
        $row = DB::table('products')->where('id', $productId)->first(['id', 'kind', 'quantity_scale', 'name']) ?? throw new RowRefused($field, 'reference');

        return ['id' => (int) Row::text($row, 'id'), 'kind' => Row::text($row, 'kind'), 'scale' => (int) Row::text($row, 'quantity_scale'), 'name' => Row::text($row, 'name')];
    }

    /**
     * The record an earlier step of the row created.
     *
     * @param  array<mixed>  $done
     */
    public static function result(array $done, int $step, string $field): int
    {
        $outcome = $done[$step] ?? null;
        $id = $outcome instanceof Outcome ? $outcome->resultId : null;

        return $id ?? throw new RowRefused($field, 'reference');
    }

    /** A client organization by the identity its import row carried. */
    public static function client(string $identity, string $field): int
    {
        return self::imported(ClientKind::CODE, $identity, $field);
    }

    /** A supplier by the identity its import row carried. */
    public static function supplier(string $identity, string $field): int
    {
        return self::imported(SupplierKind::CODE, $identity, $field);
    }
}
