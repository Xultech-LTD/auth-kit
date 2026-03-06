<?php

namespace Xul\AuthKit\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * AuthKitEmailVerified
 *
 * Fired when AuthKit successfully verifies a user's email address,
 * regardless of verification driver (link/token).
 *
 * This event is complementary to Laravel's Verified event and provides a
 * stable, package-scoped signal for consumers.
 */
final class AuthKitEmailVerified
{
    /**
     * Create a new instance.
     *
     * @param Authenticatable $user
     * @param string $driver
     */
    public function __construct(
        public Authenticatable $user,
        public string $driver = 'link'
    ) {}
}