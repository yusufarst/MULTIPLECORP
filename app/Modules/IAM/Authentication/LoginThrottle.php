<?php

namespace App\Modules\IAM\Authentication;

use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SECURITY AU-05's throttling without lockout (D-SEC-04; WS-12), on Valkey: per normalized identifier and client
 * address, five failures a minute, then a cooldown that doubles up to 15 minutes; per address, 30 failures in 15
 * minutes, then 15 minutes' cooldown; per identifier across addresses no block — the 21st failure in an hour raises
 * a security event and an alert (LG-07). An IPv6 address counts by its /64. No account is ever locked or disabled,
 * and a success clears its own key. Its store fails closed (H6-07): an unreadable store refuses the attempt.
 */
final class LoginThrottle
{
    public const FAILURES_PER_MINUTE = 5;

    public const FAILURES_PER_ADDRESS = 30;

    public const ALERT_AFTER = 20;

    public const MAXIMUM_COOLDOWN = 900;

    private const STORE = 'redis';

    public function __construct(private readonly SecurityEventRecorder $events) {}

    /** The seconds left on a cooldown that applies to this attempt, 0 when it may go ahead. */
    public function blockedFor(Request $request, string $identifier): int
    {
        try {
            $now = time();
            $until = max(
                self::integer($this->store()->get($this->key('until', $identifier, $request))),
                self::integer($this->store()->get($this->key('address-until', null, $request))),
            );

            return max(0, $until - $now);
        } catch (Throwable $e) {
            Log::error('The sign-in limiter store is unavailable; the attempt is refused (AU-05; H6-07).', ['exception' => $e::class]);

            return 60;
        }
    }

    /** One failed attempt; returns the cooldown it starts, 0 when none. */
    public function failed(Request $request, string $identifier): int
    {
        try {
            $store = $this->store();
            $now = time();
            $cooldown = 0;

            $failures = $this->hit($store, $this->key('minute', $identifier, $request), 60);

            if ($failures >= self::FAILURES_PER_MINUTE) {
                $strikes = $this->hit($store, $this->key('strikes', $identifier, $request), 3600);
                $cooldown = (int) min(60 * (1 << max(0, $strikes - 1)), self::MAXIMUM_COOLDOWN);
                $store->put($this->key('until', $identifier, $request), $now + $cooldown, $cooldown);
                $store->forget($this->key('minute', $identifier, $request));
            }

            if ($this->hit($store, $this->key('address', null, $request), self::MAXIMUM_COOLDOWN) >= self::FAILURES_PER_ADDRESS) {
                $store->put($this->key('address-until', null, $request), $now + self::MAXIMUM_COOLDOWN, self::MAXIMUM_COOLDOWN);
                $store->forget($this->key('address', null, $request));
                $cooldown = max($cooldown, self::MAXIMUM_COOLDOWN);
            }

            if ($this->hit($store, 'login:hour:'.hash('sha256', $identifier), 3600) === self::ALERT_AFTER + 1) {
                Log::alert('More than 20 failed sign-ins for one identifier in an hour (AU-05; LG-07).');
                $this->events->record(SecurityEventKind::Throttled, reasonCode: 'IDENTIFIER_HOURLY', routeName: 'login.store', identifier: $identifier, ipAddress: $request->ip(), userAgent: $request->userAgent());
            }

            if ($cooldown > 0) {
                $this->events->record(SecurityEventKind::Throttled, reasonCode: 'LOGIN_COOLDOWN', routeName: 'login.store', identifier: $identifier, ipAddress: $request->ip(), userAgent: $request->userAgent(), details: ['cooldown_seconds' => $cooldown]);
            }

            return $cooldown;
        } catch (Throwable $e) {
            Log::error('The sign-in limiter store is unavailable (AU-05; H6-07).', ['exception' => $e::class]);

            return 60;
        }
    }

    /** A success clears its own key — the identifier at this address —, never the address's or the hour's count. */
    public function succeeded(Request $request, string $identifier): void
    {
        $store = $this->store();

        foreach (['minute', 'strikes', 'until'] as $kind) {
            $store->forget($this->key($kind, $identifier, $request));
        }
    }

    /** The client address a key counts: an IPv6 address by its /64 prefix (AU-05). */
    public static function addressKey(?string $address): string
    {
        if ($address === null || $address === '') {
            return 'none';
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = inet_pton($address);

            return $packed === false ? $address : bin2hex(substr($packed, 0, 8)).'::/64';
        }

        return $address;
    }

    private function key(string $kind, ?string $identifier, Request $request): string
    {
        $address = self::addressKey($request->ip());

        return $identifier === null
            ? "login:{$kind}:".hash('sha256', $address)
            : "login:{$kind}:".hash('sha256', $identifier.'|'.$address);
    }

    private function hit(Repository $store, string $key, int $seconds): int
    {
        $store->add($key, 0, $seconds);

        return (int) $store->increment($key);
    }

    private static function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function store(): Repository
    {
        return Cache::store(config('cache.default') === 'array' ? 'array' : self::STORE);
    }
}
