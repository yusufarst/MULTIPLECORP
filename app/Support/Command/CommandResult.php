<?php

namespace App\Support\Command;

/** What a committed action produced: the result's type and id that `command_log` records (CI-06). */
final class CommandResult
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?int $id = null,
    ) {}
}
