<?php

namespace App\Modules\PRJ\Data;

/**
 * The facts and decisions of PRJ's own tables a project's journey derives from (P7 gate record §a; L-01): its state,
 * whether a commercial confirmation exists, its latest quotation revision's state, whether a DRAFT still lacks the
 * unit or PIC its activation needs (SM:Project), and per fulfilment mode whether its lines are fulfilled and delivered
 * in full or their remainder cancelled (`project_line_balances`; L-04). `invoiced` is read only for a viewer with
 * `finance.view` and only once there is confirmed value: whether the invoiced value reaches it (L-27; QS-15).
 */
final class JourneyFacts
{
    /** @param  array<string, array{done: bool, delivered: bool}>  $modes */
    public function __construct(
        public readonly string $state,
        public readonly bool $confirmed,
        public readonly ?string $quotation,
        public readonly bool $detailsMissing,
        public readonly array $modes,
        public readonly ?bool $invoiced = null,
    ) {}
}
