<?php

namespace Xul\AuthKit\Actions\PasswordReset;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\RateLimiter;
use Psr\SimpleCache\InvalidArgumentException;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetPolicyContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordResetUserResolverContract;
use Xul\AuthKit\Contracts\PasswordReset\PasswordUpdaterContract;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;
use Xul\AuthKit\Events\AuthKitLoggedIn;
use Xul\AuthKit\Support\PendingPasswordReset;

/**
 * VerifyPasswordResetTokenAction
 *
 * Verifies that a reset token or code is valid for an email and completes
 * the password reset in the same request for token-driver flows.
 *
 * Behavior:
 * - Requires a pending reset context to exist.
 * - Validates and consumes the reset token.
 * - Resolves the target user and updates the password.
 * - Applies throttling to reduce brute-force attempts against short reset codes.
 * - Optionally logs the user in after successful reset.
 * - Returns a standardized AuthKitActionResult for all outcomes.
 */
final class VerifyPasswordResetTokenAction
{
    /**
     * Create a new instance.
     *
     * @param PendingPasswordReset $pending
     * @param PasswordResetUserResolverContract $users
     * @param PasswordResetPolicyContract $policy
     * @param PasswordUpdaterContract $updater
     * @param AuthFactory $auth
     */
    public function __construct(
        protected PendingPasswordReset $pending,
        protected PasswordResetUserResolverContract $users,
        protected PasswordResetPolicyContract $policy,
        protected PasswordUpdaterContract $updater,
        protected AuthFactory $auth,
    ) {}

    /**
     * Execute the token verification and reset flow.
     *
     * @param string $email
     * @param string $token
     * @param string $password
     * @return AuthKitActionResult
     * @throws InvalidArgumentException
     */
    public function handle(string $email, string $token, string $password): AuthKitActionResult
    {
        $emailKey = mb_strtolower(trim($email));
        $tokenKey = trim($token);
        $password = (string) $password;

        if ($emailKey === '' || $tokenKey === '' || $password === '') {
            return AuthKitActionResult::failure(
                message: 'Invalid reset request.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_reset_request', 'Invalid reset request.'),
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

        if (! $this->policy->canReset($emailKey)) {
            return AuthKitActionResult::failure(
                message: 'Password reset is not allowed for this account.',
                status: 403,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('password_reset_not_allowed', 'Password reset is not allowed for this account.'),
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

        $payload = $this->pending->consumeToken($emailKey, $tokenKey);

        if (! is_array($payload)) {
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

        $user = $this->users->resolve($emailKey);

        if (! $user instanceof Authenticatable) {
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

        $refreshRememberToken = (bool) data_get(
            config('authkit.password_reset.password_updater', []),
            'refresh_remember_token',
            true
        );

        $this->updater->update($user, $password, $refreshRememberToken);

        $loggedIn = $this->loginAfterReset($user);

        return AuthKitActionResult::success(
            message: 'Password reset successfully.',
            status: 200,
            flow: AuthKitFlowStep::completed(),
            redirect: $this->postResetRedirect($loggedIn),
            payload: AuthKitPublicPayload::make([
                'email' => $emailKey,
                'driver' => $driver,
                'password_reset' => true,
                'logged_in' => $loggedIn,
                'user_id' => (string) $user->getAuthIdentifier(),
            ])
        );
    }

    /**
     * Optionally authenticate the user after successful reset.
     *
     * @param Authenticatable $user
     * @return bool
     */
    protected function loginAfterReset(Authenticatable $user): bool
    {
        $enabled = (bool) data_get(config('authkit.password_reset.post_reset', []), 'login_after_reset', false);

        if (! $enabled) {
            return false;
        }

        $guardName = (string) config('authkit.auth.guard', 'web');
        $remember = (bool) data_get(config('authkit.password_reset.post_reset', []), 'remember', true);

        $this->auth->guard($guardName)->login($user, $remember);

        event(new AuthKitLoggedIn(
            user: $user,
            guard: $guardName,
            remember: $remember
        ));

        return true;
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

    /**
     * Resolve the redirect after successful password reset.
     *
     * @param bool $loggedIn
     * @return AuthKitRedirect
     */
    protected function postResetRedirect(bool $loggedIn): AuthKitRedirect
    {
        $webNames = (array) config('authkit.route_names.web', []);
        $mode = (string) data_get(config('authkit.password_reset.post_reset', []), 'mode', 'success_page');

        if ($mode === 'redirect') {
            $redirectRoute = (string) (data_get(config('authkit.password_reset.post_reset', []), 'redirect_route') ?? '');

            if ($redirectRoute !== '') {
                return AuthKitRedirect::route(
                    routeName: $redirectRoute,
                    parameters: [],
                    url: route($redirectRoute)
                );
            }

            $loginFallback = (string) data_get(
                config('authkit.password_reset.post_reset', []),
                'login_route',
                'authkit.web.login'
            );

            return AuthKitRedirect::route(
                routeName: $loginFallback,
                parameters: [],
                url: route($loginFallback)
            );
        }

        if ($loggedIn) {
            $redirectRoute = data_get(config('authkit.login', []), 'redirect_route');
            $dashboardRoute = (string) data_get(config('authkit.login', []), 'dashboard_route', 'dashboard');

            $target = is_string($redirectRoute) && $redirectRoute !== ''
                ? $redirectRoute
                : $dashboardRoute;

            if ($target !== '') {
                return AuthKitRedirect::route(
                    routeName: $target,
                    parameters: [],
                    url: route($target)
                );
            }
        }

        $successRoute = (string) data_get(
            config('authkit.password_reset.post_reset', []),
            'success_route',
            (string) ($webNames['password_reset_success'] ?? 'authkit.web.password.reset.success')
        );

        return AuthKitRedirect::route(
            routeName: $successRoute,
            parameters: [],
            url: route($successRoute)
        );
    }
}