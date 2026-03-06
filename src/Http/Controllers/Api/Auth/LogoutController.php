<?php

namespace Xul\AuthKit\Http\Controllers\Api\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\Events\AuthKitLoggedOut;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * LogoutController
 *
 * Logs out the current authenticated user from the configured guard.
 *
 * Returns:
 * - JSON responses for AJAX/JSON requests.
 * - Redirect responses with flash messages for standard SSR form posts.
 */
final class LogoutController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @param AuthFactory $auth
     * @return JsonResponse|RedirectResponse
     */
    public function __invoke(Request $request, AuthFactory $auth): JsonResponse|RedirectResponse
    {
        $guardName = (string) config('authkit.auth.guard', 'web');
        $guard = $auth->guard($guardName);

        $user = $guard->user();

        $guard->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        event(new AuthKitLoggedOut(
            user: $user,
            guard: $guardName
        ));

        if (ResponseResolver::expectsJson($request)) {
            return $this->ok([
                'ok' => true,
                'status' => 200,
                'message' => 'Logged out.',
            ], 200);
        }

        $loginRoute = (string) data_get(config('authkit.route_names.web', []), 'login', 'authkit.web.login');

        return $this->toRouteWithStatus(
            routeName: $loginRoute,
            parameters: [],
            message: 'Logged out.'
        );
    }
}