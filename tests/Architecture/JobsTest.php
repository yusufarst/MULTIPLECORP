<?php

use App\Support\Jobs\PlatformJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Schema;

// TASK-022 (CONCURRENCY_IDEMPOTENCY §15; ARCHITECTURE §11; HO-20): every queued job of the application carries the
// shared mechanics — after-commit dispatch, identifiers-only payload, its format version — by extending PlatformJob;
// none uses the framework's batching (the scan rule `job-batching`) and no job-batch table exists.

it('builds every job of a module on the shared mechanics', function () {
    $jobs = glob(app_path('Modules/*/Jobs/*.php')) ?: [];

    expect($jobs)->not->toBe([]);

    foreach ($jobs as $path) {
        $class = 'App\\Modules\\'.basename(dirname($path, 2)).'\\Jobs\\'.basename($path, '.php');

        expect(is_subclass_of($class, PlatformJob::class))->toBeTrue("{$class} extends PlatformJob")
            ->and(is_subclass_of($class, ShouldQueue::class))->toBeTrue();
    }
});

it('creates no job-batch table, the framework\'s batching being unused (JB-07; HO-20)', function () {
    $migrations = implode("\n", array_map(fn (string $path): string => (string) file_get_contents($path), glob(database_path('migrations/*.php')) ?: []));

    expect($migrations)->not->toContain('job_batches')
        ->and(Schema::getConnection()->getDriverName())->toBe('pgsql');
});
