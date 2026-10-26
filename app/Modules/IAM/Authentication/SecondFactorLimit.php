<?php

namespace App\Modules\IAM\Authentication;

use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/**
 * SECURITY AU-05's second-factor limit (DIR-044 R-01; DIR-048 R2-02; RV-12, RV-13): one failed-code count per
 * account, shared across every client address and every step that takes a TOTP or recovery code — the
 * enrolment's confirmation excepted —, over a rolling attempt window of 15 minutes. Each submission takes its
 * place before its code is evaluated and gives it back only when the code is accepted, so the count holds
 * failures alone and concurrent submissions never exceed it. The fifth failure in the window is a breach: the
 * pending challenge is void and every covered step of the account is refused, unevaluated, for 1, 2, 4, 8 and
 * then 15 minutes by the number of breaches in the last 24 hours; the breach empties the count. A success resets
 * nothing. The limit never locks the account.
 *
 * The account's entry on the limiter store — its places, its failures and its breaches, each time-stamped and
 * trimmed by the operation, with no expiry of its own — is read and written in one operation under the store's
 * lock on that account, which makes each operation atomic for the account. Before a place is taken the entry is
 * brought up to the durable floor read from the account's TWO_FACTOR_FAILED and TWO_FACTOR_COOLDOWN_STARTED
 * events (IX-19): each missing failure and breach inserted at its own time, keyed by its submission identifier
 * so none counts twice, and no failure seeded into a window from a floor read before the store's latest breach.
 * A restart or an eviction of the store therefore never shortens a cooldown or lowers the count below the
 * record. The failure and breach events are written before the step answers; a floor that cannot be read, a
 * store that cannot be reached or an event that cannot be written refuses the step (fail closed).
 *
 * @phpstan-type Entry array{places: array<string, int>, failed: array<string, int>, breaches: array<string, array{0: int, 1: int}>, refused: int, reported_at: int}
 */
final class SecondFactorLimit
{
    public const THRESHOLD = 5;

    public const WINDOW_SECONDS = 900;

    public const ESCALATION_SECONDS = 86400;

    /** The cooldown of the first, second, third, fourth and every later breach within the escalation period. */
    public const COOLDOWNS = [60, 120, 240, 480, 900];

    public const PATH_PASSWORD = 'PASSWORD';

    public const PATH_GOOGLE = 'GOOGLE';

    public const PATH_SESSION_STEP = 'SESSION_STEP';

    private const REPORT_EVERY_MS = 60_000;

    public function __construct(private readonly SecurityEventRecorder $events) {}

    /**
     * Takes the submission's place in the account's count.
     *
     * @throws SecondFactorRefused during a cooldown, past the threshold, or when the limit cannot be applied
     */
    public function take(int $userId, Request $request): SecondFactorPlace
    {
        $submission = (string) Str::uuid7();

        try {
            $floor = $this->floor($userId);
            $now = self::nowMs();

            $result = $this->atomically($userId, function (array $entry) use ($floor, $submission, $now): array {
                $entry = self::trim($entry, $now);
                $storeBreach = self::latestBreach($entry);

                foreach ($floor['breaches'] as $id => $breach) {
                    $entry['breaches'][$id] ??= $breach;
                }

                $latest = self::latestBreach($entry);

                if ($floor['read_at'] >= $storeBreach) {
                    foreach ($floor['failures'] as $id => $placed) {
                        if ($placed > $latest && $placed >= $now - self::WINDOW_SECONDS * 1000 && ! isset($entry['failed'][$id])) {
                            $entry['failed'][$id] = $placed;
                            $entry['places'][$id] = $placed;
                        }
                    }
                }

                $until = self::cooldownUntil($entry);

                if ($until > $now) {
                    return [$entry, ['refused' => (int) ceil(($until - $now) / 1000)]];
                }

                if (count($entry['places']) >= self::THRESHOLD) {
                    return [$entry, ['refused' => 60]];
                }

                $entry['places'][$submission] = $now;

                return [$entry, ['placed' => $now]];
            });
        } catch (Throwable $e) {
            Log::error('The second-factor limit cannot be applied; the step is refused (AU-05; H6-07).', ['exception' => $e::class]);

            throw new SecondFactorRefused(60);
        }

        if (isset($result['refused'])) {
            $this->refusedInCooldown($userId, $request);

            throw new SecondFactorRefused((int) $result['refused']);
        }

        return new SecondFactorPlace($userId, $submission, (int) $result['placed']);
    }

