<?php

namespace App\Support\Audit;

use App\Support\CorrelationId;
use App\Support\Database\Transactions;
use App\Support\Redaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The business audit writer (SECURITY LG-01; BR-XC-01–BR-XC-03; DATABASE §23; ARCHITECTURE §5 SF-CMD step 9):
 * an action calls it inside its own transaction, so the audit row commits or rolls back with the change it
 * records, and an audit failure rolls the command back. It refuses to write outside a transaction. The
 * actor is the individual account (BR-XC-02) — NULL with authority SYSTEM for the operator's commands —
 * and a backdated command keeps its business date beside the recorded instant (DIR-018). It is shared in
 * Support, so every module's commands — IAM's identity steps among them — call it (ARCHITECTURE §4; DIR-093).
 */
final class AuditRecorder
{
    /**
     * @param  string  $action  the command's action code
     * @param  string|null  $commandId  the command identity of CONCURRENCY_IDEMPOTENCY, a UUID
     * @param  string|null  $businessDate  the command's business date, `Y-m-d`, kept in the metadata beside `recorded_at`
     * @param  array<string, mixed>  $metadata  further non-sensitive context, such as an operator's identity
     * @param  ConnectionInterface|null  $connection  the command's connection where it is not the default — the
     *                                                administration role's for `GuardMaintenance` (TX-08)
     */
    public function record(
        string $action,
        AuthorityClass $authorityClass,
        ?int $actorUserId,
        ?int $companyId,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $entityPublicId = null,
        ?string $reason = null,
        ?string $commandId = null,
        ?AuditChange $change = null,
        ?AuditLinkage $linkage = null,
        ?string $businessDate = null,
        array $metadata = [],
        ?ConnectionInterface $connection = null,
    ): void {
        if ($connection !== null ? $connection->transactionLevel() === 0 : ! Transactions::applicationOpen()) {
            throw new LogicException('The business audit is written inside its command\'s transaction (LG-01; DATABASE §23).');
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_.]*$/', $action) !== 1) {
            throw new InvalidArgumentException('An audit action is a code of letters, digits, underscores and dots.');
        }

        if ($actorUserId === null && $authorityClass !== AuthorityClass::System) {
            throw new InvalidArgumentException('Only a SYSTEM event is written without an actor (LG-01).');
        }

        $metadata = Redaction::redact([...$metadata, ...($linkage?->toArray() ?? [])]);

        if ($businessDate !== null) {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $businessDate, $day) !== 1 || ! checkdate((int) $day[2], (int) $day[3], (int) $day[1])) {
                throw new InvalidArgumentException('A business date is a Y-m-d day.');
            }

            $metadata['business_date'] = $businessDate;
        }

        // The application clock at microsecond precision: the instant is UTC, its business day WIB (GAP-016).
        $now = CarbonImmutable::now()->utc()->format('Y-m-d H:i:s.uP');

        ($connection ?? DB::connection())->table('audit_events')->insert([
            'occurred_at' => $now,
            'actor_user_id' => $actorUserId,
            'company_id' => $companyId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_public_id' => $entityPublicId,
            'reason' => $reason,
            'correlation_id' => CorrelationId::current(),
            'command_id' => $commandId,
            'authority_class' => $authorityClass->value,
            'before' => self::json($change?->before),
            'after' => self::json($change?->after),
            'metadata' => self::json($metadata === [] ? null : $metadata),
            'recorded_at' => $now,
        ]);
    }

    /**
     * @param  array<array-key, mixed>|null  $values
     */
    private static function json(?array $values): ?string
    {
        return $values === null ? null : json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
