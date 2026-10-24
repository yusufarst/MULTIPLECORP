<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security log keyed hash
    |--------------------------------------------------------------------------
    |
    | The dedicated secret of SECURITY LG-03 (D-SEC-13): a submitted login
    | identifier, an address and a provider subject reach the security log
    | only as keyed HMACs under it. It lives in the environment only (SX-01),
    | never in the repository or the database, is not APP_KEY, and holds at
    | least 32 characters; rotating it breaks the linkage of earlier events.
    |
    */

    'log_hmac_key' => env('SECURITY_LOG_HMAC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Security event connection
    |--------------------------------------------------------------------------
    |
    | The database connection the security events are written on (LG-02): a
    | connection of their own, so an event commits outside any business
    | transaction. Only the test suite's transaction-wrapped tests point it
    | at the default connection, where their uncommitted rows are visible.
    |
    */

    'event_connection' => 'security',

];
