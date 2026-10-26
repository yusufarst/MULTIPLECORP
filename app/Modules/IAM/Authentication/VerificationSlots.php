<?php

namespace App\Modules\IAM\Authentication;

use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * H6-12's cap on concurrent password verifications and hashes (TM-58; PF-39), two funnels of atomic locks on the
 * limiter store: unauthenticated work — the login's verification, AU-04's dummy verification and a credential
 * link's new hash — shares two slots and waits at most 250 ms for one, so a flood's waiting requests cannot hold
 * the worker pool; authenticated work — the step-up confirmation, and the password change of TASK-023 — has one
 * slot of its own and waits up to 2 s. A slot expires after 5 s if its holder dies. A request without a slot is
 * refused with the generic throttled answer and one THROTTLED event a minute; an unavailable store refuses too
 * (fail closed). The numbers are starting values P9 tunes (H9-11; HO-26).
 */
final class VerificationSlots
{
    public const UNAUTHENTICATED_SLOTS = 2;

    public const UNAUTHENTICATED_WAIT_MS = 250;

    public const AUTHENTICATED_SLOTS = 1;

    public const AUTHENTICATED_WAIT_MS = 2000;

    public const RELEASE_AFTER_SECONDS = 5;

    private const POLL_MS = 25;

    public function __construct(private readonly SecurityEventRecorder $events) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     *
     * @throws VerificationRefused
     */
    public function unauthenticated(string $routeName, Closure $work): mixed
    {
        return $this->run('unauthenticated', self::UNAUTHENTICATED_SLOTS, self::UNAUTHENTICATED_WAIT_MS, $routeName, $work);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     *
     * @throws VerificationRefused
     */
    public function authenticated(string $routeName, Closure $work): mixed
    {
        return $this->run('authenticated', self::AUTHENTICATED_SLOTS, self::AUTHENTICATED_WAIT_MS, $routeName, $work);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function run(string $funnel, int $slots, int $waitMs, string $routeName, Closure $work): mixed
    {
        $slot = null;

        try {
            $store = $this->store();
            $owner = Str::random(20);
            $deadline = hrtime(true) + $waitMs * 1_000_000;

            while (($slot = $this->acquire($store, $funnel, $slots, $owner)) === null && hrtime(true) < $deadline) {
                usleep(self::POLL_MS * 1000);
            }
        } catch (Throwable $e) {
            Log::error('The password-verification cap\'s store is unavailable; the verification is refused (H6-12; H6-07).', ['exception' => $e::class]);

            throw new VerificationRefused;
        }

        if ($slot === null) {
            $this->refused($store, $funnel, $routeName);

            throw new VerificationRefused;
        }

        try {
            return $work();
        } finally {
            $slot->release();
        }
    }

    private function acquire(Repository $store, string $funnel, int $slots, string $owner): ?Lock
    {
        $locks = $store->getStore();

        if (! $locks instanceof LockProvider) {
            return null;
        }

        for ($i = 1; $i <= $slots; $i++) {
            $lock = $locks->lock("verification-slot:{$funnel}:{$i}", self::RELEASE_AFTER_SECONDS, $owner);

            if ($lock->get() === true) {
                return $lock;
            }
        }

        return null;
    }

    /** One THROTTLED event a minute per funnel, the refusals between them aggregated in its count (H6-07). */
    private function refused(Repository $store, string $funnel, string $routeName): void
    {
        try {
            $count = $store->increment("verification-slot:{$funnel}:refused");

            if ($store->add("verification-slot:{$funnel}:reported", true, 60)) {
                $store->forget("verification-slot:{$funnel}:refused");
                $this->events->record(SecurityEventKind::Throttled, reasonCode: 'VERIFICATION_CAP', routeName: $routeName, details: ['funnel' => $funnel, 'refused' => is_numeric($count) ? (int) $count : 1]);
            }
        } catch (Throwable) {
            // The refusal stands whether or not it could be counted.
        }
    }

    private function store(): Repository
    {
        return Cache::store(config('cache.default') === 'array' ? 'array' : 'redis');
    }
}
