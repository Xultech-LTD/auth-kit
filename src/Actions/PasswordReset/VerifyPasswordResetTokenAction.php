<?php

namespace Xul\AuthKit\Actions\PasswordReset;

use Illuminate\Support\Facades\RateLimiter;
use Psr\SimpleCache\InvalidArgumentException;
use Xul\AuthKit\Support\PendingPasswordReset;

/**
 * VerifyPasswordResetTokenAction
 *
 * Verifies that a reset token/code is valid for an email without consuming it.
 *
 * Behavior:
 * - Requires a pending reset context to exist (presence key).
 * - Uses a peek strategy so the token remains available for the final reset submission.
 * - Applies a throttle to reduce brute-force attempts against short tokens.
 */
final class VerifyPasswordResetTokenAction
{
    /**
     * @param PendingPasswordReset $pending
     */
    public function __construct(
        protected PendingPasswordReset $pending
    ) {}

    /**
     * Execute the action.
     *
     * @param string $email
     * @param string $token
     * @return VerifyPasswordResetTokenResult
     * @throws InvalidArgumentException
     */
    public function execute(string $email, string $token): VerifyPasswordResetTokenResult
    {
        $emailKey = mb_strtolower(trim($email));
        $tokenKey = trim($token);

        if ($emailKey === '' || $tokenKey === '') {
            return VerifyPasswordResetTokenResult::failed('Invalid reset token.');
        }

        $driver = (string) config('authkit.password_reset.driver', 'link');

        if ($driver !== 'token') {
            return VerifyPasswordResetTokenResult::wrongDriver();
        }

        if (! $this->pending->hasPendingForEmail($emailKey)) {
            return VerifyPasswordResetTokenResult::expired();
        }

        $maxAttempts = (int) data_get(config('authkit.password_reset.token', []), 'max_attempts', 5);
        $decayMinutes = (int) data_get(config('authkit.password_reset.token', []), 'decay_minutes', 1);

        $limiterKey = "authkit:password_reset:verify_token:{$emailKey}";

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            return VerifyPasswordResetTokenResult::throttled();
        }

        $isValid = $this->pending->isTokenValidForEmail($emailKey, $tokenKey);

        if (! $isValid) {
            RateLimiter::hit($limiterKey, $decayMinutes * 60);

            return VerifyPasswordResetTokenResult::invalidToken();
        }

        return VerifyPasswordResetTokenResult::success();
    }
}