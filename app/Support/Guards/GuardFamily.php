<?php

namespace App\Support\Guards;

/**
 * One guard family of DATABASE §3.1 as `ReconcileGuards` recomputes it: its table, its key, the columns its
 * authoritative formula covers and that formula as SQL over the facts, selecting the key and each column. A
 * posting Task that owns a family registers it in `Families` with the formula of its own semantics.
 */
interface GuardFamily
{
    /** The family's name, its table's (§3.1). */
    public function table(): string;

    /** @return list<string> the key columns */
    public function key(): array;

    /** @return list<string> the columns the formula covers */
    public function columns(): array;

    /** The formula: a SELECT of the key and covered columns over the facts, one row per key that has facts. */
    public function formula(): string;

    /**
     * @return list<string>|null the further columns the formula selects to fill a missing guard row, never compared;
     *                           null when a missing row holds a column no formula of this family recomputes, so
     *                           `GuardMaintenance` reports it and never invents the value
     */
    public function carried(): ?array;
}
