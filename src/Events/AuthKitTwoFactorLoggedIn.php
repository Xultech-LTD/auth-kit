<?php

namespace Xul\AuthKit\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * AuthKitTwoFactorLoggedIn
 *
 * Dispatched when a user completes a two-factor login challenge successfully.
 *
 * This event exists to allow consuming applications to attach custom behavior
 * specifically for the two-factor completion step (audit logs, security alerts,
 * device remembering, analytics, etc.) without coupling into AuthKit internals.
 */
final class AuthKitTwoFactorLoggedIn
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
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
        public array $methods = [],
        public bool $remember = false
    ) {}
}