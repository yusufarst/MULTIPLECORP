<?php

use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Integration', 'Architecture');

/**
 * The Content-Security-Policy of SECURITY WS-08 for the given nonce.
 */
function expectedContentSecurityPolicy(string $nonce): string
{
    return "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}'; "
        ."img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; "
        ."base-uri 'none'; form-action 'self'; frame-ancestors 'none'";
}
