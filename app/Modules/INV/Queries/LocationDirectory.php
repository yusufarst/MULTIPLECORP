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
 * of no company to every holder of `stock.view` (S1; CS-05), whatever their company grants — each list a page after its
 * cursor, any product reachable by the lookup with its current minimum and version —; and the active racks that
 * receiving, counts and moves choose from (L-13; L-14). A page reads a fixed number of statements (PFM-01).
 */
final class LocationDirectory
{
    /** The rows of each list a page shows; "Muat Lagi" asks for the next after its last (PT-08). */
    public const PAGE = 100;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /**
     * The page's props: each list's page after its cursor, the product the lookup chose — with its current minimum and
     * version, so any product is reachable whatever the lists show —, and the hint whether the viewer may manage.
     *
     * @return array{locations: array{rows: list<array{code: string, name: string, active: bool, lock_version: int}>, more: ?string},
     *     minimums: array{rows: list<array{id: string, sku: ?string, name: string, minimum: string, unit: string, lock_version: int}>, more: ?string},
     *     picked: ?array{id: string, sku: ?string, name: string, minimum: ?string, unit: string, lock_version: int}, can: array{manage: bool}}
     */
    public function screen(int $viewerId, ?string $afterRack, ?string $afterMinimum, ?string $picked): array
    {
        $viewer = $this->capabilities->for($viewerId);

        return [
            'locations' => $this->racks($afterRack),
            'minimums' => $this->minimums($afterMinimum),
            'picked' => $picked === null ? null : $this->product($picked),
            'can' => ['manage' => $viewer->allows(SaveLocation::CAPABILITY) && ! $viewer->scope->isEmpty()],
        ];
    }

    /** @return array{rows: list<array{code: string, name: string, active: bool, lock_version: int}>, more: ?string} */
    public function racks(?string $after): array
    {
        $query = Location::query()->orderBy('code')->limit(self::PAGE + 1);

        if ($after !== null) {
            $query->where('code', '>', $after);
        }

        $found = array_values($query->get(['code', 'name', 'is_active', 'lock_version'])->all());
        $rows = array_map(fn (Location $location): array => ['code' => $location->code, 'name' => $location->name, 'active' => $location->is_active,
            'lock_version' => $location->lock_version], array_slice($found, 0, self::PAGE));

        return ['rows' => $rows, 'more' => count($found) > self::PAGE ? $rows[self::PAGE - 1]['code'] : null];
    }

    /** @return array{rows: list<array{id: string, sku: ?string, name: string, minimum: string, unit: string, lock_version: int}>, more: ?string} */
    public function minimums(?string $after): array
    {
        $query = DB::table('products')->join('units', 'units.id', '=', 'products.base_unit_id')->whereNotNull('products.min_stock_qty_base')
            ->orderByRaw('lower(products.name)')->orderBy('products.id')->limit(self::PAGE + 1);
        $cursor = $after !== null && preg_match(self::UUID, $after) === 1 ? DB::table('products')->where('public_id', $after)->first(['id', 'name']) : null;

        if ($cursor !== null) {
            $query->whereRaw('(lower(products.name), products.id) > (lower(?), ?)', [Row::text($cursor, 'name'), (int) Row::text($cursor, 'id')]);
        }

        $found = array_values($query->get(['products.public_id', 'products.sku', 'products.name', 'products.min_stock_qty_base', 'units.code', 'products.lock_version'])->all());
        $rows = array_map(fn (object $product): array => ['id' => Row::text($product, 'public_id'), 'sku' => Row::nullable($product, 'sku'), 'name' => Row::text($product, 'name'),
            'minimum' => Row::text($product, 'min_stock_qty_base'), 'unit' => Row::text($product, 'code'), 'lock_version' => (int) Row::text($product, 'lock_version')],
            array_slice($found, 0, self::PAGE));

        return ['rows' => $rows, 'more' => count($found) > self::PAGE ? $rows[self::PAGE - 1]['id'] : null];
    }

    /**
     * One product as ROUTE-148's minimum form needs it: its current minimum — none when it has not one — and version.
     *
     * @return array{id: string, sku: ?string, name: string, minimum: ?string, unit: string, lock_version: int}|null
     */
    public function product(string $publicId): ?array
    {
        if (preg_match(self::UUID, $publicId) !== 1) {
            return null;
        }

        $row = DB::table('products')->join('units', 'units.id', '=', 'products.base_unit_id')->where('products.public_id', $publicId)
            ->first(['products.public_id', 'products.sku', 'products.name', 'products.min_stock_qty_base', 'units.code', 'products.lock_version']);

        return $row === null ? null : ['id' => Row::text($row, 'public_id'), 'sku' => Row::nullable($row, 'sku'), 'name' => Row::text($row, 'name'),
            'minimum' => Row::nullable($row, 'min_stock_qty_base'), 'unit' => Row::text($row, 'code'), 'lock_version' => (int) Row::text($row, 'lock_version')];
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