    /** An accepted code gives its place back; nothing else changes (AU-05: a success resets nothing). */
    public function accepted(SecondFactorPlace $place): void
    {
        try {
            $this->atomically($place->userId, function (array $entry) use ($place): array {
                unset($entry['places'][$place->submission]);

                return [$entry, []];
            });
        } catch (Throwable $e) {
            // A place not given back leaves the count stricter until it leaves the window.
            Log::warning('A second-factor place could not be given back (AU-05).', ['exception' => $e::class]);
        }
    }

    /**
     * A failed code keeps its place; TWO_FACTOR_FAILED is written before the step answers, and the fifth failure
     * of the window starts the cooldown with TWO_FACTOR_COOLDOWN_STARTED. Answers whether this failure breached,
     * which voids the pending challenge or step.
     *
     * @throws SecondFactorRefused when the failure cannot be counted or recorded (fail closed)
     */
    public function failed(SecondFactorPlace $place, string $path, Request $request): bool
    {
        try {
            $route = $request->route();
            $common = ['userId' => $place->userId, 'routeName' => $route instanceof Route ? $route->getName() : null, 'ipAddress' => $request->ip(), 'userAgent' => $request->userAgent()];
            // Written first: the durable floor holds every evaluated failure, whatever the store does next.
            $this->events->recordDurably(SecurityEventKind::TwoFactorFailed, ...$common, details: ['path' => $path, 'submission_id' => $place->submission, 'place_time' => $place->placeTime]);
            $now = self::nowMs();
            $breach = $this->atomically($place->userId, function (array $entry) use ($place, $now): array {
                $entry = self::trim($entry, $now);

                // A place emptied by a breach belongs to the breached window: its failure is recorded, not counted again.
                if (! isset($entry['places'][$place->submission])) {
                    return [$entry, []];
                }

                $entry['failed'][$place->submission] = $place->placeTime;
                $latest = self::latestBreach($entry);
                $inWindow = array_filter($entry['failed'], fn (int $placed): bool => $placed > $latest);

                if (count($inWindow) < self::THRESHOLD) {
                    return [$entry, []];
                }

                $level = count($entry['breaches']) + 1;
                $seconds = self::COOLDOWNS[min($level, count(self::COOLDOWNS)) - 1];
                $entry['breaches'][$place->submission] = [$now, $seconds];
                $entry['places'] = [];
                $entry['failed'] = [];

                return [$entry, ['level' => $level, 'seconds' => $seconds]];
            });

            if ($breach === []) {
                return false;
            }

            $this->events->recordDurably(SecurityEventKind::TwoFactorCooldownStarted, ...$common, details: [
                'path' => $path, 'submission_id' => $place->submission, 'escalation_level' => $breach['level'], 'cooldown_seconds' => $breach['seconds'],
            ]);
            Log::alert('A second-factor cooldown started (AU-05; LG-07).', ['user_id' => $place->userId, 'path' => $path, 'escalation_level' => $breach['level'], 'cooldown_seconds' => $breach['seconds']]);

            return true;
        } catch (Throwable $e) {
            Log::error('A second-factor failure could not be counted or recorded; the step is refused (AU-05).', ['exception' => $e::class]);

            throw new SecondFactorRefused(60);
        }
    }

    /**
     * The durable floor (IX-19): the account's breaches of the last 24 hours and its failures placed since the
     * later of 15 minutes ago and its latest breach, from the security events that are never deleted.
     *
     * @return array{read_at: int, failures: array<string, int>, breaches: array<string, array{0: int, 1: int}>}
     */
    private function floor(int $userId): array
    {
        $readAt = self::nowMs();
        $rows = DB::table('security_events')->where('user_id', $userId)
            ->whereIn('event_kind', [SecurityEventKind::TwoFactorFailed->value, SecurityEventKind::TwoFactorCooldownStarted->value])
            ->where('occurred_at', '>=', now()->subSeconds(self::ESCALATION_SECONDS))
            ->orderBy('occurred_at')->get(['event_kind', 'occurred_at', 'details']);

        $failures = [];
        $breaches = [];

        foreach ($rows as $row) {
            $details = is_string($row->details) ? json_decode($row->details, true) : null;
            $id = is_array($details) ? ($details['submission_id'] ?? null) : null;

            if (! is_string($id)) {
                continue;
            }

            if ($row->event_kind === SecurityEventKind::TwoFactorCooldownStarted->value) {
                // To the millisecond, as the places are: the breached window's own failures stay before it.
                $at = is_string($row->occurred_at) ? (int) CarbonImmutable::parse($row->occurred_at)->getPreciseTimestamp(3) : 0;
                $breaches[$id] = [$at, is_int($details['cooldown_seconds'] ?? null) ? $details['cooldown_seconds'] : self::COOLDOWNS[0]];
            } elseif (is_int($details['place_time'] ?? null)) {
                $failures[$id] = $details['place_time'];
            }
        }

        return ['read_at' => $readAt, 'failures' => $failures, 'breaches' => $breaches];
    }

