<?php

namespace Xul\AuthKit\Http\Controllers\Api\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\Auth\TwoFactorChallengeAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\Http\Requests\Auth\TwoFactorChallengeRequest;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * TwoFactorChallengeController
 *
 * Handles completion of a pending login two-factor challenge.
 *
 * Returns:
 * - JSON responses for AJAX/JSON requests.
 * - Redirect responses with flash messages for standard SSR form posts.
 */
final class TwoFactorChallengeController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle the incoming request.
     *
     * @param TwoFactorChallengeRequest $request
     * @param TwoFactorChallengeAction $action
     * @return JsonResponse|RedirectResponse
     * @throws \Throwable
     */
    public function __invoke(TwoFactorChallengeRequest $request, TwoFactorChallengeAction $action): JsonResponse|RedirectResponse
    {
        $result = $action->handle($request->validated());

        if (ResponseResolver::expectsJson($request)) {
            $status = (int) ($result['status'] ?? 200);

            return $this->ok($result, $status);
        }

        $loginRoute = (string) data_get(config('authkit.route_names.web', []), 'login', 'authkit.web.login');

        if (!(bool) ($result['ok'] ?? false)) {
            if ((bool) ($result['two_factor_required'] ?? false)) {
                $twoFactorRoute = (string) data_get(config('authkit.route_names.web', []), 'two_factor_challenge', 'authkit.web.twofactor.challenge');

                return $this->toRouteWithError(
                    routeName: $twoFactorRoute,
                    parameters: ['c' => (string) ($result['challenge'] ?? '')],
                    message: (string) ($result['message'] ?? 'Invalid authentication code.')
                );
            }

            return $this->toRouteWithError(
                routeName: $loginRoute,
                parameters: [],
                message: (string) ($result['message'] ?? 'Two-factor challenge failed.')
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
            ->with('status', (string) ($result['message'] ?? 'Two-factor verified.'));
    }
}