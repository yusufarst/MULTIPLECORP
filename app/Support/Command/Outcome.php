<?php

namespace App\Support\Command;

/**
 * A command's answer in the vocabulary of WORKFLOWS §1 (CONCURRENCY_IDEMPOTENCY §12): COMMITTED with its result's
 * type and id, REJECTED with its rule code, CONFLICT with its cause code, PENDING while background work runs and
 * FAILED when nothing committed and the same `command_id` may be sent again. `state` is the current in-scope
 * state the answer carries, never another record's data; `replayed` marks an answer read from `command_log`.
 */
final class Outcome
{
    public const COMMITTED = 'COMMITTED';

    public const REJECTED = 'REJECTED';

    public const CONFLICT = 'CONFLICT';

    public const PENDING = 'PENDING';

    public const FAILED = 'FAILED';

    /**
     * @param  array<string, mixed>  $state
     */
    private function __construct(
        public readonly string $kind,
        public readonly ?string $code = null,
        public readonly ?string $resultType = null,
        public readonly ?int $resultId = null,
        public readonly array $state = [],
        public readonly bool $replayed = false,
    ) {}

    public static function committed(?string $resultType, ?int $resultId, bool $replayed = false): self
    {
        return new self(self::COMMITTED, null, $resultType, $resultId, replayed: $replayed);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function rejected(string $rule, array $state = [], bool $replayed = false): self
    {
        return new self(self::REJECTED, $rule, state: $state, replayed: $replayed);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function conflict(string $cause, array $state = [], bool $replayed = false): self
    {
        return new self(self::CONFLICT, $cause, state: $state, replayed: $replayed);
    }

    /** FAILED: nothing committed, retriable under the same `command_id`; the reason is for the log, not the user. */
    public static function failed(string $reason): self
    {
        return new self(self::FAILED, $reason);
    }

    public static function pending(?string $resultType, ?int $resultId): self
    {
        return new self(self::PENDING, null, $resultType, $resultId);
    }

    /** Whether the outcome is a terminal one `command_log` records (CI-06). */
    public function isTerminal(): bool
    {
        return in_array($this->kind, [self::COMMITTED, self::REJECTED, self::CONFLICT], true);
    }
}
