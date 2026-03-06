<?php

namespace Xul\AuthKit\Http\Controllers\Api\PasswordReset;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\PasswordReset\ResetPasswordAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\Http\Requests\PasswordReset\ResetPasswordRequest;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * ResetPasswordController
 *
 * Completes a password reset using an email + token/code + new password.
 *
 * Returns:
 * - JSON for AJAX/JSON requests.
 * - Redirect + flash messages for standard SSR form posts.
 */
final class ResetPasswordController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * @param ResetPasswordRequest $request
     * @param ResetPasswordAction $action
     * @param AuthFactory $auth
     * @return JsonResponse|RedirectResponse
     */
    public function __invoke(
        ResetPasswordRequest $request,
        ResetPasswordAction $action,
        AuthFactory $auth
    ): JsonResponse|RedirectResponse {
        $email = (string) data_get($request->validated(), 'email', '');
        $token = (string) data_get($request->validated(), 'token', '');
        $password = (string) data_get($request->validated(), 'password', '');

        $result = $action->execute(
            email: $email,
            token: $token,
            newPasswordRaw: $password
        );

        $payload = [
            'ok' => $result->ok,
            'message' => $result->message,
        ];

        if (ResponseResolver::expectsJson($request)) {
            return $this->ok($payload, $result->ok ? 200 : 422);
        }

        $webNames = (array) config('authkit.route_names.web', []);
        $resetRoute = (string) ($webNames['password_reset'] ?? 'authkit.web.password.reset');

        if (! $result->ok) {
            return $this->toRouteWithError(
                routeName: $resetRoute,
                parameters: ['token' => $token, 'email' => $email],
                message: $result->message
            );
        }

        $mode = (string) data_get(config('authkit.password_reset.post_reset', []), 'mode', 'success_page');

        if ($mode === 'redirect') {
            $redirectRoute = (string) (data_get(config('authkit.password_reset.post_reset', []), 'redirect_route') ?? '');

            if ($redirectRoute !== '') {
                return redirect()->route($redirectRoute)->with('status', $result->message);
            }

            $loginFallback = (string) data_get(config('authkit.password_reset.post_reset', []), 'login_route', 'authkit.web.login');

            return redirect()->route($loginFallback)->with('status', $result->message);
        }

        $loginAfterReset = (bool) data_get(config('authkit.password_reset.post_reset', []), 'login_after_reset', false);

        if ($loginAfterReset && $result->user !== null) {
            $guard = (string) config('authkit.auth.guard', 'web');
            $remember = (bool) data_get(config('authkit.password_reset.post_reset', []), 'remember', true);

            $auth->guard($guard)->login($result->user, $remember);

            $redirectRoute = data_get(config('authkit.login', []), 'redirect_route');
            $dashboardRoute = (string) data_get(config('authkit.login', []), 'dashboard_route', 'dashboard');

            $target = is_string($redirectRoute) && $redirectRoute !== '' ? $redirectRoute : $dashboardRoute;

            if ($target === '') {
                $target = (string) ($webNames['login'] ?? 'authkit.web.login');
            }

            return redirect()->route($target)->with('status', $result->message);
        }

        $successRoute = (string) data_get(
            config('authkit.password_reset.post_reset', []),
            'success_route',
            (string) ($webNames['password_reset_success'] ?? 'authkit.web.password.reset.success')
        );

        return $this->toRouteWithStatus(
            routeName: $successRoute,
            parameters: [],
            message: $result->message
        );
    }
}