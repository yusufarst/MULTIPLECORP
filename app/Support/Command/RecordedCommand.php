<?php

namespace App\Support\Command;

use stdClass;

/** A terminal `command_log` row, as a replay reads it (CI-05). */
final class RecordedCommand
{
    public function __construct(
        public readonly string $commandType,
        public readonly ?int $actorUserId,
        public readonly ?int $companyId,
        public readonly string $fingerprint,
        public readonly string $state,
        public readonly ?string $resultType,
        public readonly ?int $resultId,
        public readonly ?string $outcomeCode,
    ) {}

    public static function of(stdClass $row): self
    {
        $int = fn (mixed $value): ?int => is_numeric($value) ? (int) $value : null;
        $string = fn (mixed $value): ?string => is_string($value) ? $value : null;

        return new self(
            (string) $string($row->command_type),
            $int($row->actor_user_id),
            $int($row->company_id),
            (string) $string($row->payload_fingerprint),
            (string) $string($row->state),
            $string($row->result_entity_type),
            $int($row->result_entity_id),
            $string($row->outcome_code),
        );
    }

    /** The recorded outcome (CI-05). */
    public function outcome(): Outcome
    {
        return match ($this->state) {
            Outcome::COMMITTED => Outcome::committed($this->resultType, $this->resultId, replayed: true),
            Outcome::REJECTED => Outcome::rejected((string) $this->outcomeCode, replayed: true),
            default => Outcome::conflict((string) $this->outcomeCode, replayed: true),
        };
    }
}
