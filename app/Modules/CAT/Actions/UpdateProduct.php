<?php

namespace App\Modules\CAT\Actions;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Services\CatalogCommands;
use App\Modules\INV\Queries\ProductStock;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * `CAT UpdateProduct` (ROUTE-146; INT-146; NX-01; WF-MD-04; J-MD-04): a product's name and SKU; its serial policy and
 * quantity scale; its archive. The product row at LK-08, changed at the `lock_version` the form read (ST-01), so a form
 * opened before a policy or scale change meets it as CONFLICT. A policy or scale switch also locks the product's guard
 * at LK-13 and re-reads ON HAND under it (ST-09): switching to serialized with stock on hand is REJECTED with the
 * registration-count path, switching it off keeps the serial history (L-35); a service is never serialized and a
 * serialized product counts whole pieces (DATABASE §4.4; BR-INV-03); a scale that rows of the old scale still
 * reference is REJECTED through the scale key (DATABASE §7; C-09). An archive keeps the product, its stock countable
 * and its confirmed demand fulfillable, and offers it for no new demand or purchase (WF-MD-04; FS-02).
 */
final class UpdateProduct
{
    public const TYPE = 'CAT.UpdateProduct';

    public function __construct(
        private readonly CatalogCommands $commands,
        private readonly ProductStock $stock,
    ) {}

    public function details(string $commandId, int $actorUserId, Product $product, int $lockVersion, string $name, ?string $sku): Outcome
    {
        return $this->commands->run($commandId, self::TYPE, $actorUserId, ['product' => $product->public_id, 'lock_version' => $lockVersion, 'name' => $name, 'sku' => $sku],
            function (CommandContext $context) use ($product, $lockVersion, $name, $sku): CommandResult {
                $row = $this->locked($context, $product, false);

                if ($sku !== null && DB::table('products')->where('id', '<>', $row->id)->whereRaw('lower(sku) = ?', [mb_strtolower($sku)])->exists()) {
                    throw Refused::conflict('SQ-08', ['field' => 'sku']);
                }

                $before = array_filter(['name' => $row->name, 'sku' => $row->sku], fn (?string $value, string $key): bool => ['name' => $name, 'sku' => $sku][$key] !== $value, ARRAY_FILTER_USE_BOTH);
                $after = array_intersect_key(['name' => $name, 'sku' => $sku], $before);
                $this->guard($row, $lockVersion);

                if ($after !== []) {
                    $this->commands->update($context, 'products', $row->id, $lockVersion, $after);
                    $this->commands->record($context, 'CAT.ProductUpdated', 'product', $row->id, $row->public_id, $before, $after);
                }

                return new CommandResult('product', $row->id);
            });
    }

    public function policy(string $commandId, int $actorUserId, Product $product, int $lockVersion, bool $serialized, int $scale): Outcome
    {
        return $this->commands->run($commandId, self::TYPE, $actorUserId, ['product' => $product->public_id, 'lock_version' => $lockVersion, 'serialized' => $serialized, 'scale' => $scale],
            function (CommandContext $context) use ($product, $lockVersion, $serialized, $scale): CommandResult {
                $row = $this->locked($context, $product, true);

                if ($row->kind === 'SERVICE' && ($serialized || $scale !== 0)) {
                    throw Refused::rejected('BR-INV-11', ['reason' => 'service']);
                }

                if ($serialized && $scale !== 0) {
                    throw Refused::rejected('BR-INV-03', ['reason' => 'scale']);
                }

                $this->guard($row, $lockVersion);

                // ON HAND is never negative (C-04): any non-zero digit is stock on hand.
                if ($serialized && ! $row->is_serialized && preg_match('/[1-9]/', $this->stock->onHand($row->id)) === 1) {
                    throw Refused::rejected('L-35', ['reason' => 'on_hand']);
                }

                $before = ['is_serialized' => $row->is_serialized, 'quantity_scale' => $row->quantity_scale];
                $after = ['is_serialized' => $serialized, 'quantity_scale' => $scale];

                if ($before !== $after) {
                    try {
                        DB::transaction(fn () => $this->commands->update($context, 'products', $row->id, $lockVersion, $after));
                    } catch (QueryException $error) {
                        throw $error->getCode() === '23503' ? Refused::rejected('C-09', ['reason' => 'scale_in_use']) : $error;
                    }

                    $this->commands->record($context, 'CAT.ProductPolicyChanged', 'product', $row->id, $row->public_id, $before, $after);
                }

                return new CommandResult('product', $row->id);
            });
    }

    public function archive(string $commandId, int $actorUserId, Product $product, int $lockVersion, ?string $reason): Outcome
    {
        return $this->commands->run($commandId, self::TYPE, $actorUserId, ['product' => $product->public_id, 'lock_version' => $lockVersion, 'archive' => true, 'reason' => $reason],
            function (CommandContext $context) use ($product, $lockVersion, $reason): CommandResult {
                $row = $this->locked($context, $product, false);

                if (! $row->is_active) {
                    throw Refused::rejected('WF-MD-04', ['reason' => 'archived']);
                }

                $this->commands->update($context, 'products', $row->id, $lockVersion, ['is_active' => false, 'deactivated_at' => $context->now(),
                    'deactivated_by' => $context->request->actorUserId, 'deactivated_reason' => $reason === null || trim($reason) === '' ? null : trim($reason)]);
                $this->commands->record($context, 'CAT.ProductArchived', 'product', $row->id, $row->public_id, ['is_active' => true], ['is_active' => false], $reason);

                return new CommandResult('product', $row->id);
            });
    }

    /** The product row at LK-08 — and its guard at LK-13 for a policy or scale switch (ST-09) —, read under them. */
    private function locked(CommandContext $context, Product $product, bool $guard): Product
    {
        $set = (new LockSet)->add('products', ['id' => $product->id]);
        $context->lock($guard ? $set->add('stock_product_balances', ['product_id' => $product->id]) : $set);
        $row = Product::query()->findOrFail($product->id);

        if (! $row->is_active && $guard) {
            throw Refused::rejected('WF-MD-04', ['reason' => 'archived']);
        }

        return $row;
    }

    /** ST-01: a form opened before the row changed — a policy or scale switch included — meets it as CONFLICT. */
    private function guard(Product $row, int $lockVersion): void
    {
        if ($row->lock_version !== $lockVersion) {
            throw Refused::conflict('ST-01');
        }
    }
}
