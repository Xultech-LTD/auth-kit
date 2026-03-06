<?php

namespace Xul\AuthKit\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * AuthKitRegistered
 *
 * Fired when AuthKit successfully registers a new user.
 *
 * This event allows consuming applications to hook into registration
 * (analytics, onboarding, CRM sync, etc.) without overriding controllers.
 */
final class AuthKitRegistered
{
    /**
     * Create a new instance.
     */
    public function __construct(
        public Authenticatable $user
    ) {}
}