<?php

namespace Xul\AuthKit\Actions\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;
use Xul\AuthKit\Events\AuthKitLoggedOut;

/**
 * LogoutAction
 *
 * Logs out the current authenticated user from the configured guard and
 * returns a standardized AuthKit action result.
 *
 * Responsibilities:
 * - Resolve the configured guard.
 * - Ensure the guard is stateful.
 * - Resolve the current authenticated user.
 * - Fail when no authenticated user is available for logout.
 * - Perform logout on the configured guard.
 * - Dispatch AuthKitLoggedOut after successful logout.
 * - Return a standardized AuthKitActionResult for all outcomes.
 */
final class LogoutAction
{
    /**
     * Create a new instance.
     *
     * @param AuthFactory $auth
     */
    public function __construct(
        protected AuthFactory $auth,
    ) {}

    /**
     * Attempt logout and return a standardized action result.
     *
     * @return AuthKitActionResult
     */
    public function handle(): AuthKitActionResult
    {
        $guardName = (string) config('authkit.auth.guard', 'web');
        $guard = $this->auth->guard($guardName);

        if (! $guard instanceof StatefulGuard) {
            return AuthKitActionResult::failure(
                message: 'Auth guard is not stateful.',
                status: 500,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('guard_not_stateful', 'Auth guard is not stateful.'),
                ],
            );
        }

        $user = $guard->user();

        if ($user === null) {
            return AuthKitActionResult::failure(
                message: 'Unauthenticated.',
                status: 401,
                flow: AuthKitFlowStep::failed(),
                errors: [
                    AuthKitError::make('unauthenticated', 'Unauthenticated.'),
                ],
            );
        }

        $guard->logout();

        session()->invalidate();
        session()->regenerateToken();

        event(new AuthKitLoggedOut(
            user: $user,
            guard: $guardName
        ));

        $loginRoute = (string) data_get(
            config('authkit.route_names.web', []),
            'login',
            'authkit.web.login'
        );

        return AuthKitActionResult::success(
            message: 'Logged out.',
            status: 200,
            flow: AuthKitFlowStep::completed(),
            redirect: AuthKitRedirect::route(
                routeName: $loginRoute,
                parameters: [],
                url: route($loginRoute)
            ),
            payload: AuthKitPublicPayload::make([
                'guard' => $guardName,
            ]),
        );
    }
}