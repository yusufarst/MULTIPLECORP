<?php

namespace App\Support\SecurityLog;

use RuntimeException;

/** A security event that had to be written before its step answers could not be (SECURITY AU-05; fail closed). */
final class SecurityEventNotWritten extends RuntimeException
{
    public function __construct(string $kind, ?\Throwable $previous = null)
    {
        parent::__construct("The security event {$kind} could not be written; the step is refused.", 0, $previous);
    }
}
