<?php

use Tests\Support\GuardReconciliation;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit', 'Architecture', 'Feature', 'Authorization', 'Routes', 'Interaction', 'Performance');

// TEST_STRATEGY TS-11: the guards are reconciled after every integration test (ReconcileGuards' families).
pest()->extend(TestCase::class)->afterEach(function () {
    GuardReconciliation::assertNone(self::$__filename, $this);
})->in('Integration');

/**
 * The Content-Security-Policy of SECURITY WS-08 for the given nonce.
 */
function expectedContentSecurityPolicy(string $nonce): string
{
    return "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}'; "
        ."img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; "
        ."base-uri 'none'; form-action 'self'; frame-ancestors 'none'";
}
