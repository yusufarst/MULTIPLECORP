<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Data\DispatchEntry;
use App\Modules\INV\Services\DispatchPosting;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV Dispatch` (ROUTE-186; INT-186; AX-03; WF-INV-03; WF-INV-07; L-49; M-01; M-03; M-05; M-07) — "Catat Barang Keluar", one command per line with its own answer (IP-19). `stock.dispatch` of the project's company; another company's lot also `stock.allocate_intercompany`, the consuming company's grant alone (XL-1).
 */
final class Dispatch
{
    public const TYPE = 'INV.Dispatch';

    public const CAPABILITY = 'stock.dispatch';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly DispatchPosting $posting,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, int $companyId, DispatchEntry $entry, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($entry): CommandResult {
            $context->lock($this->posting->locks($entry));

            return new CommandResult('stock_movement', $this->posting->record($context, $entry));
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED ? DispatchPosting::refusalState($outcome->code, $entry) : null);
    }
}
