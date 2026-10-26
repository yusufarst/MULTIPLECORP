<?php

namespace App\Modules\IAM\Authentication;

/** One submission's place in an account's second-factor count: its identifier and the time it took the place (AU-05). */
final class SecondFactorPlace
{
    public function __construct(
        public readonly int $userId,
        public readonly string $submission,
        public readonly int $placeTime,
    ) {}
}
