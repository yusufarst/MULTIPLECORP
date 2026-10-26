<?php

use App\Modules\IAM\Authentication\AuthenticationServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    AuthenticationServiceProvider::class,
];
