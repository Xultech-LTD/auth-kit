<?php

namespace Xul\AuthKit\Actions\PasswordReset;

use Xul\AuthKit\Contracts\PasswordReset\PasswordResetPolicyContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUserResolverContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordUpdaterContract;
use Xul\AuthKit\Support\PendingPasswordReset;

/**
 * ResetPasswordAction
 *
 * Completes a password reset by validating the reset token/code and persisting a new password.
 *
 * Behavior:
 * - Normalizes the email identity.
 * - Enforces an optional policy gate (canReset).
 * - Consumes the reset token (single-use) via PendingPasswordReset.
 * - Resolves the target user via PasswordResetUserResolverContract.
 * - Updates the password via PasswordUpdaterContract.
 */
final class ResetPasswordAction
{
    /**
     * @param PendingPasswordReset $pending
     * @param PasswordResetUserResolverContract $users
     * @param PasswordResetPolicyContract $policy
     * @param PasswordUpdaterContract $updater
     */
    public function __construct(
        protected PendingPasswordReset $pending,
        protected PasswordResetUserResolverContract $users,
        protected PasswordResetPolicyContract $policy,
        protected PasswordUpdaterContract $updater
    ) {}

    /**
     * Execute the reset operation.
     */
    public function execute(string $email, string $token, string $newPasswordRaw): ResetPasswordResult
    {
        $email = mb_strtolower(trim($email));
        $token = trim($token);

        if ($email === '' || $token === '') {
            return ResetPasswordResult::invalidToken();
        }

        if (! $this->policy->canReset($email)) {
            return ResetPasswordResult::failed('Password reset is not allowed for this account.');
        }

        $payload = $this->pending->consumeToken($email, $token);

        if ($payload === null) {
            return ResetPasswordResult::invalidToken();
        }

        $user = $this->users->resolve($email);

        if ($user === null) {
            return ResetPasswordResult::invalidToken();
        }

        $refresh = (bool) data_get(config('authkit.password_reset.password_updater', []), 'refresh_remember_token', true);

        $this->updater->update($user, $newPasswordRaw, $refresh);

        return ResetPasswordResult::success($user);
    }
}