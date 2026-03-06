<?php

namespace Xul\AuthKit\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * AuthKitTwoFactorRecovered
 *
 * Dispatched when a pending login is completed using a recovery code.
 */
final class AuthKitTwoFactorRecovered
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new instance.
     *
     * @param object $user
     * @param string $guard
     * @param bool $remember
     * @param string $driver
     */
    public function __construct(
        public readonly object $user,
        public readonly string $guard,
        public readonly bool $remember,
        public readonly string $driver
    ) {}
}