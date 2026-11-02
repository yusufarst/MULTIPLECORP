<?php

namespace Tests\Support;

use App\Modules\INV\Actions\AdjustStock;
use App\Modules\INV\Actions\ApplyCountVariance;
use App\Modules\INV\Actions\AttributeSurplus;
use App\Modules\INV\Actions\CancelCount;
use App\Modules\INV\Actions\ChangeCondition;
use App\Modules\INV\Actions\OpenCount;
use App\Modules\INV\Actions\RecordCount;
use App\Modules\INV\Actions\ResolveUnattributedLoss;
use App\Modules\INV\Actions\ResolveUnattributedLossWithEvidence;
use App\Modules\INV\Actions\ReverseUnattributedResolution;
use App\Modules\INV\Data\CountEntry;
use App\Modules\INV\Data\StockPicks;
use App\Support\BusinessDate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TASK-043's world (TL-04–TL-08): ROUTE-191–ROUTE-193 and ROUTE-197–ROUTE-200's actions as their own commands, each answering its word.
 */
final class CountWorld
{
    public const CAPABILITIES = ['stock.view', 'stock.count', 'stock.count_apply', 'stock.reserve', 'evidence.view', 'projects.view'];

    public static function open(int $actor, int $product, int $rack, string $condition = 'USABLE'): int
    {
        $word = InvoiceWorld::word(app(OpenCount::class)->run((string) Str::uuid(), $actor, $product, $rack, $condition, ['run' => (string) Str::uuid()]));

        if ($word !== 'COMMITTED') {
            throw new \RuntimeException("The count did not open: {$word}.");
        }

        return (int) DB::table('stock_counts')->orderByDesc('id')->value('id');
    }

    public static function openRegistration(int $actor, int $product): string
    {
        return InvoiceWorld::word(app(OpenCount::class)->run((string) Str::uuid(), $actor, $product, null, null, ['run' => (string) Str::uuid()]));
    }

    public static function line(int $actor, int $count, int $product, int $rack, string $qty, ?string $serial = null, string $condition = 'USABLE', ?int $lot = null, ?string $commandId = null): string
    {
        return InvoiceWorld::word(app(RecordCount::class)->run($commandId ?? (string) Str::uuid(), $actor, $count, $product, $rack, $condition, $qty, $serial, $lot, ['qty' => $qty, 'serial' => $serial, 'rack' => $rack]));
    }

    public static function cancel(int $actor, int $count, int $product, ?string $commandId = null): string
    {
        return InvoiceWorld::word(app(CancelCount::class)->run($commandId ?? (string) Str::uuid(), $actor, $count, $product, 'Hitungan keliru dibuka', ['count' => $count]));
    }

    /**
     * @param  list<array{lot: int, location: int, condition: string, qty: string, serial: ?int}>  $picks
     * @param  array<int, string>  $cuts
     * @param  list<int>  $evidence
     * @param  list<array{case: int, qty: string}>  $conversions
     * @param  list<array{consumption: int, qty: string}>  $restored
     * @param  list<string>  $acknowledged
     */
    public static function entry(int $count, array $picks = [], string $pending = '', array $cuts = [], bool $unidentified = false, array $evidence = [], array $conversions = [], array $restored = [], ?string $allocation = null, array $acknowledged = [], ?string $duplicateReason = null): CountEntry
    {
        $product = (int) trim((string) DB::table('stock_counts')->where('id', $count)->value('product_ids'), '{}');
        $policy = DB::table('products')->where('id', $product)->first(['is_serialized', 'quantity_scale']);

        return new CountEntry($count, $picks, $unidentified, $pending, $conversions, $restored, $cuts, 'Selisih hasil hitungan', $evidence, $allocation, $acknowledged, $duplicateReason, BusinessDate::today(), false, (bool) $policy->is_serialized, (int) $policy->quantity_scale);
    }

    public static function apply(int $actor, CountEntry $entry, ?string $commandId = null): string
    {
        return InvoiceWorld::word(app(ApplyCountVariance::class)->run($commandId ?? (string) Str::uuid(), $actor, $entry, ['count' => $entry->countId, 'picks' => $entry->picks, 'pending' => $entry->pending, 'cuts' => $entry->cuts, 'conversions' => $entry->conversions, 'restored' => $entry->restored, 'run' => $commandId === null ? (string) Str::uuid() : '']));
    }

    /** @param  list<array{consumption: int, qty: string}>  $restored */
    public static function attribute(int $actor, int $finding, int $company, string $basis, array $restored = [], bool $serialized = false): string
    {
        return InvoiceWorld::word(app(AttributeSurplus::class)->run((string) Str::uuid(), $actor, $finding, $company, Money::of($basis), $restored, 'Stok ditemukan saat opname', BusinessDate::today(), false, $serialized, 0, ['finding' => $finding, 'company' => $company, 'basis' => $basis, 'run' => (string) Str::uuid()]));
    }

    /** @param  list<array{company: int, closes: list<array{lot: int, qty: string}>}>  $lines */
    public static function resolve(int $actor, int $case, array $lines): string
    {
        return InvoiceWorld::word(app(ResolveUnattributedLoss::class)->run((string) Str::uuid(), $actor, $case, $lines, 'Keputusan Owner atas selisih', BusinessDate::today(), false, ['case' => $case, 'lines' => $lines, 'run' => (string) Str::uuid()]));
    }

    /**
     * @param  list<array{lot: int, qty: string}>  $closes
     * @param  list<int>  $evidence
     */
    public static function resolveEvidence(int $actor, int $case, array $closes, array $evidence, string $reason = 'Bukti menunjukkan lot pemilik'): string
    {
        return InvoiceWorld::word(app(ResolveUnattributedLossWithEvidence::class)->run((string) Str::uuid(), $actor, $case, $closes, $evidence, $reason, BusinessDate::today(), false, ['case' => $case, 'closes' => $closes, 'run' => (string) Str::uuid()]));
    }

    /** PG-027's pending branch as its own command (SF-UNATTRIBUTED; Part 4). */
    public static function pendingShrinkage(int $actor, int $product, int $rack, string $qty, int $evidence): string
    {
        $entry = new StockPicks($product, [], [], 'Tidak jelas milik siapa', [$evidence], true, [], null, BusinessDate::today(), false, false, 0, [['location' => $rack, 'condition' => 'USABLE', 'qty' => $qty]]);

        return InvoiceWorld::word(app(AdjustStock::class)->run((string) Str::uuid(), $actor, $entry, 'SHRINKAGE', null, null, ['qty' => $qty, 'run' => (string) Str::uuid()]));
    }

    /** ROUTE-195's pending branch as its own command. */
    public static function pendingCondition(int $actor, int $product, int $rack, string $qty, int $evidence): string
    {
        $entry = new StockPicks($product, [], [], 'Tidak jelas milik siapa', [$evidence], true, [], null, BusinessDate::today(), false, false, 0, [['location' => $rack, 'condition' => 'USABLE', 'qty' => $qty]]);

        return InvoiceWorld::word(app(ChangeCondition::class)->run((string) Str::uuid(), $actor, $entry, ['qty' => $qty, 'run' => (string) Str::uuid()]));
    }

    public static function reverse(int $actor, int $resolution, string $capability = 'stock.resolve_unattributed'): string
    {
        return InvoiceWorld::word(app(ReverseUnattributedResolution::class)->run((string) Str::uuid(), $actor, $resolution, $capability, 'Penetapan keliru', BusinessDate::today(), false, ['resolution' => $resolution, 'run' => (string) Str::uuid()]));
    }
}
