<?php

namespace Tests\Support;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\INV\Actions\ReturnToSupplier;
use App\Modules\INV\Data\StockPicks;
use App\Modules\OPS\Actions\CloseCorrectionCase;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\PUR\Actions\RecordReturnOutcome;
use App\Support\BusinessDate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TASK-044's world (TL-04–TL-08): ROUTE-176's and ROUTE-177's actions and ROUTE-271's close as their own commands, each answering its word, over a lot StockWorld received from a purchase.
 */
final class ReturnWorld
{
    public const CAPABILITIES = [...DispatchWorld::CAPABILITIES, 'purchase_return.record', 'cases.close', 'evidence.view'];

    /** The purchase a lot was received from. */
    public static function purchaseOf(int $lot): int
    {
        return (int) DB::table('inventory_lots as i')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->join('purchase_lines as l', 'l.id', '=', 'r.purchase_line_id')->where('i.id', $lot)->value('l.purchase_id');
    }

    /**
     * ROUTE-176's action on the lot's purchase: its word.
     *
     * @param  list<array{lot: int, location: int, condition: string, qty: string, serial: ?int}>  $picks
     * @param  array<int, string>  $cuts
     * @param  list<int>  $evidence
     */
    public static function returnUnits(int $actor, int $lot, array $picks, array $cuts = [], ?string $commandId = null, array $evidence = [], ?int $purchase = null): string
    {
        $purchase ??= self::purchaseOf($lot);
        $company = (int) DB::table('purchases')->where('id', $purchase)->value('company_id');
        $product = (int) DB::table('inventory_lots')->where('id', $lot)->value('product_id');
        $policy = DB::table('products')->where('id', $product)->first(['is_serialized', 'quantity_scale']);
        $entry = new StockPicks($product, $picks, $cuts, 'Rusak dari pemasok', $evidence, false, [], null, BusinessDate::today(), false, (bool) ($policy->is_serialized ?? false), (int) ($policy->quantity_scale ?? 0));

        return InvoiceWorld::word(app(ReturnToSupplier::class)->run($commandId ?? (string) Str::uuid(), $actor, $company, $purchase, $entry, ['picks' => $picks, 'cuts' => $cuts, 'run' => $commandId ?? (string) Str::uuid()]));
    }

    /** The newest purchase-return case. */
    public static function lastCase(): int
    {
        return (int) DB::table('correction_cases')->where('case_kind', 'PURCHASE_RETURN')->max('id');
    }

    /** ROUTE-177's action: its word. */
    public static function outcome(int $actor, int $case, string $kind, ?string $qty = null, ?string $amount = null, ?int $evidence = null, ?string $allocation = null, ?string $commandId = null): string
    {
        $company = (int) DB::table('correction_cases')->where('id', $case)->value('company_id');

        return InvoiceWorld::word(app(RecordReturnOutcome::class)->run($commandId ?? (string) Str::uuid(), $actor, $company, $case, $kind, $qty, $amount === null ? null : Money::of($amount), $evidence, $allocation, 'Hasil retur dari pemasok', BusinessDate::today(), false, ['kind' => $kind, 'qty' => $qty, 'amount' => $amount, 'run' => $commandId ?? (string) Str::uuid()]));
    }

    /** ROUTE-271's close of the case: its word. */
    public static function close(int $actor, int $case): string
    {
        $model = CorrectionCase::query()->findOrFail($case);

        return InvoiceWorld::word(app(CloseCorrectionCase::class)->run((string) Str::uuid(), $actor, $model, (int) $model->lock_version, 'Retur selesai'));
    }

    /** A company proof a supplier credit cites. */
    public static function credit(int $companyId): int
    {
        return (int) EvidenceDocument::factory()->create(['company_id' => $companyId, 'evidence_scope' => 'COMPANY', 'evidence_type' => 'SUPPLIER_BILL'])->id;
    }

    /** @return array{returned: string, refunded: string, replaced: string, unrecovered: string, state: string} */
    public static function figures(int $case): array
    {
        $row = DB::table('correction_cases')->where('id', $case)->first(['returned_cost', 'refunded_value', 'replaced_value', 'unrecovered_value', 'state']);

        return ['returned' => (string) ($row->returned_cost ?? ''), 'refunded' => (string) ($row->refunded_value ?? ''), 'replaced' => (string) ($row->replaced_value ?? ''), 'unrecovered' => (string) ($row->unrecovered_value ?? ''), 'state' => (string) ($row->state ?? '')];
    }
}
