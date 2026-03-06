<?php

namespace Xul\AuthKit\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * AuthKitTwoFactorResent
 *
 * Dispatched after a two-factor challenge is resent via a resend-capable driver.
 */
final class AuthKitTwoFactorResent
{
    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param string $guard
     * @param string $driver
     */
    public function __construct(
        public Authenticatable $user,
        public string $guard,
        public string $driver
    ) {}
}