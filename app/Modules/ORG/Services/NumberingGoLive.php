<?php

namespace App\Modules\ORG\Services;

/**
 * Go-live as the number allocator reads it (NM-03; HO-32; INFRASTRUCTURE §4.2): the day this database became the
 * system of record, recorded once at the cutover by the operator's command of TASK-077, as a setting kept with the
 * deployment. Where it is stored is P11Q-04's answer (DIR-085 OF-04; GAP-068), so TASK-021 reads it through this
 * one interface and nothing else (DIR-098 reading 2): until a store records it, go-live is not recorded, nothing
 * is created on demand and the numbering command neither starts nor seeds.
 */
interface NumberingGoLive
{
    /** The go-live day, `Y-m-d` (WIB), or null while it is not recorded. */
    public function day(): ?string;
}
