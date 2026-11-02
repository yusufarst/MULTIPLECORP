<?php

namespace Tests\Support;

use App\Modules\CAT\Models\Product;
use App\Modules\IAM\Models\User;
use App\Modules\INV\Actions\ReceiveGoods;
use App\Modules\INV\Actions\ReverseReceipt;
use App\Modules\INV\Data\ReceiptEntry;
use App\Modules\ORG\Models\Company;
use App\Modules\PUR\Data\PurchaseEntry;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseLine;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Database\Factories\INV\LocationFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

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
    public static function purchase(Company $company, int $productId, int $unitId, string $qty = '100', string $price = '1000', ?int $project = null, ?int $demand = null, ?int $actor = null): Purchase
    {
        if (! DB::table('numbering_schemes')->where('company_id', $company->id)->where('sequence_type', 'PURCHASE')->exists()) {
            PurchaseWorld::numbered($company);
        }

        $actor ??= PurchaseWorld::actor($company)->id;
        $supplier = PurchaseWorld::supplier()->id;
        $entry = new PurchaseEntry($company->id, $project, $supplier, BusinessDate::today(), false, null, [['handle' => null, 'kind' => 'GOODS', 'product' => $productId, 'line' => $demand, 'mode' => 'WAREHOUSE', 'quantity' => $qty, 'unit' => $unitId, 'price' => $price, 'description' => 'Barang uji', 'taxes' => []]], []);
        $saved = PurchaseWorld::save($actor, $entry);

        if ($saved !== 'COMMITTED') {
            throw new RuntimeException("The purchase was {$saved}.");
        }

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

    /**
     * ROUTE-181's action as its own command, for the race tests; its word.
     *
     * @param  list<array{serial: string, damaged: bool}>  $serials
     */
    public static function receive(int $actor, int $company, int $purchase, int $line, int $unit, int $rack, string $usable, ?string $reference = null, array $serials = [], string $kind = ReceiptEntry::ORIGINAL, ?int $cites = null, ?int $case = null): string
    {
        // Go-live is bound per process (Numbering::goLive), and a session is its own.
        Numbering::goLive(substr(BusinessDate::today(), 0, 4).'-01-01');
        $product = DB::table('purchase_lines as l')->join('products as p', 'p.id', '=', 'l.product_id')->where('l.id', $line)->first(['p.is_serialized', 'p.quantity_scale']);
        $entry = new ReceiptEntry($purchase, $line, $kind, $unit, $usable, '0', '0', $rack, $serials, $reference, $cites, $case, null, [], BusinessDate::today(), false, (bool) ($product->is_serialized ?? false), (int) ($product->quantity_scale ?? 0));

        return InvoiceWorld::word(app(ReceiveGoods::class)->run((string) Str::uuid(), $actor, $company, $entry, ['run' => (string) Str::uuid()]));
    }

    /** ROUTE-183's action as its own command, usable units of the rack; its word. */
    public static function reverse(int $actor, int $company, int $lot, int $rack, string $qty): string
    {
        Numbering::goLive(substr(BusinessDate::today(), 0, 4).'-01-01');

        return InvoiceWorld::word(app(ReverseReceipt::class)->run((string) Str::uuid(), $actor, $company, $lot, [['location' => $rack, 'condition' => 'USABLE', 'qty' => $qty, 'serial' => null]], [], 'Salah barang', BusinessDate::today(), false));
    }

    /** ROUTE-183's action naming one serial, for the race tests; its word. */
    public static function reverseSerial(int $actor, int $company, int $lot, int $rack, int $serial): string
    {
        Numbering::goLive(substr(BusinessDate::today(), 0, 4).'-01-01');

        return InvoiceWorld::word(app(ReverseReceipt::class)->run((string) Str::uuid(), $actor, $company, $lot, [['location' => $rack, 'condition' => 'USABLE', 'qty' => '1', 'serial' => $serial]], [], 'Salah barang', BusinessDate::today(), false));
    }

    /**
     * Serial-state reconciliation (FX-04 scenario 14; C-02; BR-INV-08; AC-17): each serialized lot's place holds exactly its serials in stock, each with the entry that put it there.
     *
     * @return list<object>
     */
    public static function serialDifferences(): array
    {
        return [
            ...DB::select("select b.lot_id, b.location_id, b.condition, b.qty, count(s.id) as serials from stock_lot_balances b join products p on p.id = b.product_id and p.is_serialized left join serial_units s on s.current_lot_id = b.lot_id and s.current_location_id = b.location_id and s.current_condition = b.condition and s.state = 'IN_STOCK' group by b.lot_id, b.location_id, b.condition, b.qty having b.qty <> count(s.id)"),
            ...DB::select("select s.id, s.serial_number, s.state from serial_units s left join stock_movement_entries e on e.id = s.last_entry_id where (s.state = 'IN_STOCK' or s.last_entry_id is not null) and (e.id is null or e.serial_unit_id <> s.id or (s.state = 'IN_STOCK' and (e.lot_id <> s.current_lot_id or e.qty_delta_base <= 0)))"),
        ];
    }

    public static function lotOf(int $lineId): int
    {
        $lot = DB::table('inventory_lots as i')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->where('r.purchase_line_id', $lineId)->orderByDesc('i.id')->value('i.id');

        return is_numeric($lot) ? (int) $lot : 0;
    }

    public static function balance(int $lineId): object
    {
        return DB::table('purchase_line_balances')->where('purchase_line_id', $lineId)->firstOrFail();
    }
}
