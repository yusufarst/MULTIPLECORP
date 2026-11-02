<?php

namespace App\Modules\INV\Actions;

use App\Modules\INV\Services\SurplusAttribution;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Money;

/**
 * `INV AttributeSurplus` (ROUTE-197; INT-197; AX-07; OD-08) — "Tetapkan Surplus". `stock.attribute_surplus`, the Owner's alone, never grantable (RG-02).
 */
final class AttributeSurplus
{
    public const TYPE = 'INV.AttributeSurplus';

    public const CAPABILITY = 'stock.attribute_surplus';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly SurplusAttribution $attribution,
    ) {}

    /**
     * @param  list<array{consumption: int, qty: string}>  $restored
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, int $finding, int $company, Money $basis, array $restored, string $reason, string $businessDate, bool $prefilled, bool $serializedSeen, int $scaleSeen, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, null, self::CAPABILITY, Fingerprint::of(self::TYPE, null, ['target' => $finding, ...$payload]));

        return $this->envelope->run($request, function (CommandContext $context) use ($finding, $company, $basis, $restored, $reason, $businessDate, $prefilled, $serializedSeen, $scaleSeen): CommandResult {
            $context->lock(SurplusAttribution::locks($finding, $restored));

            return new CommandResult('stock_count_finding', $this->attribution->attribute($context, $finding, $company, $basis, $restored, $reason, $businessDate, $prefilled, $serializedSeen, $scaleSeen));
        });
    }
}
