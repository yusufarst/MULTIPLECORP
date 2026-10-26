<?php

use App\Support\Jobs\PayloadFormat;
use App\Support\Jobs\PlatformJob;

// TL-03 (TASK-022; GAP-012; HO-31; CONCURRENCY_IDEMPOTENCY §15): a job's payload carries its format version; a worker
// that meets a newer one releases it back with a delay instead of failing it, runs the current and the previous
// format, and fails an older one; every job leaves only after its transaction commits.

final class FormatTwoJob extends PlatformJob
{
    public const int FORMAT = 2;

    public function handle(): void {}
}

function throughFormat(int $format): array
{
    $job = (new FormatTwoJob)->withFakeQueueInteractions();
    $job->format = $format;
    $ran = false;
    (new PayloadFormat)->handle($job, function () use (&$ran): void {
        $ran = true;
    });

    return [$job, $ran];
}

it('stamps the payload with its format and dispatches after commit', function () {
    $job = new FormatTwoJob;

    expect($job->format)->toBe(2)
        ->and($job->afterCommit)->toBeTrue()
        ->and($job->middleware()[0])->toBeInstanceOf(PayloadFormat::class);
});

it('releases a newer payload with a delay, runs the current and the previous, and fails an older one', function () {
    [$newer, $ranNewer] = throughFormat(3);
    [, $ranCurrent] = throughFormat(2);
    [, $ranPrevious] = throughFormat(1);
    [$older, $ranOlder] = throughFormat(0);

    $newer->assertReleased(PayloadFormat::RELEASE_DELAY_SECONDS);
    $older->assertFailed();

    expect($ranNewer)->toBeFalse()
        ->and($ranCurrent)->toBeTrue()
        ->and($ranPrevious)->toBeTrue()
        ->and($ranOlder)->toBeFalse();
});
