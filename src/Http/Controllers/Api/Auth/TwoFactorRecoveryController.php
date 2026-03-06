<?php

namespace Xul\AuthKit\Http\Controllers\Api\Auth;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;
use Xul\AuthKit\Actions\Auth\TwoFactorRecoveryAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\Http\Requests\Auth\TwoFactorRecoveryRequest;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * TwoFactorRecoveryController
 *
 * Handles recovery-code completion of a pending two-factor login challenge.
 *
 * @final
 */
final class TwoFactorRecoveryController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle a two-factor recovery request.
     *
     * Returns:
     * - JSON responses for AJAX/JSON requests.
     * - Redirect responses with flash messages for standard SSR form posts.
     *
     * @param TwoFactorRecoveryRequest $request
     * @param TwoFactorRecoveryAction $action
     * @return JsonResponse|RedirectResponse
     * @throws Throwable
     */
    public function __invoke(TwoFactorRecoveryRequest $request, TwoFactorRecoveryAction $action): JsonResponse|RedirectResponse
    {
        $result = $action->handle($request->validated());

        if (ResponseResolver::expectsJson($request)) {
            $status = (int) ($result['status'] ?? 200);

            return $this->ok($result, $status);
        }

        $loginRoute = (string) data_get(config('authkit.route_names.web', []), 'login', 'authkit.web.login');
        $twoFactorRoute = (string) data_get(config('authkit.route_names.web', []), 'two_factor_challenge', 'authkit.web.twofactor.challenge');

        if (!(bool) ($result['ok'] ?? false)) {
            $status = (int) ($result['status'] ?? 422);

            if ($status === 410) {
                return $this->toRouteWithError(
                    routeName: $loginRoute,
                    parameters: [],
                    message: (string) ($result['message'] ?? 'Expired or invalid two-factor challenge.')
                );
            }

            return $this->toRouteWithError(
                routeName: $twoFactorRoute,
                parameters: ['c' => (string) $request->input('challenge', '')],
                message: (string) ($result['message'] ?? 'Recovery failed.')
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
            ->with('status', (string) ($result['message'] ?? 'Recovered and logged in.'));
    }
}