<?php

namespace Xul\AuthKit\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * AuthKitLoggedOut
 *
 * Dispatched when a user logs out through AuthKit.
 *
 * This event allows consuming applications to attach custom behavior
 * (audit logs, security notifications, analytics, etc.).
 */
final class AuthKitLoggedOut
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable|null $user
     * @param string $guard
     */
    public function __construct(
        public ?Authenticatable $user,
        public string $guard
    ) {}
}