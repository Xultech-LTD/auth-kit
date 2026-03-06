<?php

namespace Xul\AuthKit\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * EmailVerificationNotifierContract
 *
 * Sends the initial email verification message after registration.
 *
 * Implementations may send:
 * - A signed link (link driver)
 * - A short code/token (token driver)
 *
 * Consumers may override the notifier via container binding or future config.
 */
interface EmailVerificationNotifierContract
{
    /**
     * Send an email verification message for the given user.
     *
     * @param Authenticatable $user
     * @param string $driver link|token
     * @param string $email
     * @param string $token
     * @param string|null $url Signed verification URL when driver=link
     *
     * @return void
     */
    public function send(
        Authenticatable $user,
        string $driver,
        string $email,
        string $token,
        ?string $url = null
    ): void;
}