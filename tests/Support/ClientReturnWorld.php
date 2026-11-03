<?php

namespace Tests\Support;

use App\Modules\INV\Actions\RestoreStock;
use App\Modules\INV\Actions\ReverseRestorationOrReturn;
use App\Modules\ORG\Models\Company;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TASK-048's world (TL-04–TL-08): ROUTE-210's and ROUTE-211's actions as their own commands, each answering its word, over a shipment DispatchWorld dispatched and DeliveryWorld delivered.
 */
final class ClientReturnWorld
{
    public const CAPABILITIES = [...DeliveryWorld::CAPABILITIES, 'sales_return.record', 'cases.close', 'cost.view'];

    /** A return proof of the company (IP-16). */
    public static function proof(Company $company): int
    {
        return DispatchWorld::evidence($company, ['evidence_type' => 'DELIVERY_PROOF']);
    }

    /**
     * ROUTE-210's action: its word.
     *
     * @param  list<array{consumption: int, qty: string, base_unit: bool, condition: string, location: int, serial: ?int}>  $items
     * @param  list<string>  $acknowledged
     */
    public static function restore(int $actor, int $company, int $dispatchLine, array $items, int $evidence, ?string $reserve = null, array $acknowledged = [], ?string $duplicateReason = null, ?string $commandId = null): string
    {
        $product = DB::table('dispatch_lines as d')->join('products as p', 'p.id', '=', 'd.product_id')->where('d.id', $dispatchLine)->first(['p.is_serialized', 'p.quantity_scale']);

        return InvoiceWorld::word(app(RestoreStock::class)->run($commandId ?? (string) Str::uuid(), $actor, $company, $dispatchLine, $items, $reserve, 'Klien mengembalikan barang', $evidence, $acknowledged, $duplicateReason, BusinessDate::today(), false,
            (bool) ($product->is_serialized ?? false), (int) ($product->quantity_scale ?? 0), ['line' => $dispatchLine, 'items' => $items, 'reserve' => $reserve, 'evidence' => $evidence, 'acknowledged' => $acknowledged, 'run' => $commandId ?? (string) Str::uuid()]));
    }

    /**
     * ROUTE-211's action on a restoration's or a return's movement: its word.
     *
     * @param  list<array{restoration: ?int, consumption: ?int, qty: string, location: ?int, condition: ?string}>  $items
     * @param  array<int, string>  $cuts
     */
    public static function reverse(int $actor, int $company, int $movement, array $items, array $cuts = [], ?string $commandId = null): string
    {
        return InvoiceWorld::word(app(ReverseRestorationOrReturn::class)->run($commandId ?? (string) Str::uuid(), $actor, $company, $movement, $items, $cuts, 'Tercatat keliru', BusinessDate::today(), false,
            ['movement' => $movement, 'items' => $items, 'cuts' => $cuts, 'run' => $commandId ?? (string) Str::uuid()]));
    }

    /** ROUTE-211's action on a drop-ship return without a movement: its word. */
    public static function reverseDropship(int $actor, int $company, int $confirmation, int $restoration, ?string $commandId = null): string
    {
        return InvoiceWorld::word(app(ReverseRestorationOrReturn::class)->runDropship($commandId ?? (string) Str::uuid(), $actor, $company, $confirmation, $restoration, 'Tercatat keliru', BusinessDate::today(), false,
            ['confirmation' => $confirmation, 'restoration' => $restoration, 'run' => $commandId ?? (string) Str::uuid()]));
    }

    /** @return array{restoration: ?int, consumption: ?int, qty: string, location: ?int, condition: ?string} */
    public static function undo(int $restoration, string $qty): array
    {
        return ['restoration' => $restoration, 'consumption' => null, 'qty' => $qty, 'location' => null, 'condition' => null];
    }

    /** @return array{restoration: ?int, consumption: ?int, qty: string, location: ?int, condition: ?string} */
    public static function unreturn(int $consumption, string $qty, int $rack, string $condition = 'USABLE'): array
    {
        return ['restoration' => null, 'consumption' => $consumption, 'qty' => $qty, 'location' => $rack, 'condition' => $condition];
    }

    /** The newest sales return: its movement, restoration and case. */
    public static function last(): object
    {
        return DB::table('stock_restorations as r')->join('correction_cases as k', 'k.id', '=', 'r.correction_case_id')->where('r.kind', 'SALES_RETURN')->orderByDesc('r.id')
            ->first(['r.id as restoration', 'r.stock_movement_id as movement', 'r.qty_base', 'r.returned_condition', 'k.id as case', 'k.public_id as case_public_id', 'k.causal_project_id', 'k.source_lot_id', 'k.state']) ?? (object) [];
    }

    /** A lot's quantity at a rack in a condition. */
    public static function held(int $lot, int $rack, string $condition = 'USABLE'): string
    {
        return (string) (DB::table('stock_lot_balances')->where('lot_id', $lot)->where('location_id', $rack)->where('condition', $condition)->value('qty') ?? '0');
    }

    /** The project line's net dispatched. */
    public static function dispatched(int $line): string
    {
        return (string) DB::table('project_line_balances')->where('project_line_id', $line)->value('dispatched_net_qty_base');
    }

    /** The consumption's restored quantity. */
    public static function restored(int $consumption): string
    {
        return (string) DB::table('stock_consumptions')->where('id', $consumption)->value('restored_qty_base');
    }

    /** The steps the case was opened with (ADMIN_FLOW §11). */
    public static function steps(int $case): array
    {
        $row = DB::table('audit_events')->where('entity_type', 'correction_case')->where('entity_id', $case)->where('action', 'OPS.CorrectionCaseOpened')->first([DB::raw("metadata->'steps' as steps")]);
        $steps = is_object($row) && is_string($row->steps ?? null) ? json_decode($row->steps, true) : [];

        return is_array($steps) ? $steps : [];
    }
}
