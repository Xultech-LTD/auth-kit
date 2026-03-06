<?php

namespace Xul\AuthKit\Contracts\PasswordReset;

/**
 * PasswordResetPolicyContract
 *
 * Optional policy hook for enforcing application-specific reset rules.
 *
 * Examples:
 * - Block resets for suspended users
 * - Require email verified before allowing reset
 * - Enforce a minimum time between resets per identity
 *
 * Notes:
 * - This contract is optional. AuthKit can ship with a default permissive policy.
 * - When present, controllers/actions should consult it before generating tokens
 *   or updating passwords.
 */
interface PasswordResetPolicyContract
{
    /**
     * Determine if a password reset may be requested for the given identity.
     *
     * @param string $email Normalized identity.
     * @return bool
     */
    public function canRequest(string $email): bool;

    /**
     * Determine if the new password may be set for the given identity.
     *
     * @param string $email Normalized identity.
     * @return bool
     */
    public function canReset(string $email): bool;
}