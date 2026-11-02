<?php

namespace App\Modules\INV\Services;

use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * ROUTE-196 over the one path that undoes a stock-out's consumptions (CM-34; DATABASE §5.3): only a movement that recorded a loss, through MovementReversal's capped LOSS_REVERSAL restorations (C-27; C-55).
 */
final class LossReversal
{
    public function __construct(private readonly MovementReversal $reversal) {}

    public function locks(int $movementId): LockSet
    {
        return $this->reversal->locks($movementId, []);
    }

    public function reverse(CommandContext $context, int $movementId, string $reason, string $businessDate, bool $prefilled): int
    {
        if (! in_array(DB::table('stock_movements')->where('id', $movementId)->value('movement_type'), MovementReversal::BY_RESTORATION, true)) {
            throw Refused::rejected('C-55', ['reason' => 'type']);
        }

        return $this->reversal->reverse($context, $movementId, [], $reason, $businessDate, $prefilled);
    }
}
