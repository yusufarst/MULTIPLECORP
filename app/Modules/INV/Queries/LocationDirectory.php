<?php

namespace App\Modules\INV\Queries;

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Actions\SaveLocation;
use App\Modules\INV\Models\Location;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * PG-057 *Lokasi Rak* (ROUTE-057; SCR-53; WF-MD-05; PERMISSIONS_MATRIX §7.2 rows 22 and 47): the shared warehouse's
 * racks — code, name, active state — and the products that carry a minimum stock in base units (IX-14), pool records
 * of no company to every holder of `stock.view` (S1; CS-05), whatever their company grants; and the active racks that
 * receiving, counts and moves choose from (L-13; L-14). A page reads a fixed number of statements (PFM-01).
 */
final class LocationDirectory
{
    /** The rows shown of each list; a warehouse holds tens of racks. */
    public const ROWS = 200;

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /**
     * @return array{locations: array{rows: list<array{code: string, name: string, active: bool, lock_version: int}>, cut: bool},
     *     minimums: array{rows: list<array{id: string, sku: ?string, name: string, minimum: string, unit: string, lock_version: int}>, cut: bool}, can: array{manage: bool}}
     */
    public function screen(int $viewerId): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $locations = Location::query()->orderByDesc('is_active')->orderBy('code')->limit(self::ROWS + 1)->get(['code', 'name', 'is_active', 'lock_version'])->all();
        $minimums = DB::table('products')->join('units', 'units.id', '=', 'products.base_unit_id')->whereNotNull('products.min_stock_qty_base')
            ->orderByRaw('lower(products.name)')->limit(self::ROWS + 1)
            ->get(['products.public_id', 'products.sku', 'products.name', 'products.min_stock_qty_base', 'units.code', 'products.lock_version'])->all();

        return [
            'locations' => [
                'rows' => array_map(fn (Location $location): array => ['code' => $location->code, 'name' => $location->name, 'active' => $location->is_active,
                    'lock_version' => $location->lock_version], array_slice($locations, 0, self::ROWS)),
                'cut' => count($locations) > self::ROWS,
            ],
            'minimums' => [
                'rows' => array_map(fn (object $product): array => ['id' => Row::text($product, 'public_id'), 'sku' => Row::nullable($product, 'sku'),
                    'name' => Row::text($product, 'name'), 'minimum' => Row::text($product, 'min_stock_qty_base'), 'unit' => Row::text($product, 'code'),
                    'lock_version' => (int) Row::text($product, 'lock_version')], array_slice($minimums, 0, self::ROWS)),
                'cut' => count($minimums) > self::ROWS,
            ],
            'can' => ['manage' => $viewer->allows(SaveLocation::CAPABILITY) && ! $viewer->scope->isEmpty()],
        ];
    }

    /**
     * The active racks, by code, for the forms that record a place (L-13; L-14).
     *
     * @return list<array{code: string, name: string}>
     */
    public function active(): array
    {
        return array_values(array_map(fn (Location $location): array => ['code' => $location->code, 'name' => $location->name],
            Location::query()->where('is_active', true)->orderBy('code')->get(['code', 'name'])->all()));
    }

    public function find(string $code): ?Location
    {
        return Location::query()->where('code', $code)->first();
    }

    /**
     * A product's internal identifier and version by its public identifier, for ROUTE-148's minimum.
     *
     * @return array{id: int, lock_version: int}|null
     */
    public function productOf(string $publicId): ?array
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $publicId) !== 1) {
            return null;
        }

        $row = DB::table('products')->where('public_id', $publicId)->first(['id', 'lock_version']);

        return $row === null ? null : ['id' => (int) Row::text($row, 'id'), 'lock_version' => (int) Row::text($row, 'lock_version')];
    }
}
