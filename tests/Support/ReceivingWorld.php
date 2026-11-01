<?php

namespace Tests\Support;

use App\Modules\CAT\Models\Product;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use App\Modules\PUR\Data\PurchaseEntry;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseLine;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Database\Factories\INV\LocationFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TASK-039's world (TL-04–TL-08): a purchase through ROUTE-171's own action, a rack, and ROUTE-181's request. Static, so the closures of the two-connection harness can call them (Sessions::run).
 */
final class ReceivingWorld
{
    public const CAPABILITIES = ['stock.view', 'stock.receive', 'stock.reserve', 'stock.reverse', 'projects.view', 'evidence.view', 'documents.issue'];

    /** @param  list<string>  $extra */
    public static function actor(Company $company, array $extra = []): User
    {
        return User::query()->findOrFail(Authorization::admin([$company->id], array_values(array_unique([...self::CAPABILITIES, ...$extra])))->id);
    }

    public static function rack(): int
    {
        return LocationFactory::new()->createOne()->id;
    }

    /** A purchase of one goods line of the product in its base unit, optionally for a project and its demand line. */
    public static function purchase(Company $company, int $productId, int $unitId, string $qty = '100', string $price = '1000', ?int $project = null, ?int $demand = null): Purchase
    {
        if (! DB::table('numbering_schemes')->where('company_id', $company->id)->where('sequence_type', 'PURCHASE')->exists()) {
            PurchaseWorld::numbered($company);
        }

        $actor = PurchaseWorld::actor($company)->id;
        $supplier = PurchaseWorld::supplier()->id;
        $entry = new PurchaseEntry($company->id, $project, $supplier, BusinessDate::today(), false, null, [['handle' => null, 'kind' => 'GOODS', 'product' => $productId, 'line' => $demand, 'mode' => 'WAREHOUSE', 'quantity' => $qty, 'unit' => $unitId, 'price' => $price, 'description' => 'Barang uji', 'taxes' => []]], []);
        expect(PurchaseWorld::save($actor, $entry))->toBe('COMMITTED');

        return PurchaseWorld::latest($company->id);
    }

    public static function line(Purchase $purchase): PurchaseLine
    {
        return PurchaseLine::query()->where('purchase_id', $purchase->id)->orderBy('line_no')->firstOrFail();
    }

    /**
     * ROUTE-181's request.
     *
     * @param  array<string, mixed>  $more
     * @return array<string, mixed>
     */
    public static function request(PurchaseLine $line, string $unit, int $rack, string $usable, string $damaged = '0', string $rejected = '0', array $more = []): array
    {
        $product = Product::query()->findOrFail($line->product_id);

        return ['command_id' => (string) Str::uuid(), 'baris' => RowHandle::of('purchase_lines', $line->id), 'jenis' => 'ORIGINAL', 'satuan' => $unit, 'layak' => $usable, 'rusak' => $damaged, 'tolak' => $rejected, 'rak' => RowHandle::of('locations', $rack),
            'kebijakan_seri' => (bool) $product->is_serialized, 'kebijakan_skala' => $product->quantity_scale, 'tanggal' => BusinessDate::today(), ...$more];
    }

    public static function balance(int $lineId): object
    {
        return DB::table('purchase_line_balances')->where('purchase_line_id', $lineId)->firstOrFail();
    }
}
