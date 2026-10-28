<?php

namespace App\Modules\CAT\Services;

use App\Modules\CAT\Data\Quantity;
use App\Modules\CAT\Models\Product;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * CAT's write service for a product's minimum stock (ARCHITECTURE §3; DATABASE §4.4 `min_stock_qty_base`; WF-MD-05):
 * the one way a module above the catalogue sets it — INV's `SetMinimumStock` (TASK-028). The product row at LK-08,
 * changed at the `lock_version` the form read (ST-01); the minimum in base units at the product's scale, or none.
 * Setting it records an advice threshold only: no purchase, reservation or movement follows (BR-INV-10).
 */
final class ProductMinimum
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public static function locks(int $productId): LockSet
    {
        return (new LockSet)->add('products', ['id' => $productId]);
    }

    /**
     * Under the product's lock (`locks`): the minimum set, or cleared with null — at the version the page read, or, with
     * none read, only while the product has no minimum (a minimum the page did not show is never overwritten; ST-01).
     */
    public function set(CommandContext $context, int $productId, ?int $lockVersion, ?string $minimum): void
    {
        $product = Product::query()->findOrFail($productId);

        if ($minimum !== null && ! Quantity::atScale($minimum, $product->quantity_scale)) {
            throw Refused::rejected('BR-INV-03', ['field' => 'minimum', 'reason' => 'scale']);
        }

        if ($lockVersion === null && $product->min_stock_qty_base !== null) {
            throw Refused::conflict('ST-01');
        }

        $lockVersion ??= $product->lock_version;

        if ($product->lock_version !== $lockVersion) {
            throw Refused::conflict('ST-01');
        }

        $before = $product->min_stock_qty_base;

        if (self::same($before, $minimum)) {
            return;
        }

        $changed = DB::table('products')->where('id', $productId)->where('lock_version', $lockVersion)
            ->update(['min_stock_qty_base' => $minimum, 'lock_version' => $lockVersion + 1, 'updated_at' => $context->now()]);

        if ($changed !== 1) {
            throw Refused::conflict('ST-01');
        }

        $this->audit->record(
            action: 'CAT.MinimumStockSet',
            authorityClass: AuthorityClass::Adm,
            actorUserId: $context->request->actorUserId,
            companyId: null,
            entityType: 'product',
            entityId: $product->id,
            entityPublicId: $product->public_id,
            commandId: $context->request->commandId,
            change: AuditChange::updated(['min_stock_qty_base' => $before], ['min_stock_qty_base' => $minimum]),
        );
    }

    private static function same(?string $stored, ?string $given): bool
    {
        // Both exact decimals of three places once multiplied by one — never compared as floats.
        return $stored === null || $given === null ? $stored === $given : Quantity::toBase($stored, 1) === Quantity::toBase($given, 1);
    }
}
