<?php

namespace Xul\AuthKit\Http\Controllers\Api\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Throwable;
use Xul\AuthKit\Actions\Auth\TwoFactorResendAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Requests\Auth\TwoFactorResendRequest;
use Xul\AuthKit\Support\AuthKitSessionKeys;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * TwoFactorResendController
 *
 * Handles resending a two-factor challenge when supported by the active driver.
 *
 * Responsibilities:
 * - Validate the incoming request through TwoFactorResendRequest.
 * - Build the normalized mapped payload for the resend context.
 * - Delegate resend orchestration to TwoFactorResendAction.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
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
    public function __invoke(
        TwoFactorResendRequest $request,
        TwoFactorResendAction $action
    ): JsonResponse|RedirectResponse {
        $payload = MappedPayloadBuilder::build('two_factor_resend', $request->validated());

        $result = $action->handle($payload);

        if (ResponseResolver::expectsJson($request)) {
            return $this->ok($result->toArray(), $result->status);
        }

        return $this->toWebResponse(
            result: $result,
            hasChallenge: (string) $request->session()->get(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE, '') !== ''
        );
    }

    /**
     * Convert the standardized action result into a web redirect response.
     *
     * @param AuthKitActionResult $result
     * @param bool $hasChallenge
     * @return RedirectResponse
     */
    protected function toWebResponse(AuthKitActionResult $result, bool $hasChallenge): RedirectResponse
    {
        $twoFactorRoute = (string) data_get(
            config('authkit.route_names.web', []),
            'two_factor_challenge',
            'authkit.web.twofactor.challenge'
        );

        $loginRoute = (string) data_get(
            config('authkit.route_names.web', []),
            'login',
            'authkit.web.login'
        );

        if (! $result->ok) {
            if ($result->hasRedirect() && $result->redirect?->isRoute()) {
                return $this->toRouteWithError(
                    routeName: $result->redirect->target,
                    parameters: $result->redirect->parameters,
                    message: $result->message
                );
            }

            return $hasChallenge
                ? $this->toRouteWithError($twoFactorRoute, [], $result->message)
                : $this->toRouteWithError($loginRoute, [], $result->message);
        }

        if ($result->hasRedirect() && $result->redirect?->isRoute()) {
            return $this->toRouteWithStatus(
                routeName: $result->redirect->target,
                parameters: $result->redirect->parameters,
                message: $result->message
            );
        }

        return $this->toRouteWithStatus(
            routeName: $twoFactorRoute,
            parameters: [],
            message: $result->message
        );
    }
}