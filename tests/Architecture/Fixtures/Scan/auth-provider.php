<?php

// Provider-specific authentication outside the authentication boundary (ARCHITECTURE §8, §19).
use Laravel\Socialite\Facades\Socialite;

$driver = Socialite::driver('google');
