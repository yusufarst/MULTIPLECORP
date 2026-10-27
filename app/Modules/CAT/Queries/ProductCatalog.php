<?php

namespace App\Modules\CAT\Queries;

use App\Modules\CAT\Actions\ChangeDefaultPrice;
use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Services\CatalogCommands;
use App\Modules\IAM\Data\Audience;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Queries\ProductStock;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * PG-056 *Produk & Jasa* (ROUTE-056; SCR-52; WF-MD-04; PERMISSIONS_MATRIX §7.2 rows 22–27; PJ-14): every active
 * account's catalogue — a page in name order after a cursor, each row with its primary image's thumbnail (PF-31;
 * PT-22); the page's search-first lookup is ROUTE-281's — and one product's record: its units, barcodes, serial policy,
 * images and active state to all (S0), each price default's current value and history only to its family — purchase
 * `cost.view`, selling and SPJ-reference `finance.view` —, and its pooled stock figures only with `stock.view`; a family
 * the viewer lacks is absent, never masked or zero (IA §8). A record reads a fixed number of statements (PFM-02).
 */
final class ProductCatalog
{
    public const PAGE = 25;

    /** A kind's history rows shown, the latest first. */
    public const HISTORY = 20;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly ProductStock $stock,
    ) {}

    /**
     * @return array{list: array{rows: list<array<string, mixed>>, more: ?string}, product: ?array<string, mixed>, can: array{edit: bool, prices: list<string>}}
     */
    public function screen(int $viewerId, ?string $after, ?string $selected): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $product = $selected === null ? null : $this->find($selected);
        $edit = $viewer->allows(CatalogCommands::CAPABILITY) && ! $viewer->scope->isEmpty();

        return [
            'list' => $this->page($after),
            'product' => $product === null ? null : $this->detail($viewer, $product),
            'can' => ['edit' => $edit, 'prices' => $edit ? self::kinds($viewer) : []],
        ];
    }

    /** @return array{rows: list<array<string, mixed>>, more: ?string} */
    public function page(?string $after): array
    {
        $query = Product::query()->select(['id', 'public_id', 'sku', 'name', 'kind', 'is_active']);
        $cursor = $after !== null && preg_match(self::UUID, $after) === 1 ? Product::query()->where('public_id', $after)->first(['id', 'name']) : null;

        if ($cursor !== null) {
            $query->whereRaw('(lower(name), id) > (lower(?), ?)', [$cursor->name, $cursor->id]);
        }

        $found = array_values($query->orderByRaw('lower(name)')->orderBy('id')->limit(self::PAGE + 1)->get()->all());
        $shown = array_slice($found, 0, self::PAGE);
        $thumbnails = $this->thumbnails(array_map(fn (Product $product): int => $product->id, $shown));
        $rows = array_map(fn (Product $product): array => ['id' => $product->public_id, 'sku' => $product->sku, 'name' => $product->name, 'kind' => $product->kind,
            'active' => $product->is_active, 'thumbnail' => $thumbnails[$product->id] ?? null], $shown);

        return ['rows' => $rows, 'more' => count($found) > self::PAGE ? $rows[self::PAGE - 1]['id'] : null];
    }

    /**
     * The primary image's file of each product, by product — one statement for a list's rows (PF-31; PT-22).
     *
     * @param  list<int>  $productIds
     * @return array<int, string>
     */
    public function thumbnails(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = DB::table('product_images')->join('file_objects', 'file_objects.id', '=', 'product_images.file_object_id')
            ->whereIn('product_images.product_id', $productIds)->where('product_images.is_primary', true)
            ->get(['product_images.product_id', 'file_objects.public_id'])->all();
        $found = [];

        foreach ($rows as $row) {
            $found[(int) Row::text($row, 'product_id')] = Row::text($row, 'public_id');
        }

        return $found;
    }

    public function publicIdOf(int $id): ?string
    {
        $publicId = Product::query()->whereKey($id)->value('public_id');

        return is_string($publicId) ? $publicId : null;
    }

    public function find(string $publicId): ?Product
    {
        return preg_match(self::UUID, $publicId) === 1 ? Product::query()->where('public_id', $publicId)->first() : null;
    }

    /** @return array<string, mixed> */
    public function detail(Authority $viewer, Product $product): array
    {
        $units = DB::table('product_units')->join('units', 'units.id', '=', 'product_units.unit_id')->where('product_units.product_id', $product->id)
            ->orderByDesc('product_units.is_base')->orderBy('product_units.ratio_to_base')
            ->get(['units.code', 'units.name', 'product_units.ratio_to_base', 'product_units.is_base', 'product_units.lock_version'])->all();
        $barcodes = DB::table('product_barcodes')->where('product_id', $product->id)->orderByDesc('is_active')->orderBy('id')->get(['code', 'kind', 'is_active'])->all();
        $images = DB::table('product_images')->join('file_objects', 'file_objects.id', '=', 'product_images.file_object_id')
            ->where('product_images.product_id', $product->id)->orderByDesc('product_images.is_primary')->orderBy('product_images.sort_order')
            ->get(['file_objects.public_id', 'product_images.is_primary'])->all();
        $kinds = self::kinds($viewer);
        $history = $kinds === [] ? [] : DB::table('product_price_history')->where('product_id', $product->id)->whereIn('price_kind', $kinds)
            ->orderByDesc('effective_from')->orderByDesc('id')->get(['price_kind', 'amount', 'effective_from', 'reason'])->all();
        $prices = [];

        foreach ($kinds as $kind) {
            $rows = array_values(array_filter($history, fn (object $row): bool => Row::text($row, 'price_kind') === $kind));
            $prices[] = ['kind' => $kind, 'history' => array_map(fn (object $row): array => ['amount' => Row::text($row, 'amount'), 'date' => Row::text($row, 'effective_from'),
                'reason' => Row::nullable($row, 'reason')], array_slice($rows, 0, self::HISTORY))];
        }

        $base = array_values(array_filter($units, fn (object $unit): bool => data_get($unit, 'is_base') === true))[0] ?? null;

        return [
            'id' => $product->public_id,
            'kind' => $product->kind,
            'sku' => $product->sku,
            'name' => $product->name,
            'base_unit' => $base === null ? null : Row::text($base, 'code'),
            'quantity_scale' => $product->quantity_scale,
            'serialized' => $product->is_serialized,
            'active' => $product->is_active,
            'deactivated_at' => $product->deactivated_at?->toIso8601String(),
            'lock_version' => $product->lock_version,
            'units' => array_map(fn (object $unit): array => ['code' => Row::text($unit, 'code'), 'name' => Row::text($unit, 'name'),
                'ratio' => (int) Row::text($unit, 'ratio_to_base'), 'base' => data_get($unit, 'is_base') === true, 'lock_version' => (int) Row::text($unit, 'lock_version')], $units),
            'barcodes' => array_map(fn (object $barcode): array => ['code' => Row::text($barcode, 'code'), 'kind' => Row::text($barcode, 'kind'),
                'active' => data_get($barcode, 'is_active') === true], $barcodes),
            'images' => array_map(fn (object $image): array => ['file' => Row::text($image, 'public_id'), 'primary' => data_get($image, 'is_primary') === true], $images),
            'prices' => $prices,
            ...(ProductStock::audience()->admits($viewer) ? ['stock' => $this->stock->figures($viewer, $product->id)] : []),
        ];
    }

    /**
     * The price kinds the viewer's families admit (PJ-14).
     *
     * @return list<string>
     */
    public static function kinds(Authority $viewer): array
    {
        return array_values(array_filter(array_keys(ChangeDefaultPrice::FAMILIES), fn (string $kind): bool => Audience::family(ChangeDefaultPrice::FAMILIES[$kind])->admits($viewer)));
    }
}
