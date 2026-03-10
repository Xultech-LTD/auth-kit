<?php

namespace Xul\AuthKit\Actions\PasswordReset;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
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
 * ResetPasswordAction
 *
 * Completes a password reset by validating the reset token or code and
 * persisting a new password.
 *
 * Behavior:
 * - Normalizes the email identity.
 * - Enforces an optional policy gate through canReset().
 * - Consumes the reset token as a single-use credential.
 * - Resolves the target user through PasswordResetUserResolverContract.
 * - Updates the password through PasswordUpdaterContract.
 * - Optionally logs the user in after reset when configured.
 * - Returns a standardized AuthKitActionResult for all outcomes.
 */
final class ResetPasswordAction
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
     * Execute the reset operation.
     *
     * @param string $email
     * @param string $token
     * @param string $newPasswordRaw
     * @return AuthKitActionResult
     */
    public function handle(string $email, string $token, string $newPasswordRaw): AuthKitActionResult
    {
        $email = mb_strtolower(trim($email));
        $token = trim($token);

        if ($email === '' || $token === '') {
            return AuthKitActionResult::failure(
                message: 'Invalid or expired reset token.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_or_expired_reset_token', 'Invalid or expired reset token.'),
                ],
                redirect: $this->resetRedirect($email, $token)
            );
        }

        if (! $this->policy->canReset($email)) {
            return AuthKitActionResult::failure(
                message: 'Password reset is not allowed for this account.',
                status: 403,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('password_reset_not_allowed', 'Password reset is not allowed for this account.'),
                ],
                redirect: $this->resetRedirect($email, $token)
            );
        }

        $payload = $this->pending->consumeToken($email, $token);

        if ($payload === null) {
            return AuthKitActionResult::failure(
                message: 'Invalid or expired reset token.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_or_expired_reset_token', 'Invalid or expired reset token.'),
                ],
                redirect: $this->resetRedirect($email, $token)
            );
        }

        $user = $this->users->resolve($email);

        if (! $user instanceof Authenticatable) {
            return AuthKitActionResult::failure(
                message: 'Invalid or expired reset token.',
                status: 422,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('invalid_or_expired_reset_token', 'Invalid or expired reset token.'),
                ],
                redirect: $this->resetRedirect($email, $token)
            );
        }

        $refreshRememberToken = (bool) data_get(
            config('authkit.password_reset.password_updater', []),
            'refresh_remember_token',
            true
        );

        $this->updater->update($user, $newPasswordRaw, $refreshRememberToken);

        $loggedIn = $this->loginAfterReset($user);

        return AuthKitActionResult::success(
            message: 'Password reset successfully.',
            status: 200,
            flow: AuthKitFlowStep::completed(),
            redirect: $this->postResetRedirect($loggedIn),
            payload: AuthKitPublicPayload::make([
                'email' => $email,
                'user_id' => (string) $user->getAuthIdentifier(),
                'password_reset' => true,
                'logged_in' => $loggedIn,
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
     * Resolve the reset form redirect.
     *
     * @param string $email
     * @param string $token
     * @return AuthKitRedirect
     */
    protected function resetRedirect(string $email, string $token): AuthKitRedirect
    {
        $webNames = (array) config('authkit.route_names.web', []);
        $routeName = (string) ($webNames['password_reset'] ?? 'authkit.web.password.reset');

        return AuthKitRedirect::route(
            routeName: $routeName,
            parameters: ['token' => $token, 'email' => $email],
            url: route($routeName, ['token' => $token, 'email' => $email])
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

            $loginRoute = (string) ($webNames['login'] ?? 'authkit.web.login');

            return AuthKitRedirect::route(
                routeName: $loginRoute,
                parameters: [],
                url: route($loginRoute)
            );
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