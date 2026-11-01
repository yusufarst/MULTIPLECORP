<?php

namespace Tests\Support;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\INV\Actions\AdjustStock;
use App\Modules\INV\Actions\ChangeCondition;
use App\Modules\INV\Actions\MoveLocation;
use App\Modules\INV\Actions\ReceiveGoods;
use App\Modules\INV\Actions\RecognizeLoss;
use App\Modules\INV\Actions\Reinspect;
use App\Modules\INV\Actions\ReverseLoss;
use App\Modules\INV\Data\ReceiptEntry;
use App\Modules\INV\Data\StockPicks;
use App\Modules\ORG\Models\Company;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TASK-042's world (TL-04–TL-08): ROUTE-190 and ROUTE-194–ROUTE-196's actions as their own commands, each answering its word; a lot received with units damaged at its receipt.
 */
final class AdjustmentWorld
{
    public const CAPABILITIES = ['stock.view', 'stock.condition', 'stock.adjust', 'stock.move', 'stock.reverse', 'stock.reserve', 'evidence.view', 'projects.view'];

    /** @return array{lot: int, location: int, condition: string, qty: string, serial: ?int} */
    public static function pick(int $lot, int $rack, string $qty, string $condition = 'USABLE', ?int $serial = null): array
    {
        return ['lot' => $lot, 'location' => $rack, 'condition' => $condition, 'qty' => $qty, 'serial' => $serial];
    }

    /**
     * @param  list<array{lot: int, location: int, condition: string, qty: string, serial: ?int}>  $picks
     * @param  array<int, string>  $cuts
     * @param  list<int>  $evidence
     * @param  list<string>  $acknowledged
     */
    public static function entry(int $product, array $picks, array $cuts = [], array $evidence = [], bool $unidentified = false, array $acknowledged = [], ?string $duplicateReason = null): StockPicks
    {
        $policy = DB::table('products')->where('id', $product)->first(['is_serialized', 'quantity_scale']);

        return new StockPicks($product, $picks, $cuts, 'Unit rusak diperiksa', $evidence, $unidentified, $acknowledged, $duplicateReason, BusinessDate::today(), false, (bool) $policy->is_serialized, (int) $policy->quantity_scale);
    }

    public static function change(int $actor, StockPicks $entry, ?string $commandId = null): string
    {
        return InvoiceWorld::word(app(ChangeCondition::class)->run($commandId ?? (string) Str::uuid(), $actor, $entry, self::payload($entry)));
    }

    public static function reinspect(int $actor, StockPicks $entry): string
    {
        return InvoiceWorld::word(app(Reinspect::class)->run((string) Str::uuid(), $actor, $entry, self::payload($entry)));
    }

    public static function adjust(int $actor, StockPicks $entry, string $kind = 'SHRINKAGE', ?int $case = null, ?string $allocation = null): string
    {
        $action = $kind === 'OTHER' ? app(RecognizeLoss::class) : app(AdjustStock::class);

        return InvoiceWorld::word($action->run((string) Str::uuid(), $actor, $entry, $kind, $case, $allocation, [...self::payload($entry), 'kind' => $kind, 'case' => $case, 'allocation' => $allocation]));
    }

    /** @param  list<int>  $serials */
    public static function move(int $actor, int $product, int $lot, int $from, int $to, string $qty, string $condition = 'USABLE', array $serials = []): string
    {
        $policy = DB::table('products')->where('id', $product)->first(['is_serialized', 'quantity_scale']);

        return InvoiceWorld::word(app(MoveLocation::class)->run((string) Str::uuid(), $actor, $product, $lot, $from, $to, $condition, $qty, $serials, BusinessDate::today(), false, (bool) $policy->is_serialized, (int) $policy->quantity_scale, ['lot' => $lot, 'from' => $from, 'to' => $to, 'qty' => $qty, 'serials' => $serials, 'run' => (string) Str::uuid()]));
    }

    public static function reverse(int $actor, ?int $company, int $movement): string
    {
        return InvoiceWorld::word(app(ReverseLoss::class)->run((string) Str::uuid(), $actor, $company, $movement, 'Kerugian keliru dicatat', BusinessDate::today(), false));
    }

    /** A lot received with `damaged` of its units UNUSABLE at the receipt of the purchase line; the lot. */
    public static function damagedReceipt(int $actor, Company $company, int $product, int $unit, int $rack, string $usable, string $damaged, string $price, int $project, int $demand): int
    {
        $total = (string) ((int) $usable + (int) $damaged);
        $purchase = ReceivingWorld::purchase($company, $product, $unit, $total, $price, $project, $demand, $actor);
        $line = ReceivingWorld::line($purchase);
        Numbering::goLive(substr(BusinessDate::today(), 0, 4).'-01-01');
        $entry = new ReceiptEntry($purchase->id, $line->id, ReceiptEntry::ORIGINAL, $unit, $usable, $damaged, '0', $rack, [], null, null, null, null, [], BusinessDate::today(), false, false, 0);
        $word = InvoiceWorld::word(app(ReceiveGoods::class)->run((string) Str::uuid(), $actor, $company->id, $entry, ['run' => (string) Str::uuid()]));

        if ($word !== 'COMMITTED') {
            throw new \RuntimeException("The receipt was {$word}.");
        }

        return ReceivingWorld::lotOf($line->id);
    }

    /**
     * Pool evidence, as shared stock's is (PJ-08), unless the attributes say otherwise.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function evidence(array $attributes = []): int
    {
        return (int) EvidenceDocument::factory()->create(['company_id' => null, 'evidence_scope' => 'POOL', 'evidence_type' => 'WAREHOUSE_RECORD', ...$attributes])->id;
    }

    /** @return array<string, mixed> */
    private static function payload(StockPicks $entry): array
    {
        return ['product' => $entry->productId, 'picks' => $entry->picks, 'cuts' => $entry->cuts, 'evidence' => $entry->evidence, 'unidentified' => $entry->unidentified, 'acknowledged' => $entry->acknowledged];
    }
}
