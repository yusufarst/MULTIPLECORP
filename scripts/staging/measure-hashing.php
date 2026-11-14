<?php

// PFM-12's measurement on the VPS (TASK-068 for TASK-079; PERFORMANCE_TARGETS PFM-12; SECURITY H6-12; OPI-07): the Argon2id
// verification the application is configured with, timed on this host — the figure the password-verification cap's three
// slots rest on —, beside the slots and the limiters in force, for the operator's review. Read only: it hashes a throwaway
// value in memory and writes nothing. In the `app` container:
//
//   php scripts/staging/measure-hashing.php [rounds]

use App\Modules\IAM\Authentication\VerificationSlots;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$rounds = max(5, (int) ($argv[1] ?? 20));
$secret = bin2hex(random_bytes(16));
$hash = Hash::make($secret);
$times = [];

for ($round = 0; $round < $rounds; $round++) {
    $started = hrtime(true);
    Hash::check($secret, $hash);
    $times[] = (hrtime(true) - $started) / 1_000_000;
}

sort($times);
$at = fn (float $share): float => $times[max(0, (int) ceil($share * count($times)) - 1)];

printf("PFM-12 on %s\n", gethostname());
printf("hashing: %s, memory %d KiB, time cost %d, threads %d\n", config('hashing.driver'), config('hashing.argon.memory'), config('hashing.argon.time'), config('hashing.argon.threads'));
printf("one verification over %d rounds: median %.1f ms, p95 %.1f ms, slowest %.1f ms\n", $rounds, $at(0.5), $at(0.95), $times[count($times) - 1]);
printf("verification slots: %d unauthenticated (wait %d ms), %d authenticated (wait %d ms), released after %d s\n",
    VerificationSlots::UNAUTHENTICATED_SLOTS, VerificationSlots::UNAUTHENTICATED_WAIT_MS, VerificationSlots::AUTHENTICATED_SLOTS,
    VerificationSlots::AUTHENTICATED_WAIT_MS, VerificationSlots::RELEASE_AFTER_SECONDS);
printf("the limiters' values are in app/Providers/AppServiceProvider.php and app/Modules/IAM/Authentication/AuthenticationServiceProvider.php; each kept or corrected through review (PFM-12)\n");
