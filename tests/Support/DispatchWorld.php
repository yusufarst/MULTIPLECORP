<?php

namespace Tests\Support;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\INV\Actions\Dispatch;
use App\Modules\INV\Actions\ReverseDispatch;
use App\Modules\INV\Actions\SubstituteDispatchIdentity;
use App\Modules\INV\Data\DispatchEntry;
use App\Modules\INV\Data\DocumentDisposition;
use App\Modules\ORG\Models\Company;
use App\Modules\PUR\Actions\CorrectPurchaseCost;
use App\Modules\PUR\Models\Purchase;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TASK-041's world (TL-04–TL-08): a confirmed warehouse line of a project, ROUTE-186, ROUTE-188 and ROUTE-189's actions as their own commands, each answering its word.
 */
final class DispatchWorld
{
    public const CAPABILITIES = [...StockWorld::CAPABILITIES, 'stock.dispatch', 'stock.reverse', 'stock.reserve', 'stock.view', 'projects.view'];

    /** @return array{project: object, line: int, product: int, unit: int} */
    public static function demand(InvoiceWorld $world, string $qty = '20'): array
    {
        $project = $world->project(['WAREHOUSE'], $qty);
        $line = DB::table('project_lines')->where('project_id', $project->id)->orderBy('line_no')->first();

        return ['project' => $project, 'line' => (int) $line->id, 'product' => (int) $line->product_id, 'unit' => (int) DB::table('product_units')->where('product_id', $line->product_id)->where('is_base', true)->value('unit_id')];
    }

    /** @return array{lot: int, location: int, qty: string, serial: ?int} */
    public static function pick(int $lot, int $rack, string $qty, ?int $serial = null): array
    {
        return ['lot' => $lot, 'location' => $rack, 'qty' => $qty, 'serial' => $serial];
    }

    /**
     * @param  list<array{lot: int, location: int, qty: string, serial: ?int}>  $picks
     * @param  list<string>  $acknowledged
     */
    public static function dispatch(int $actor, int $company, int $line, int $unit, string $qty, array $picks, ?string $allocation = null, array $acknowledged = [], ?string $duplicateReason = null, ?string $date = null, ?string $commandId = null): string
    {
        Numbering::goLive(substr(BusinessDate::today(), 0, 4).'-01-01');
        $product = DB::table('project_lines as l')->join('products as p', 'p.id', '=', 'l.product_id')->where('l.id', $line)->first(['p.is_serialized', 'p.quantity_scale']);
        $entry = new DispatchEntry($line, $unit, $qty, $picks, $allocation, $acknowledged, $duplicateReason, $date ?? BusinessDate::today(), false, (bool) $product->is_serialized, (int) $product->quantity_scale);

        return InvoiceWorld::word(app(Dispatch::class)->run($commandId ?? (string) Str::uuid(), $actor, $company, $entry, ['line' => $line, 'qty' => $qty, 'picks' => $picks, 'allocation' => $allocation, 'acknowledged' => $acknowledged]));
    }

    /** The dispatch movement last recorded for the line, with its dispatch line and first consumption. */
    public static function last(int $line): object
    {
        return DB::table('dispatch_lines as d')->join('stock_consumptions as c', 'c.dispatch_line_id', '=', 'd.id')->where('d.project_line_id', $line)->orderByDesc('d.id')->orderBy('c.id')->first(['d.id as dispatch', 'd.stock_movement_id as movement', 'c.id as consumption', 'c.lot_id', 'c.source_company_id', 'c.bearer_company_id', 'c.bearer_project_id', 'c.allocation_reason', 'c.allocation_authorized_by']);
    }

    /**
     * @param  list<array{consumption: int, qty: string, base_unit: bool, condition: string, location: int, serial: ?int}>  $items
     * @param  list<string>  $acknowledged
     */
    public static function restore(int $actor, int $company, int $dispatchLine, string $kind, array $items, ?string $reserve = null, array $acknowledged = [], ?string $duplicateReason = null, ?DocumentDisposition $document = null): string
    {
        [$serialized, $scale] = self::policy(DB::table('dispatch_lines')->where('id', $dispatchLine)->value('product_id'));

        return InvoiceWorld::word(app(ReverseDispatch::class)->run((string) Str::uuid(), $actor, $company, $dispatchLine, $kind, $items, $reserve, 'Barang tidak jadi berangkat', $acknowledged, $duplicateReason, BusinessDate::today(), false, $serialized, $scale, $document, ['document' => $document?->choice, 'line' => $dispatchLine, 'items' => $items, 'reserve' => $reserve, 'acknowledged' => $acknowledged]));
    }

    /** @return array{consumption: int, qty: string, base_unit: bool, condition: string, location: int, serial: ?int} */
    public static function item(int $consumption, int $rack, string $qty, string $condition = 'USABLE', ?int $serial = null): array
    {
        return ['consumption' => $consumption, 'qty' => $qty, 'base_unit' => true, 'condition' => $condition, 'location' => $rack, 'serial' => $serial];
    }

    public static function substitute(int $actor, int $company, int $consumption, string $qty, ?int $recorded, int $lot, int $rack, ?int $shipped, int $evidence, ?string $allocation = null, ?DocumentDisposition $document = null): string
    {
        [$serialized, $scale] = self::policy(DB::table('stock_consumptions')->where('id', $consumption)->value('product_id'));

        return InvoiceWorld::word(app(SubstituteDispatchIdentity::class)->run((string) Str::uuid(), $actor, $company, $consumption, $qty, $recorded, $lot, $rack, $shipped, $allocation, $evidence, 'Nomor keliru dicatat', BusinessDate::today(), false, $serialized, $scale, $document, ['document' => $document?->choice, 'consumption' => $consumption, 'qty' => $qty, 'lot' => $lot, 'rack' => $rack, 'recorded' => $recorded, 'shipped' => $shipped]));
    }

    /** @return array{bool, int} the product's serial and scale policy, as the page read it (ST-09) */
    private static function policy(mixed $product): array
    {
        $row = DB::table('products')->where('id', $product)->first(['is_serialized', 'quantity_scale']);

        return [(bool) $row->is_serialized, (int) $row->quantity_scale];
    }

    /** ROUTE-175's action on the lot's purchase line, the goods value corrected (M-07). */
    public static function correctLotCost(int $actor, int $lot, string $value): string
    {
        $line = (int) DB::table('inventory_lots as i')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->where('i.id', $lot)->value('r.purchase_line_id');
        $purchase = Purchase::query()->findOrFail(DB::table('purchase_lines')->where('id', $line)->value('purchase_id'));

        return InvoiceWorld::word(app(CorrectPurchaseCost::class)->line((string) Str::uuid(), $actor, $purchase, $line, 'PRICE_CORRECTION', $value, 'Harga keliru', BusinessDate::today(), false));
    }

    /** @param  array<string, mixed>  $attributes */
    public static function evidence(Company $company, array $attributes = []): int
    {
        return (int) EvidenceDocument::factory()->create(['company_id' => $company->id, 'evidence_scope' => 'COMPANY', ...$attributes])->id;
    }
}
