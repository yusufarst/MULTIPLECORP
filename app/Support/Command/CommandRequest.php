<?php

namespace App\Support\Command;

/**
 * What a command brings to the envelope, validated in phase A (WS-01; CI-01, CI-02): its identity, its type
 * — the action code it records —, its actor, its company (CS-03; null for a command without one), the
 * capability it exercises, its fingerprint and its transaction class.
 */
final class CommandRequest
{
    public function __construct(
        public readonly string $commandId,
        public readonly string $commandType,
        public readonly int $actorUserId,
        public readonly ?int $companyId,
        public readonly string $capability,
        public readonly string $fingerprint,
        public readonly TransactionClass $transactionClass = TransactionClass::Interactive,
    ) {}
}
