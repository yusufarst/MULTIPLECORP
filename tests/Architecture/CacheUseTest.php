<?php

// SECURITY_TEST_MATRIX SM-13 and DP-10 (TASK-069; PERMISSIONS_MATRIX §11 DP-10; CP-04): the application caches no
// business record. Its cache store holds the limiters' counts — keyed by account and address (AU-05; WS-12; the
// limiters of the authentication steps, the step-up, search, scanning, uploads and exports, and on staging the CSP
// report's count per address, ROUTE-278) — and the scheduler's
// heartbeats, which name a task and a time and nothing of a company, so no company's data can reach another account
// through a cache key. A cache added later fails this test until its key-isolation case is written beside it.

/** The application files that reach the cache store, directly or through the rate limiter. */
function cacheUsers(): array
{
    $users = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file->getExtension() === 'php' && preg_match('/\bCache::|[^a-zA-Z_>]cache\(|Facades\\\\Cache;|RateLimiter::|->remember(Forever)?\(/', (string) file_get_contents($file->getPathname())) === 1) {
            $users[] = str_replace([app_path().DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], ['', '/'], $file->getPathname());
        }
    }

    sort($users);

    return $users;
}

it('reaches the cache store for the limiters and the heartbeats alone (DP-10; CP-04)', function () {
    expect(cacheUsers())->toBe([
        'Http/Controllers/RecordCspReport.php',
        'Modules/IAM/Authentication/AuthenticationServiceProvider.php',
        'Modules/IAM/Authentication/LoginThrottle.php',
        'Modules/IAM/Authentication/SecondFactorLimit.php',
        'Modules/IAM/Authentication/VerificationSlots.php',
        'Modules/IAM/Http/Controllers/ConfirmPasswordController.php',
        'Providers/AppServiceProvider.php',
        'Support/Heartbeat.php',
    ]);
});
