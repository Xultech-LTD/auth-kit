<?php

namespace Xul\AuthKit\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * AuthKitLoggedIn
 *
 * Fired when AuthKit successfully logs a user into the configured guard
 * (i.e., when two-factor is not required for the current login attempt).
 *
 * Consuming applications may listen to this event for analytics, audit logs,
 * onboarding hooks, etc., without overriding AuthKit controllers.
 */
final class AuthKitLoggedIn
{
    /**
     * Create a new instance.
     *
     * @param Authenticatable $user
     * @param string $guard
     * @param bool $remember
     */
    public function __construct(
        public Authenticatable $user,
        public string $guard,
        public bool $remember = false
    ) {}
}