<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Services\DispatchRestoration;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `INV ReverseDispatch` (ROUTE-188; INT-188; AX-10; CM-05; CM-06; SF-RESTORE) — "Batalkan Barang Keluar (Belum Berangkat)" and "Catat Barang Kembali Tanpa Terkirim" with MSG-40's reason (IP-09). `stock.reverse` (ADM+); the same-action reservation also `stock.reserve`.
 */
final class ReverseDispatch
{
    public const TYPE = 'INV.ReverseDispatch';

    public const CAPABILITY = 'stock.reverse';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly DispatchRestoration $restoration,
    ) {}

    /**
     * @param  list<array{consumption: int, qty: string, base_unit: bool, condition: string, location: int, serial: ?int}>  $items
     * @param  list<string>  $acknowledged
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, int $companyId, int $dispatchLineId, string $kind, array $items, ?string $reserve, string $reason, array $acknowledged, ?string $duplicateReason, string $businessDate, bool $prefilled, bool $serializedSeen, int $scaleSeen, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($companyId), $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($dispatchLineId, $kind, $items, $reserve, $reason, $acknowledged, $duplicateReason, $businessDate, $prefilled, $serializedSeen, $scaleSeen): CommandResult {
            $context->lock($this->restoration->locks($dispatchLineId, $items));

            return new CommandResult('stock_movement', $this->restoration->restore($context, $dispatchLineId, $kind, $items, $reserve, $reason, $acknowledged, $duplicateReason, $businessDate, $prefilled, $serializedSeen, $scaleSeen));
        }, null, fn (Outcome $outcome): ?array => $outcome->kind === Outcome::REJECTED ? DispatchRestoration::refusalState($outcome->code, $dispatchLineId, $items, $businessDate) : null);
    }
}
