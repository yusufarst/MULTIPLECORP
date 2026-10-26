<?php

namespace App\Modules\IAM\Recovery;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The incident command's runs, read from their audit events and never stored elsewhere (AU-21, AU-25; RV-17, RV-18):
 * no table or field is added. A run starts with its window start, recorded once; each account voided, each sweep
 * and each restoration is an event of the run; its completion is the closing sweep's end; the end of its recovery is
 * an audit action of the run.
 */
final class IncidentRuns
{
    public const STARTED = 'IAM.IncidentRunStarted';

    public const SWEPT = 'IAM.IncidentSweep';

    public const VOIDED = 'IAM.CredentialsVoided';

    public const COMPLETED = 'IAM.IncidentRunCompleted';

    public const RESTORED = 'IAM.OwnerRestored';

    public const LINK_ISSUED = 'IAM.OwnerRecoveryLinkIssued';

    public const ENDED = 'IAM.IncidentRecoveryEnded';

    /** The latest run, ended or not — the one a restart continues (RV-17). */
    public function latest(): ?IncidentRun
    {
        $started = DB::table('audit_events')->where('action', self::STARTED)->orderByDesc('id')->first(['occurred_at', 'metadata']);

        return $started === null ? null : $this->run($started);
    }

    /**
     * The latest run whose recovery has not ended, which every recovery step reads (AU-14, AU-25). A later run
     * supersedes every earlier one — a corrected window start is a new run with AU-25's recovery again
     * (INFRASTRUCTURE §7.3 step 7) —, so once the latest run's recovery has ended no earlier run reopens.
     */
    public function latestOpen(): ?IncidentRun
    {
        $run = $this->latest();

        return $run === null || $run->ended ? null : $run;
    }

    /**
     * The open run a recovery step of AU-25 acts in: its incident command completed, its recovery not ended, and the
     * window start given equal to the recorded one, any other value refused.
     *
     * @throws RecoveryRefused
     */
    public function forRecovery(string $windowStart): IncidentRun
    {
        $given = self::parse($windowStart);
        $run = $this->latestOpen();

        if ($run === null) {
            throw new RecoveryRefused('No incident run is open: the key-compromise incident command has not run, or its recovery has ended (AU-25).');
        }

        if (! $run->completed()) {
            throw new RecoveryRefused("Incident run {$run->id} has not completed: recovery starts only after its closing sweep (AU-25; RV-18).");
        }

        if (! $given->equalTo($run->windowStart)) {
            throw new RecoveryRefused("The window start given is not the one incident run {$run->id} recorded, {$run->windowStartText()}; no other value is accepted (AU-25).");
        }

        return $run;
    }

    /** Whether the run has an event of this action for the account. */
    public function hasEvent(IncidentRun $run, string $action, int $userId): bool
    {
        return DB::table('audit_events')->where('action', $action)->where('entity_type', 'user')->where('entity_id', $userId)
            ->whereRaw("metadata->>'incident_run' = ?", [$run->id])->exists();
    }

    /**
     * A window start as the operator gives it: an ISO 8601 instant with its offset, never in the future.
     *
     * @throws RecoveryRefused
     */
    public static function parse(?string $input): CarbonImmutable
    {
        $input = trim((string) $input);

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})$/', $input) !== 1) {
            throw new RecoveryRefused('Give the window start with --window-start as an ISO 8601 instant with its offset, such as 2026-10-01T08:00:00+07:00 (AU-25).');
        }

        try {
            $instant = CarbonImmutable::parse($input)->utc();
        } catch (Throwable) {
            throw new RecoveryRefused('The window start is not a valid instant (AU-25).');
        }

        if ($instant->greaterThan(CarbonImmutable::now())) {
            throw new RecoveryRefused('The window start lies in the future (AU-25).');
        }

        return $instant;
    }

    public static function format(CarbonImmutable $instant): string
    {
        return $instant->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    private function run(object $started): IncidentRun
    {
        $entry = AuditEntry::fromRow($started);
        $id = is_string($entry->metadata['incident_run'] ?? null) ? $entry->metadata['incident_run'] : '';
        $windowStart = is_string($entry->metadata['window_start'] ?? null) ? $entry->metadata['window_start'] : '';

        $completed = DB::table('audit_events')->where('action', self::COMPLETED)
            ->whereRaw("metadata->>'incident_run' = ?", [$id])->value('occurred_at');
        $ended = DB::table('audit_events')->where('action', self::ENDED)
            ->whereRaw("metadata->>'incident_run' = ?", [$id])->exists();

        return new IncidentRun(
            id: $id,
            windowStart: CarbonImmutable::parse($windowStart)->utc(),
            startedAt: $entry->occurredAt,
            completedAt: is_string($completed) ? CarbonImmutable::parse($completed) : null,
            ended: $ended,
        );
    }
}
