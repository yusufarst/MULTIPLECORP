<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password hashing (SECURITY AU-02; D-SEC-01)
    |--------------------------------------------------------------------------
    |
    | The framework hasher only, Argon2id at or above the OWASP minimum —
    | 19 MiB of memory, two iterations, parallelism 1 —, tuned by P9 to about
    | 0.25–0.5 s per hash on the VPS within its memory budget (H9-11). The
    | algorithm is verified on every check and a hash below these parameters
    | is rehashed at login; a change of driver is a planned migration, never a
    | silent switch. bcrypt is the fallback only where the runtime lacks
    | Argon2id, with a work factor of at least 12 and a password longer than
    | 72 bytes rejected by the password profile, never truncated.
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    'bcrypt' => [
        'rounds' => (int) env('BCRYPT_ROUNDS', 12),
        'verify' => true,
        'limit' => null,
    ],

    'argon' => [
        'memory' => (int) env('ARGON_MEMORY', 19456),
        'threads' => (int) env('ARGON_THREADS', 1),
        'time' => (int) env('ARGON_TIME', 2),
        'verify' => true,
    ],

    'rehash_on_login' => true,

];
