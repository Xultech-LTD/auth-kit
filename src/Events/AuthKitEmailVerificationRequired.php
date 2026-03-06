<?php

namespace Xul\AuthKit\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * AuthKitEmailVerificationRequired
 *
 * Fired when AuthKit starts an email verification flow for a newly
 * registered user (i.e., after creating the pending verification token).
 *
 * This event enables consumers to hook into verification delivery
 * (analytics, audit logs, CRM updates) without overriding actions.
 */
final class AuthKitEmailVerificationRequired
{
    /**
     * Create a new instance.
     *
     * @param Authenticatable $user
     * @param string $email
     * @param string $driver
     * @param int $ttlMinutes
     * @param string $token
     * @param string|null $url
     */
    public function __construct(
        public Authenticatable $user,
        public string $email,
        public string $driver,
        public int $ttlMinutes,
        public string $token,
        public ?string $url = null
    ) {}
}