<?php

namespace Xul\AuthKit\Contracts\PasswordReset;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * PasswordResetUserResolverContract
 *
 * Contract for resolving a user model for a given identity value during password reset flows.
 *
 * This exists to support:
 * - Non-standard identity fields (username/phone)
 * - Multi-tenant or multi-database applications
 * - Custom user providers or multiple user tables
 *
 * Notes:
 * - This resolver is used for "does user exist?" checks and for obtaining the user
 *   instance to update the password.
 * - Implementations should return null when no user can be resolved.
 */
interface PasswordResetUserResolverContract
{
    /**
     * Resolve a user for the given identity value.
     *
     * @param string $identityValue Normalized identity (e.g. email).
     *
     * @return Authenticatable|null
     */
    public function resolve(string $identityValue): ?Authenticatable;
}