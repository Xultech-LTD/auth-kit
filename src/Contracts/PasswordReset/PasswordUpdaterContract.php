<?php

namespace Xul\AuthKit\Contracts\PasswordReset;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * PasswordUpdaterContract
 *
 * Contract for persisting a new password for a resolved user.
 *
 * This exists to support:
 * - Custom hashing strategies or legacy password fields
 * - Password history policies
 * - Audit trails and security events
 * - Refreshing remember tokens and invalidating sessions
 *
 * Notes:
 * - Implementations should be responsible for hashing the provided raw password.
 * - AuthKit may optionally request remember token refresh via configuration.
 */
interface PasswordUpdaterContract
{
    /**
     * Update the user's password.
     *
     * @param Authenticatable $user
     * @param string          $newPasswordRaw The raw password submitted by the user.
     * @param bool            $refreshRememberToken Whether to refresh the user's remember token.
     *
     * @return void
     */
    public function update(Authenticatable $user, string $newPasswordRaw, bool $refreshRememberToken = true): void;
}