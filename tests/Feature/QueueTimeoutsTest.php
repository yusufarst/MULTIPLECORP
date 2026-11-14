<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

// TL-04 (TASK-072; INFRASTRUCTURE §4.1; PERFORMANCE SZ-02; CONCURRENCY_IDEMPOTENCY §15): each job's timeout stays shorter
// than the queue's retry-after, so a job still running is never handed to the second worker — which would count it as a
// further attempt and fail a one-attempt job while the first worker still works on it.

it('keeps every queued job\'s timeout below the queue\'s retry-after', function () {
    $retryAfter = config()->integer('queue.connections.redis.retry_after');
    // The rendition's timeout is configuration, set when the job is made (RenderDocument).
    $timeouts = ['files.renditions.timeout_seconds' => config()->integer('files.renditions.timeout_seconds', 120)];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], Str::after(str_replace('\\', '/', $file->getPathname()), str_replace('\\', '/', app_path()).'/'));

        if (! class_exists($class) || ! is_subclass_of($class, ShouldQueue::class)) {
            continue;
        }

        $timeout = (new ReflectionClass($class))->getDefaultProperties()['timeout'] ?? null;

        if (is_int($timeout)) {
            $timeouts[$class] = $timeout;
        }
    }

    expect(array_filter($timeouts, fn (int $timeout): bool => $timeout >= $retryAfter))->toBe([])
        ->and(count($timeouts))->toBeGreaterThan(4);
});
