<?php

namespace Xul\AuthKit\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * AuthKitTwoFactorRequired
 *
 * Fired when AuthKit determines that a login attempt must complete
 * two-factor authentication before a session is established.
 *
 * This event is dispatched after a pending login challenge is created.
 * Consuming applications may listen for audit logging, analytics, alerts,
 * or custom security workflows.
 */
final class AuthKitTwoFactorRequired
{
    /**
     * Create a new instance.
     *
     * @param Authenticatable $user
     * @param string $guard
     * @param string $challenge
     * @param array<int, string> $methods
     * @param bool $remember
     */
    public function __construct(
        public Authenticatable $user,
        public string $guard,
        public string $challenge,
        public array $methods = ['totp'],
        public bool $remember = false
    ) {}
}