    /** TWO_FACTOR_COOLDOWN_REFUSED, aggregated per account and minute with the number of refusals (H6-07). */
    private function refusedInCooldown(int $userId, Request $request): void
    {
        try {
            $now = self::nowMs();
            $report = $this->atomically($userId, function (array $entry) use ($now): array {
                $entry['refused']++;

                if ($entry['reported_at'] > $now - self::REPORT_EVERY_MS) {
                    return [$entry, []];
                }

                $count = $entry['refused'];
                $entry['refused'] = 0;
                $entry['reported_at'] = $now;

                return [$entry, ['count' => $count]];
            });

            if (isset($report['count'])) {
                $this->events->record(SecurityEventKind::TwoFactorCooldownRefused, userId: $userId, routeName: $request->route()?->getName(), ipAddress: $request->ip(), userAgent: $request->userAgent(), details: ['refusal_count' => $report['count']]);
            }
        } catch (Throwable) {
            // The refusal stands whether or not it could be counted.
        }
    }

    /**
     * One operation on the account's entry, under the store's lock on that account.
     *
     * @template T of array<string, int>
     *
     * @param  Closure(Entry): array{0: Entry, 1: T}  $operation
     * @return T
     */
    private function atomically(int $userId, Closure $operation): array
    {
        $store = $this->store();
        $locks = $store->getStore();
        $key = "two-factor-limit:{$userId}";

        if (! $locks instanceof LockProvider) {
            throw new LogicException('The second-factor limit needs a store with atomic locks (AU-05).');
        }

        /** @var T */
        return $locks->lock("{$key}:lock", 5)->block(2, function () use ($store, $key, $operation): array {
            $stored = $store->get($key);
            [$next, $result] = $operation(self::entry(is_string($stored) ? json_decode($stored, true) : null));
            $store->forever($key, json_encode($next, JSON_THROW_ON_ERROR));

            return $result;
        });
    }

    /** @return Entry */
    private static function entry(mixed $decoded): array
    {
        $raw = is_array($decoded) ? $decoded : [];
        $times = static function (mixed $list): array {
            $kept = [];

            foreach (is_array($list) ? $list : [] as $id => $at) {
                if (is_string($id) && is_int($at)) {
                    $kept[$id] = $at;
                }
            }

            return $kept;
        };
        $breaches = [];

        foreach (is_array($raw['breaches'] ?? null) ? $raw['breaches'] : [] as $id => $breach) {
            if (is_string($id) && is_array($breach) && is_int($breach[0] ?? null) && is_int($breach[1] ?? null)) {
                $breaches[$id] = [$breach[0], $breach[1]];
            }
        }

        return [
            'places' => $times($raw['places'] ?? null),
            'failed' => $times($raw['failed'] ?? null),
            'breaches' => $breaches,
            'refused' => is_int($raw['refused'] ?? null) ? $raw['refused'] : 0,
            'reported_at' => is_int($raw['reported_at'] ?? null) ? $raw['reported_at'] : 0,
        ];
    }

    /**
     * @param  Entry  $entry
     * @return Entry
     */
    private static function trim(array $entry, int $now): array
    {
        $since = $now - self::WINDOW_SECONDS * 1000;
        $entry['places'] = array_filter($entry['places'], fn (int $at): bool => $at >= $since);
        $entry['failed'] = array_filter($entry['failed'], fn (int $at): bool => $at >= $since);
        $entry['breaches'] = array_filter($entry['breaches'], fn (array $breach): bool => $breach[0] >= $now - self::ESCALATION_SECONDS * 1000);

        return $entry;
    }

    /** @param  Entry  $entry */
    private static function latestBreach(array $entry): int
    {
        $latest = 0;

        foreach ($entry['breaches'] as $breach) {
            $latest = max($latest, $breach[0]);
        }

        return $latest;
    }

    /** @param  Entry  $entry */
    private static function cooldownUntil(array $entry): int
    {
        $until = 0;

        foreach ($entry['breaches'] as $breach) {
            $until = max($until, $breach[0] + $breach[1] * 1000);
        }

        return $until;
    }

    private static function nowMs(): int
    {
        return (int) now()->getPreciseTimestamp(3);
    }

    private function store(): Repository
    {
        return Cache::store(config('cache.default') === 'array' ? 'array' : 'redis');
    }
}
