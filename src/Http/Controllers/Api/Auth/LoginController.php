<?php

namespace Xul\AuthKit\Http\Controllers\Api\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\Auth\LoginAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\Http\Requests\Auth\LoginRequest;
use Xul\AuthKit\Support\AuthKitSessionKeys;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

final class LoginController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle a login request.
     *
     * Returns:
     * - JSON responses for AJAX/JSON requests.
     * - Redirect responses with flash messages for standard SSR form posts.
     */
    public function __invoke(LoginRequest $request, LoginAction $action): JsonResponse|RedirectResponse
    {
        $result = $action->handle($request->validated());

        $twoFactorRoute = (string) data_get(config('authkit.route_names.web', []), 'two_factor_challenge', 'authkit.web.twofactor.challenge');

        if ((bool) ($result['two_factor_required'] ?? false)) {
            $challenge = (string) ($result['internal_challenge'] ?? '');

            if ($challenge !== '') {
                $request->session()->put(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE, $challenge);
            }
        }

        if (ResponseResolver::expectsJson($request)) {
            $status = (int) ($result['status'] ?? 200);

            $safe = $result;

            unset($safe['internal_challenge'], $safe['redirect_url']);

            if ((bool) ($safe['two_factor_required'] ?? false)) {
                $safe['redirect_url'] = route($twoFactorRoute);
            }

            return $this->ok($safe, $status);
        }

        $loginRoute = (string) data_get(config('authkit.route_names.web', []), 'login', 'authkit.web.login');

        if (!(bool) ($result['ok'] ?? false)) {
            return $this->toRouteWithError(
                routeName: $loginRoute,
                parameters: [],
                message: (string) ($result['message'] ?? 'Login failed.')
            );
        }

        if ((bool) ($result['two_factor_required'] ?? false)) {
            return $this->toRouteWithStatus(
                routeName: $twoFactorRoute,
                parameters: [],
                message: (string) ($result['message'] ?? 'Two-factor authentication required.')
            );
        }

        $redirectRoute = data_get(config('authkit.login', []), 'redirect_route');
        $dashboardRoute = (string) data_get(config('authkit.login', []), 'dashboard_route', 'dashboard');

        $target = is_string($redirectRoute) && $redirectRoute !== ''
            ? $redirectRoute
            : $dashboardRoute;

        if ($target === '') {
            $target = $loginRoute;
        }

        return redirect()
            ->route($target)
            ->with('status', (string) ($result['message'] ?? 'Logged in.'));
    }
}