<?php

namespace App\Support\SecurityLog;

use App\Support\CorrelationId;
use App\Support\Database\Transactions;
use App\Support\Redaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * The security event writer (SECURITY LG-02–LG-04; DATABASE §4.13 `security_events`), kept apart from the
 * business audit. An event is written outside the business transaction (LG-02): at once when none is open,
 * otherwise once it ends, committed or rolled back, so that a refused or rolled-back command's attempt is
 * recorded; it is written on a connection of its own, committing by itself, so it never joins a transaction
 * still open around a rolled-back savepoint, and a short lock timeout bounds any wait. A failed write never
 * throws, never changes the command's outcome, and is logged with the correlation id instead. A submitted
 * identifier, an address and a provider subject are stored only as keyed hashes, a user agent only as its
 * summary, and the details only under the keys LG-02 names (LG-03). It is shared in Support, so every
 * module's commands and requests — IAM's identity steps among them — call it (ARCHITECTURE §4; DIR-093).
 */
final class SecurityEventRecorder
{
    /** The connection of `config/database.php` the events are written on. */
    public const CONNECTION = 'security';

    /** The whitelisted details keys of LG-02; a later Task adding a kind's detail adds its key here. */
    private const DETAIL_KEYS = [
        // LOGIN_SUCCEEDED: how the session was established.
        'session_method',
        // TWO_FACTOR_FAILED and TWO_FACTOR_COOLDOWN_STARTED (AU-05).
        'path', 'submission_id', 'place_time', 'escalation_level', 'cooldown_seconds',
        // TWO_FACTOR_COOLDOWN_REFUSED (AU-05).
        'refusal_count',
        // OWNER_RECOVERY_LINK_ISSUED after an incident command, OWNER_RESTORED (AU-14, AU-25).
        'incident_run', 'window_start', 'confirmer_public_id',
        // IMPORT_COLLISION_UNDISCLOSED, for display only (TECH-022).
        'declared_kind', 'cutoff_date', 'company_scope',
        // AUTHORIZATION_ANOMALY and ACCESS_DENIED: the capability concerned, a catalogue code (OD-16; TASK-011).
        'capability',
    ];

    private const MAXIMUM_DETAIL_LENGTH = 200;

    /**
     * Writes an event outside the business transaction; never throws.
     *
     * @param  string|null  $identifier  a submitted login identifier, stored only as its keyed hash
     * @param  string|null  $providerSubject  a provider subject, stored only as its keyed hash
     * @param  array<string, mixed>  $details
     */
    public function record(
        SecurityEventKind $kind,
        ?int $userId = null,
        ?int $actorUserId = null,
        ?string $reasonCode = null,
        ?string $routeName = null,
        ?string $targetPublicId = null,
        ?string $identifier = null,
        ?string $providerSubject = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $details = [],
    ): void {
        if ($kind->isUndisclosed()) {
            throw new LogicException("{$kind->value} is written by recordUndisclosed() (TECH-022).");
        }

        if ($identifier !== null && $providerSubject !== null) {
            throw new InvalidArgumentException('An event carries one keyed identifier hash.');
        }

        $occurredAt = CarbonImmutable::now();
        $write = fn () => $this->write($kind, fn (): array => [
            ...$this->common($occurredAt, $kind, $userId, $reasonCode, $targetPublicId, $ipAddress, $userAgent, $details),
            'actor_user_id' => $actorUserId,
            'route_name' => $routeName === null ? null : Redaction::neutralize($routeName),
            'identifier_hash' => match (true) {
                $identifier !== null => SecurityLogHash::identifier($identifier),
                $providerSubject !== null => SecurityLogHash::subject($providerSubject),
                default => null,
            },
        ]);

        if (! Transactions::applicationOpen()) {
            $write();

            return;
        }

        // Called inside a business transaction: written once it ends, committed or rolled back, so the
        // event never waits on the transaction's locks nor names a row it has not committed.
        $written = false;
        $once = function () use (&$written, $write): void {
            if (! $written) {
                $written = true;
                $write();
            }
        };

        DB::afterCommit($once);
        DB::afterRollBack($once);
    }

    /**
     * Writes an event now, before the step answers, and throws when it cannot be written: the second-factor
     * limit's TWO_FACTOR_FAILED and TWO_FACTOR_COOLDOWN_STARTED, which the limit's durable floor is read from,
     * so a step whose event is lost is refused (SECURITY AU-05; fail closed). Called outside any transaction.
     *
     * @param  array<string, mixed>  $details
     *
     * @throws SecurityEventNotWritten
     */
    public function recordDurably(SecurityEventKind $kind, int $userId, ?string $reasonCode = null, ?string $routeName = null, ?string $ipAddress = null, ?string $userAgent = null, array $details = []): void
    {
        if (Transactions::applicationOpen()) {
            throw new LogicException("{$kind->value} is written before the step answers, outside its transaction (AU-05).");
        }

        $occurredAt = CarbonImmutable::now();
        $this->write($kind, fn (): array => [
            ...$this->common($occurredAt, $kind, $userId, $reasonCode, null, $ipAddress, $userAgent, $details),
            'actor_user_id' => null,
            'route_name' => $routeName === null ? null : Redaction::neutralize($routeName),
            'identifier_hash' => null,
        ], strict: true);
    }

