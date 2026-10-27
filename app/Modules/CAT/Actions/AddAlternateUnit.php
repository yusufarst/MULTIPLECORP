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
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * `CAT AddAlternateUnit` (ROUTE-146; INT-146; NX-01; L-31; C-09): an alternate unit of a product as an exact integer
 * multiple of its base — a box of twelve pieces —, so an entered quantity converts by multiplication only (BR-INV-03).
 * A transaction snapshots the ratio it used, so a later ratio never reaches it (numeric annex 11). The product row at
 * LK-08; a unit the product already has answers CONFLICT at its field (SQ-08).
 */
final class AddAlternateUnit
{
    public const TYPE = 'CAT.AddAlternateUnit';

    public const RATIO = 'CAT.ChangeUnitRatio';

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

    /**
     * An alternate unit's ratio corrected — BOX entered as 10 where it holds 12 —, the conversion row at LK-08 under its
     * product and changed at the `lock_version` the form read (ST-01). Saved transactions keep the ratio they pinned, so
     * the correction reaches later entries only (numeric annex 11; DATABASE §4.4). The base unit's ratio is 1 always.
     */
    public function change(string $commandId, int $actorUserId, Product $product, string $code, int $ratio, int $lockVersion): Outcome
    {
        return $this->commands->run($commandId, self::RATIO, $actorUserId, ['product' => $product->public_id, 'code' => $code, 'ratio' => $ratio, 'lock_version' => $lockVersion],
            function (CommandContext $context) use ($product, $code, $ratio, $lockVersion): CommandResult {
                $unit = ProductUnit::query()->join('units', 'units.id', '=', 'product_units.unit_id')->where('product_units.product_id', $product->id)
                    ->where('units.code', mb_strtoupper($code))->where('product_units.is_base', false)->value('product_units.id');

                if (! is_numeric($unit)) {
                    throw new ModelNotFoundException;
                }

                $context->lock((new LockSet)->add('products', ['id' => $product->id])->add('product_units', ['id' => (int) $unit]));
                $row = ProductUnit::query()->findOrFail((int) $unit);

                if (! Product::query()->findOrFail($product->id)->is_active) {
                    throw Refused::rejected('WF-MD-04', ['reason' => 'archived']);
                }

                if ($row->lock_version !== $lockVersion) {
                    throw Refused::conflict('ST-01');
                }

                if ($row->ratio_to_base !== $ratio) {
                    $this->commands->update($context, 'product_units', $row->id, $lockVersion, ['ratio_to_base' => $ratio]);
                    $this->commands->record($context, 'CAT.UnitRatioChanged', 'product_unit', $row->id, $product->public_id, ['unit' => mb_strtoupper($code), 'ratio_to_base' => $row->ratio_to_base], ['unit' => mb_strtoupper($code), 'ratio_to_base' => $ratio]);
                }

                return new CommandResult('product', $product->id);
            });
    }
}
