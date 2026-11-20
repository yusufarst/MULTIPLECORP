<?php

use Tests\Support\AuthorityReconciliation;
use Tests\Support\GuardReconciliation;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit', 'Architecture', 'Feature', 'Routes', 'Interaction', 'Performance');

// SECURITY_TEST_MATRIX SM-05 (TASK-069): the OD-16 reconciliation returns no row after every authorization test.
pest()->extend(TestCase::class)->afterEach(function () {
    AuthorityReconciliation::assertNone(self::$__filename, $this);
})->in('Authorization');

// TEST_STRATEGY TS-11: the guards are reconciled after every integration test (ReconcileGuards' families); SM-05's
// OD-16 reconciliation after each one too, the concurrency suites among them (TASK-069).
pest()->extend(TestCase::class)->afterEach(function () {
    GuardReconciliation::assertNone(self::$__filename, $this);
    AuthorityReconciliation::assertNone(self::$__filename, $this);
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
