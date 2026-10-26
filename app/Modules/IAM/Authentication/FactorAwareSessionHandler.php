<?php

namespace App\Modules\IAM\Authentication;

use Illuminate\Session\DatabaseSessionHandler;

/**
 * The framework's database session handler with SECURITY AU-16's project step (H6-15; H8-22): the framework
 * fills a session row's `user_id` only for an authenticated login, so a pending second-factor challenge — no
 * login yet — writes its account's id there too, and deleting the account's sessions, by a deactivation among
 * others, voids the challenge with them. The enrolment-only session is a login and needs nothing more.
 */
final class FactorAwareSessionHandler extends DatabaseSessionHandler
{
    protected function userId()
    {
        $id = parent::userId();

        if ($id !== null || $this->container === null || ! $this->container->bound('session.store')) {
            return $id;
        }

        $challenge = $this->container->make('session.store')->get(TwoFactorChallenge::KEY);

        return is_array($challenge) && is_int($challenge['user'] ?? null) ? $challenge['user'] : null;
    }
}