    /**
     * Writes one of the two TECH-022 kinds once its command's outcome is durable: after the open
     * transaction commits — never if it rolls back — or at once when none is open, as one insert under
     * the partial UNIQUE key (`event_kind`, `command_id`, `related_public_id`) with ON CONFLICT DO NOTHING,
     * so a repeated attempt adds nothing. It references no row of the matched records, which are plain
     * values. Never throws once written; the caller runs it after the response where it can (LG-02).
     *
     * @param  array<string, mixed>  $details  the refused batch's declared kind, cutoff and scope, for display only
     */
    public function recordUndisclosed(
        SecurityEventKind $kind,
        int $userId,
        string $commandId,
        string $targetEntityType,
        string $targetPublicId,
        string $relatedEntityType,
        string $relatedPublicId,
        ?int $companyId,
        ?int $relatedCompanyId,
        string $reasonCode,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $details = [],
    ): void {
        if (! $kind->isUndisclosed()) {
            throw new LogicException("{$kind->value} is written by record().");
        }

        $occurredAt = CarbonImmutable::now();

        DB::afterCommit(fn () => $this->write($kind, fn (): array => [
            ...$this->common($occurredAt, $kind, $userId, $reasonCode, $targetPublicId, $ipAddress, $userAgent, $details),
            'command_id' => $commandId,
            'target_entity_type' => $targetEntityType,
            'related_entity_type' => $relatedEntityType,
            'related_public_id' => $relatedPublicId,
            'company_id' => $companyId,
            'related_company_id' => $relatedCompanyId,
        ], ignoreConflict: true));
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function common(CarbonImmutable $occurredAt, SecurityEventKind $kind, ?int $userId, ?string $reasonCode, ?string $targetPublicId, ?string $ipAddress, ?string $userAgent, array $details): array
    {
        $format = 'Y-m-d H:i:s.uP';

        return [
            // The attempt's instant, and the instant the event was written after its transaction ended.
            'occurred_at' => $occurredAt->utc()->format($format),
            'event_kind' => $kind->value,
            'user_id' => $userId,
            'reason_code' => $reasonCode === null ? null : Redaction::neutralize($reasonCode),
            'target_public_id' => $targetPublicId,
            'ip_hash' => $ipAddress === null ? null : SecurityLogHash::address($ipAddress),
            'user_agent_summary' => UserAgentSummary::of($userAgent),
            'correlation_id' => CorrelationId::current(),
            'details' => $this->details($details),
            'recorded_at' => CarbonImmutable::now()->utc()->format($format),
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function details(array $details): ?string
    {
        $kept = [];
        $dropped = [];

        foreach ($details as $key => $value) {
            if (! in_array($key, self::DETAIL_KEYS, true) || Redaction::isSensitiveKey($key, request: true)) {
                $dropped[] = Redaction::neutralize((string) $key);

                continue;
            }

            $kept[$key] = is_array($value) ? array_map($this->detailValue(...), $value) : $this->detailValue($value);
        }

        if ($dropped !== []) {
            Log::warning('Security event details outside the whitelist were dropped (LG-02).', ['keys' => $dropped]);
        }

        return $kept === [] ? null : json_encode($kept, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function detailValue(mixed $value): string|int|bool|null
    {
        return match (true) {
            $value === null, is_bool($value), is_int($value) => $value,
            is_string($value) => mb_substr(Redaction::neutralize($value), 0, self::MAXIMUM_DETAIL_LENGTH),
            default => throw new InvalidArgumentException(sprintf('A security event detail is a short scalar, not %s.', get_debug_type($value))),
        };
    }

    /**
     * @param  callable(): array<string, mixed>  $row
     */
    private function write(SecurityEventKind $kind, callable $row, bool $ignoreConflict = false, bool $strict = false): void
    {
        try {
            $values = $row();
            $name = config()->string('security.event_connection', self::CONNECTION);
            $connection = DB::connection($name);

            $connection->transaction(function (ConnectionInterface $connection) use ($name, $values, $ignoreConflict): void {
                if ($name !== DB::getDefaultConnection()) {
                    $connection->unprepared("set local lock_timeout = '2s'; set local statement_timeout = '5s'");
                }

                $table = $connection->table('security_events');
                $ignoreConflict ? $table->insertOrIgnore($values) : $table->insert($values);
            });
        } catch (Throwable $e) {
            if ($strict) {
                throw new SecurityEventNotWritten($kind->value, previous: $e);
            }

            Log::error('A security event could not be written (LG-02).', [
                'event_kind' => $kind->value,
                'correlation_id' => CorrelationId::current(),
                'error' => $e::class,
                'error_code' => $e->getCode(),
            ]);
        }
    }
}
