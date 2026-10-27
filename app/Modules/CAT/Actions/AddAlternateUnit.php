<?php

namespace App\Modules\CAT\Actions;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\ProductUnit;
use App\Modules\CAT\Services\CatalogCommands;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;

/**
 * `CAT AddAlternateUnit` (ROUTE-146; INT-146; NX-01; L-31; C-09): an alternate unit of a product as an exact integer
 * multiple of its base — a box of twelve pieces —, so an entered quantity converts by multiplication only (BR-INV-03).
 * A transaction snapshots the ratio it used, so a later ratio never reaches it (numeric annex 11). The product row at
 * LK-08; a unit the product already has answers CONFLICT at its field (SQ-08).
 */
final class AddAlternateUnit
{
    public const TYPE = 'CAT.AddAlternateUnit';

    public function __construct(private readonly CatalogCommands $commands) {}

    public function run(string $commandId, int $actorUserId, Product $product, string $code, string $name, int $ratio): Outcome
    {
        return $this->commands->run($commandId, self::TYPE, $actorUserId, ['product' => $product->public_id, 'code' => $code, 'name' => $name, 'ratio' => $ratio],
            function (CommandContext $context) use ($product, $code, $name, $ratio): CommandResult {
                $context->lock((new LockSet)->add('products', ['id' => $product->id]));
                $row = Product::query()->findOrFail($product->id);

                if (! $row->is_active) {
                    throw Refused::rejected('WF-MD-04', ['reason' => 'archived']);
                }

                $unit = $this->commands->unit($context, $code, $name);

                if (ProductUnit::query()->where('product_id', $row->id)->where('unit_id', $unit->id)->exists()) {
                    throw Refused::conflict('SQ-08', ['field' => 'unit_code']);
                }

                $added = $this->commands->insert($context, new ProductUnit, ['product_id' => $row->id, 'unit_id' => $unit->id, 'ratio_to_base' => $ratio, 'is_base' => false]);
                $this->commands->record($context, 'CAT.AlternateUnitAdded', 'product_unit', $added->id, $row->public_id, [], ['unit' => $unit->code, 'ratio_to_base' => $ratio]);

                return new CommandResult('product', $row->id);
            });
    }
}
