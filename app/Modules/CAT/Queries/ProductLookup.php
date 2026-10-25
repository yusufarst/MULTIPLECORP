<?php

namespace App\Modules\CAT\Queries;

use App\Modules\CAT\Models\Product;
use App\Support\Search\NameSearch;
use App\Support\Search\Row;
use App\Support\Search\Term;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The catalogue's part of search and lookup (PERFORMANCE PF-19–PF-21; IP-17): products are S0 masters, open to
 * every active account (CS-06), so no scope applies. A barcode resolves by its `code_key` among active barcodes
 * and an SKU by `lower(sku)` — each to at most one product —; names by PF-21. Only active products are offered.
 */
final class ProductLookup
{
    /** @return list<array{type: string, id: string, code: ?string, label: string}> */
    public function exact(Term $term, int $rows): array
    {
        $rows = $this->base()
            ->where(fn (Builder $where) => $where
                ->whereRaw('lower(products.sku) = ?', [$term->lower()])
                ->orWhereIn('products.id', fn (QueryBuilder $barcodes) => $barcodes
                    ->select('product_id')
                    ->from('product_barcodes')
                    ->where('is_active', true)
                    ->where('code_key', mb_strtoupper($term->compact()))))
            ->limit($rows + 1)
            ->get()
            ->all();

        return array_map(self::row(...), array_values($rows));
    }

    /** @return array{rows: list<array{type: string, id: string, code: ?string, label: string}>, cut: bool} */
    public function names(Term $term, int $rows): array
    {
        $found = NameSearch::run($this->base(), 'products.name', $term, $rows);

        return ['rows' => array_map(self::row(...), $found['rows']), 'cut' => $found['cut']];
    }

    /** @return Builder<Product> */
    private function base(): Builder
    {
        return Product::query()->select(['products.id as search_key', 'products.public_id', 'products.sku', 'products.name'])->where('products.is_active', true);
    }

    /** @return array{type: string, id: string, code: ?string, label: string} */
    private static function row(object $product): array
    {
        return ['type' => 'product', 'id' => Row::text($product, 'public_id'), 'code' => Row::nullable($product, 'sku'), 'label' => Row::text($product, 'name')];
    }
}
