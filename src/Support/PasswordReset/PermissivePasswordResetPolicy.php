<?php

namespace Xul\AuthKit\Support\PasswordReset;

use Xul\AuthKit\Contracts\PasswordReset\PasswordResetPolicyContract;

/**
 * PermissivePasswordResetPolicy
 *
 * Default password reset policy for AuthKit.
 *
 * This policy allows all requests and resets. It exists as a plug point so
 * consuming applications can enforce rules such as:
 * - denying resets for suspended users,
 * - requiring email verification before reset,
 * - rate limiting by identity.
 */
final class PermissivePasswordResetPolicy implements PasswordResetPolicyContract
{
    /**
     * Determine if a reset may be requested for the given identity.
     */
    public function canRequest(string $email): bool
    {
        return true;
    }

    /**
     * Determine if a reset may be completed for the given identity.
     */
    public function canReset(string $email): bool
    {
        return true;
    }
}