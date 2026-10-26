<?php

/*
|--------------------------------------------------------------------------
| Fortify (SECURITY D-SEC-17; ARCHITECTURE §1, §8; TASK-017)
|--------------------------------------------------------------------------
|
| Fortify supplies the sign-in and logout controllers behind IAM's
| authentication boundary and nothing else: no feature is enabled — the
| credential links are IAM's own (AU-11), two-step verification TASK-018's —,
| no view is registered, and Fortify's own routes are ignored, IAM declaring
| ROUTE-101 and ROUTE-108 itself (SM-22). The sign-in pipeline is IAM's
| (AU-04, AU-05), set in AuthenticationServiceProvider.
|
*/

return [

    'guard' => 'web',

    'middleware' => ['web'],

    'auth_middleware' => 'auth',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'views' => false,

    'home' => '/',

    'prefix' => '',

    'domain' => null,

    'lowercase_usernames' => true,

    'limiters' => [
        'login' => null,
    ],

    'redirects' => [
        'login' => '/',
        'logout' => '/masuk',
    ],

    'features' => [],

];
