<?php

namespace Xul\AuthKit\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * AuthKitPasswordResetCompleted
 *
 * Dispatched after a successful password reset.
 *
 * Typical listeners:
 * - Audit logging / analytics
 * - Security alerts (email "Your password was changed")
 * - Session invalidation workflows
 */
final class AuthKitPasswordResetCompleted
{
    /**
     * @param string               $driver
     * @param string               $email
     * @param Authenticatable|null $user
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $driver,
        public readonly string $email,
        public readonly ?Authenticatable $user = null,
        public readonly array $meta = [],
    ) {}
}