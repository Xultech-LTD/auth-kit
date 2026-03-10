<?php

namespace Xul\AuthKit\Actions\PasswordReset;

use Illuminate\Support\Facades\RateLimiter;
use Psr\SimpleCache\InvalidArgumentException;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;
use Xul\AuthKit\Support\PendingPasswordReset;

/**
 * VerifyPasswordResetTokenAction
 *
 * Verifies that a reset token or code is valid for an email without consuming it.
 *
 * Behavior:
 * - Requires a pending reset context to exist.
 * - Uses a peek strategy so the token remains available for final reset submission.
 * - Applies throttling to reduce brute-force attempts against short reset codes.
 * - Returns a standardized AuthKitActionResult for all outcomes.
 */
final class VerifyPasswordResetTokenAction
{
    /**
     * Create a new instance.
     *
     * @param PendingPasswordReset $pending
     */
    public function __construct(
        protected PendingPasswordReset $pending
    ) {}

    /**
     * Execute the token verification flow.
     *
     * @param string $email
     * @param string $token
     * @return AuthKitActionResult
     * @throws InvalidArgumentException
     */
    public function handle(string $email, string $token): AuthKitActionResult
    {
        $emailKey = mb_strtolower(trim($email));
        $tokenKey = trim($token);

        if ($emailKey === '' || $tokenKey === '') {
            return AuthKitActionResult::failure(
                message: 'Invalid reset token.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_reset_token', 'Invalid reset token.'),
                ],
                redirect: $this->tokenPageRedirect($emailKey)
            );
        }

        $driver = (string) config('authkit.password_reset.driver', 'link');

        if ($driver !== 'token') {
            return AuthKitActionResult::failure(
                message: 'Password reset token verification is not available for the active driver.',
                status: 409,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('password_reset_wrong_driver', 'Password reset token verification is not available for the active driver.'),
                ],
                redirect: $this->tokenPageRedirect($emailKey),
                payload: AuthKitPublicPayload::make([
                    'driver' => $driver,
                ])
            );
        }

        if (! $this->pending->hasPendingForEmail($emailKey)) {
            return AuthKitActionResult::failure(
                message: 'Password reset request has expired.',
                status: 410,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('password_reset_request_expired', 'Password reset request has expired.'),
                ],
                redirect: $this->tokenPageRedirect($emailKey)
            );
        }

        $maxAttempts = (int) data_get(config('authkit.password_reset.token', []), 'max_attempts', 5);
        $decayMinutes = (int) data_get(config('authkit.password_reset.token', []), 'decay_minutes', 1);

        $limiterKey = "authkit:password_reset:verify_token:{$emailKey}";

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            return AuthKitActionResult::failure(
                message: 'Too many verification attempts. Please try again shortly.',
                status: 429,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('password_reset_token_throttled', 'Too many verification attempts. Please try again shortly.'),
                ],
                redirect: $this->tokenPageRedirect($emailKey)
            );
        }

        $isValid = $this->pending->isTokenValidForEmail($emailKey, $tokenKey);

        if (! $isValid) {
            RateLimiter::hit($limiterKey, $decayMinutes * 60);

            return AuthKitActionResult::failure(
                message: 'Invalid reset token.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_reset_token', 'Invalid reset token.'),
                ],
                redirect: $this->tokenPageRedirect($emailKey)
            );
        }

        $loginRoute = (string) data_get(config('authkit.route_names.web', []), 'login', 'authkit.web.login');

        return AuthKitActionResult::success(
            message: 'Reset token verified successfully.',
            status: 200,
            flow: AuthKitFlowStep::completed(),
            redirect: AuthKitRedirect::route(
                routeName: $loginRoute,
                parameters: [],
                url: route($loginRoute)
            ),
            payload: AuthKitPublicPayload::make([
                'email' => $emailKey,
                'driver' => $driver,
                'token_verified' => true,
            ])
        );
    }

    /**
     * Resolve the password reset token page redirect.
     *
     * @param string $email
     * @return AuthKitRedirect
     */
    protected function tokenPageRedirect(string $email): AuthKitRedirect
    {
        $webNames = (array) config('authkit.route_names.web', []);
        $routeName = (string) ($webNames['password_reset_token_page'] ?? 'authkit.web.password.reset.token');

        return AuthKitRedirect::route(
            routeName: $routeName,
            parameters: $email !== '' ? ['email' => $email] : [],
            url: route($routeName, $email !== '' ? ['email' => $email] : [])
        );
    }
}