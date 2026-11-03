<?php

namespace App\Modules\PRJ\Contracts;

/**
 * PRJ's inversion contract for the residual obligations a higher tier owns (ARCHITECTURE §3; WF-PRJ-03; UXS-37): what stays after a Force Complete —
 * FIN's open receivables, active and ageing; INV's release-required reservations; ADM's missing documents —, each module registering its part under
 * {@see self::TAG}. AX-26 snapshots them with the predicates, re-read after the project's LK-01 lock (ST-04). One set query per part, never one per project.
 */
interface ResidualObligationProvider
{
    public const TAG = 'prj.residual-obligations';

    /**
     * Each project's obligations, in a stable order; an amount or a quantity as its plain decimal string (ST-04).
     *
     * @param  list<int>  $projectIds
     * @return array<int, list<array{kind: string, ref: string, label: string, amount: ?string, quantity: ?string}>>
     */
    public function obligations(array $projectIds): array;
}
