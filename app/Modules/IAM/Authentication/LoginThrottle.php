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
 * and a success clears its own key. Each attempt takes its place in both counts with one atomic increment before
 * its password is verified, so a burst of parallel attempts gets no more than the limit (H6-07; SM-14); a success,
 * or an attempt that verified nothing, gives its place back. Its store fails closed: an unreadable store refuses
 * the attempt.
 */
final class LoginThrottle
{
    public const FAILURES_PER_MINUTE = 5;

    public const FAILURES_PER_ADDRESS = 30;

    public const ALERT_AFTER = 20;

    public const MAXIMUM_COOLDOWN = 900;

    private const STORE = 'redis';

    public function __construct(private readonly SecurityEventRecorder $events) {}

    /**
     * Takes this attempt's place in the identifier's and the address's counts; answers the seconds to wait when a
     * cooldown applies or the counts are full, 0 when the attempt may go ahead.
     */
    public function reserve(Request $request, string $identifier): int
    {
        try {
            $store = $this->store();
            $now = now()->getTimestamp();
            $until = max(
                self::integer($store->get($this->key('until', $identifier, $request))),
                self::integer($store->get($this->key('address-until', null, $request))),
            );

            if ($until > $now) {
                return $until - $now;
            }

            $attempts = $this->hit($store, $this->key('minute', $identifier, $request), 60);
            $fromAddress = $this->hit($store, $this->key('address', null, $request), self::MAXIMUM_COOLDOWN);

            if ($attempts > self::FAILURES_PER_MINUTE || $fromAddress > self::FAILURES_PER_ADDRESS) {
                $this->release($request, $identifier);

                return 60;
            }

            return 0;
        } catch (Throwable $e) {
            Log::error('The sign-in limiter store is unavailable; the attempt is refused (AU-05; H6-07).', ['exception' => $e::class]);

            return 60;
        }
    }

    /** One failed attempt, its place already taken; returns the cooldown it starts, 0 when none. */
    public function failed(Request $request, string $identifier): int
    {
        try {
            $store = $this->store();
            $now = now()->getTimestamp();
            $cooldown = 0;
            $until = $this->key('until', $identifier, $request);

            // `add` lets one of several concurrent fifth failures start the cooldown and count its strike.
            if (self::integer($store->get($this->key('minute', $identifier, $request))) >= self::FAILURES_PER_MINUTE && $store->add($until, $now + 60, 60)) {
                $strikes = $this->hit($store, $this->key('strikes', $identifier, $request), 3600);
                $cooldown = (int) min(60 * (1 << max(0, $strikes - 1)), self::MAXIMUM_COOLDOWN);
                $store->put($until, $now + $cooldown, $cooldown);
                $store->forget($this->key('minute', $identifier, $request));
            }

            if (self::integer($store->get($this->key('address', null, $request))) >= self::FAILURES_PER_ADDRESS
                && $store->add($this->key('address-until', null, $request), $now + self::MAXIMUM_COOLDOWN, self::MAXIMUM_COOLDOWN)) {
                $store->forget($this->key('address', null, $request));
                $cooldown = self::MAXIMUM_COOLDOWN;
            }

            if ($this->hit($store, 'login:hour:'.self::keyed($identifier), 3600) === self::ALERT_AFTER + 1) {
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

    /**
     * A success clears its own key — the identifier at this address — and gives its place in the address's count
     * back, never the address's failures or the hour's count.
     */
    public function succeeded(Request $request, string $identifier): void
    {
        try {
            $store = $this->store();

            foreach (['minute', 'strikes', 'until'] as $kind) {
                $store->forget($this->key($kind, $identifier, $request));
            }

            $this->giveBack($store, $this->key('address', null, $request));
        } catch (Throwable $e) {
            Log::error('The sign-in limiter store is unavailable; a success is not cleared (AU-05; H6-07).', ['exception' => $e::class]);
        }
    }

    /** An attempt that verified nothing — refused by the counts or by H6-12's cap — gives its places back. */
    public function release(Request $request, string $identifier): void
    {
        try {
            $store = $this->store();
            $this->giveBack($store, $this->key('minute', $identifier, $request));
            $this->giveBack($store, $this->key('address', null, $request));
        } catch (Throwable) {
            // A place not given back only makes the limiter stricter.
        }
    }

    /** The client address a key counts: an IPv6 address by its /64 prefix, an IPv4-mapped one as its IPv4 (AU-05). */
    public static function addressKey(?string $address): string
    {
        if ($address === null || $address === '') {
            return 'none';
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = inet_pton($address);

            if ($packed === false) {
                return $address;
            }

            if (str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
                return (string) inet_ntop(substr($packed, 12));
            }

            return bin2hex(substr($packed, 0, 8)).'::/64';
        }

        return $address;
    }

    private function key(string $kind, ?string $identifier, Request $request): string
    {
        $address = self::addressKey($request->ip());

        return $identifier === null
            ? "login:{$kind}:".self::keyed($address)
            : "login:{$kind}:".self::keyed($identifier.'|'.$address);
    }

    /** A key names its identifier and address only by a keyed hash, which a dictionary cannot reverse (LG-03). */
    private static function keyed(string $value): string
    {
        $key = config('app.key');

        return hash_hmac('sha256', $value, is_string($key) ? $key : '');
    }

    private function hit(Repository $store, string $key, int $seconds): int
    {
        $store->add($key, 0, $seconds);

        return (int) $store->increment($key);
    }

    /** A place given back to a count that still exists — never a new key without its expiry. */
    private function giveBack(Repository $store, string $key): void
    {
        if ($store->has($key)) {
            $store->decrement($key);
        }
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
