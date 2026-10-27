<?php

namespace App\Modules\CAT\Actions;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\ProductPriceHistory;
use App\Modules\CAT\Services\CatalogCommands;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * `CAT ChangeDefaultPrice` (ROUTE-146; INT-146; NX-01; CAP-03; REF-015; BR-SN-01): a purchase, selling or SPJ-reference
 * default changed forward only — a new history row from today's business date, the current default the latest
 * (DATABASE §4.4) —, never a saved transaction's or document's value, which snapshots its own. Beyond `products.edit`
 * the kind's own view capability, re-read with the actor's authority in the command (PJ-14): purchase `cost.view`,
 * selling and SPJ-reference `finance.view`. A price is a critical audit area (BR-XC-01).
 */
final class ChangeDefaultPrice
{
    public const TYPE = 'CAT.ChangeDefaultPrice';

    /** Each kind's view capability (PJ-14). */
    public const FAMILIES = ['PURCHASE_DEFAULT' => 'cost.view', 'SELLING_DEFAULT' => 'finance.view', 'SPJ_REFERENCE' => 'finance.view'];

    public function __construct(private readonly CatalogCommands $commands) {}

    public function run(string $commandId, int $actorUserId, Product $product, string $kind, string $amount, ?string $reason): Outcome
    {
        return $this->commands->run($commandId, self::TYPE, $actorUserId, ['product' => $product->public_id, 'kind' => $kind, 'amount' => $amount, 'reason' => $reason],
            function (CommandContext $context) use ($product, $kind, $amount, $reason): CommandResult {
                $context->lock((new LockSet)->add('products', ['id' => $product->id]));

                if ($context->authority()?->allows(self::FAMILIES[$kind]) !== true) {
                    throw new AuthorizationException;
                }

                $row = Product::query()->findOrFail($product->id);

                if (! $row->is_active) {
                    throw Refused::rejected('WF-MD-04', ['reason' => 'archived']);
                }

                $values = ['product_id' => $row->id, 'price_kind' => $kind, 'amount' => $amount, 'effective_from' => BusinessDate::today(), 'reason' => $reason];
                $history = new ProductPriceHistory;
                $history->forceFill([...$values, 'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(),
                    'recorded_by' => $context->request->actorUserId])->save();
                $this->commands->record($context, 'CAT.DefaultPriceChanged', 'product', $row->id, $row->public_id, [], ['price_kind' => $kind, 'amount' => $amount, 'effective_from' => $values['effective_from']], $reason);

                return new CommandResult('product', $row->id);
            });
    }
}
