<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Data\ReceiptEntry;
use App\Modules\INV\Services\ReceivingRecords;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV ReceiveGoods` (ROUTE-181; INT-181; AX-04; WF-INV-01; BD-01; BD-18; M-05; M-35; M-36) — "Catat Barang Masuk", one command per purchase line with its own answer (IP-19), the optional "Reservasi untuk Proyek Ini" in the same transaction (D-UX-12). `stock.receive` of the purchase's company; the reservation also `stock.reserve`.
 */
final class ReceiveGoods
{
    public const TYPE = 'INV.ReceiveGoods';

    public const CAPABILITY = 'stock.receive';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly ReceivingRecords $records,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, int $companyId, ReceiptEntry $entry, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($entry): CommandResult {
            $context->lock($this->records->locks($entry));

            return new CommandResult('stock_movement', $this->records->receive($context, $entry));
        });
    }
}
