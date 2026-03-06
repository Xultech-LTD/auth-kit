<?php

namespace Xul\AuthKit\Http\Controllers\Api\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Throwable;
use Xul\AuthKit\Actions\Auth\TwoFactorResendAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\Http\Requests\Auth\TwoFactorResendRequest;
use Xul\AuthKit\Support\AuthKitSessionKeys;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * TwoFactorResendController
 *
 * Handles resending a two-factor challenge when supported by the active driver.
 */
final class TwoFactorResendController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle a two-factor resend request.
     *
     * @param TwoFactorResendRequest $request
     * @param TwoFactorResendAction $action
     * @return JsonResponse|RedirectResponse
     * @throws Throwable
     */
    public function __invoke(TwoFactorResendRequest $request, TwoFactorResendAction $action): JsonResponse|RedirectResponse
    {
        $result = $action->handle($request->validated());

        if (ResponseResolver::expectsJson($request)) {
            $status = (int) ($result['status'] ?? 200);

            return $this->ok($result, $status);
        }

        $twoFactorRoute = (string) data_get(config('authkit.route_names.web', []), 'two_factor_challenge', 'authkit.web.twofactor.challenge');
        $loginRoute = (string) data_get(config('authkit.route_names.web', []), 'login', 'authkit.web.login');

        if (!(bool) ($result['ok'] ?? false)) {
            $hasChallenge = (string) $request->session()->get(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE, '') !== '';

            return $hasChallenge
                ? $this->toRouteWithError($twoFactorRoute, [], (string) ($result['message'] ?? 'Resend failed.'))
                : $this->toRouteWithError($loginRoute, [], (string) ($result['message'] ?? 'Resend failed.'));
        }

        return $this->toRouteWithStatus(
            routeName: $twoFactorRoute,
            message: (string) ($result['message'] ?? 'Challenge resent.')
        );
    }
}