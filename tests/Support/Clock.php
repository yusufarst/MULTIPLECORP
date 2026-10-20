<?php

namespace Tests\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The controlled clock of FX-08 (CONCURRENCY_IDEMPOTENCY HO-19): freezes now() on one of its named
 * instants, in WIB. The framework's test case releases it after each test.
 */
final class Clock
{
    public static function freeze(string $instant): CarbonImmutable
    {
        $set = FixtureSets::load('FX-08');
        $value = $set['entries']['instants'][$instant] ?? throw new InvalidArgumentException("FX-08 has no instant {$instant}.");
        $now = CarbonImmutable::parse($value)->setTimezone($set['timezone']);

        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);

        return $now;
    }
}
