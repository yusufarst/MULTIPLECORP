<?php

namespace App\Modules\IAM\Authentication;

use RuntimeException;

/** A second-factor step refused without its code being evaluated — a cooldown, a full count or a limit that cannot be applied (AU-05). */
final class SecondFactorRefused extends RuntimeException
{
    public function __construct(public readonly int $seconds)
    {
        parent::__construct("The second-factor step is refused for {$seconds} s (AU-05).");
    }
}
