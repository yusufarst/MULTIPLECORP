<?php

namespace App\Modules\IAM\Recovery;

use Carbon\CarbonImmutable;

/**
 * A run of the key-compromise incident command as its audit events record it (AU-21, AU-25; RV-17): its identifier,
 * the compromise window's start recorded once at its start, when its closing sweep completed — the window's end —
 * and whether the operator has recorded the end of its recovery.
 */
final class IncidentRun
{
    public function __construct(
        public readonly string $id,
        public readonly CarbonImmutable $windowStart,
        public readonly CarbonImmutable $startedAt,
        public readonly ?CarbonImmutable $completedAt,
        public readonly bool $ended,
    ) {}

    public function completed(): bool
    {
        return $this->completedAt !== null;
    }

    /** The window start as every event of the run records it: ISO 8601 UTC with microseconds. */
    public function windowStartText(): string
    {
        return IncidentRuns::format($this->windowStart);
    }

    /**
     * The run and its window start for an event's metadata and a security event's details (AU-14, AU-25).
     *
     * @return array{incident_run: string, window_start: string}
     */
    public function reference(): array
    {
        return ['incident_run' => $this->id, 'window_start' => $this->windowStartText()];
    }
}
