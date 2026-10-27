<?php

namespace Tests\Support;

use App\Modules\CAT\Actions\CreateProduct;
use App\Modules\CAT\Actions\RegisterBarcode;
use App\Modules\CAT\Actions\UpdateProduct;
use App\Modules\CAT\Models\Product;
use App\Support\Command\Outcome;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;

/**
 * TASK-027's race steps (BD-16; C-01; L-35; LK-13): autoloaded, so the sessions' own processes reach them — two
 * creations of one SKU, two registrations of one code, and the serial switch against stock arriving under the guard.
 */
final class CatalogRaces
{
    public static function create(int $actorId, string $sku): string
    {
        return self::word(app(CreateProduct::class)->run((string) Str::uuid(), $actorId,
            ['kind' => 'GOODS', 'sku' => $sku, 'name' => "Produk {$sku}", 'unit_code' => 'PCS', 'unit_name' => 'Pieces', 'quantity_scale' => 0]));
    }

    public static function register(int $actorId, int $productId, string $code): string
    {
        return self::word(app(RegisterBarcode::class)->run((string) Str::uuid(), $actorId, Product::query()->findOrFail($productId), 'MANUFACTURER', $code));
    }

    public static function serialize(int $actorId, int $productId): string
    {
        $product = Product::query()->findOrFail($productId);

        return self::word(app(UpdateProduct::class)->policy((string) Str::uuid(), $actorId, $product, $product->lock_version, true, 0));
    }

    /** The step committed only once the other session waits on what it wrote. */
    public static function holding(string $point, Closure $step): string
    {
        return DB::transaction(function () use ($point, $step): string {
            $word = $step();
            Barrier::await($point);
            Sessions::awaitBlocked();

            return $word;
        });
    }

    /** A receipt standing in for TASK-039's: ON HAND raised under the product's guard, held until the switch waits. */
    public static function receiveHolding(int $productId, string $quantity): string
    {
        return DB::transaction(function () use ($productId, $quantity): string {
            DB::select('select product_id from stock_product_balances where product_id = ? for no key update', [$productId]);
            DB::update('update stock_product_balances set on_hand_qty = on_hand_qty + ? where product_id = ?', [$quantity, $productId]);
            Barrier::await('the receipt holds the guard');
            Sessions::awaitBlocked();

            return 'RECEIVED';
        });
    }

    private static function word(Outcome $outcome): string
    {
        return trim("{$outcome->kind} ".($outcome->kind === Outcome::COMMITTED ? '' : $outcome->code));
    }
}
