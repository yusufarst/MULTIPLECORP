<?php

namespace App\Modules\FIN\Data;

/**
 * A payment's contra as AX-20 takes it (CM-19; ST-07; SF-CONTRA): how much of the fact was wrong, and what becomes of every ACTIVE application and settlement it backs —
 * kept, released to credit, moved to the re-recorded payment, a refund's part left unbacked as the case's residual, or a settlement turned RESIDUAL.
 */
final class ContraEntry
{
    public const KEEP = 'KEEP';

    public const RELEASE = 'RELEASE';

    public const MOVE = 'MOVE';

    public const SHORTFALL = 'SHORTFALL';

    public const RESIDUAL = 'RESIDUAL';

    /**
     * @param  array<int, array{action: string, amount: ?string}>  $applications  application => its disposition, a part's amount where not the whole
     * @param  array<int, string>  $settlements  settlement => KEEP, MOVE or RESIDUAL
     */
    public function __construct(
        public readonly string $amount,
        public readonly string $reason,
        public readonly string $businessDate,
        public readonly bool $prefilled,
        public readonly ?int $rerecordedId,
        public readonly array $applications,
        public readonly array $settlements,
    ) {}
}
