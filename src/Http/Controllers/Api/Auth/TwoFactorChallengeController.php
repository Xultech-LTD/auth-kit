<?php

namespace Xul\AuthKit\Http\Controllers\Api\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\Auth\TwoFactorChallengeAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Requests\Auth\TwoFactorChallengeRequest;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * TwoFactorChallengeController
 *
 * Handles completion of a pending login two-factor challenge.
 *
 * Responsibilities:
 * - Validate the incoming request through TwoFactorChallengeRequest.
 * - Delegate two-factor challenge orchestration to TwoFactorChallengeAction.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
 *
 * Design notes:
 * - TwoFactorChallengeAction is the source of truth for outcome, flow,
 *   redirect, payload, and error semantics.
 * - Public JSON responses are generated from the standardized action result DTO.
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
    public function __invoke(
        TwoFactorChallengeRequest $request,
        TwoFactorChallengeAction $action
    ): JsonResponse|RedirectResponse {
        $result = $action->handle($request->validated());

        if (ResponseResolver::expectsJson($request)) {
            return $this->ok($result->toArray(), $result->status);
        }

        return $this->toWebResponse($result);
    }

    /**
     * Convert the standardized action result into a web redirect response.
     *
     * @param AuthKitActionResult $result
     * @return RedirectResponse
     */
    protected function toWebResponse(AuthKitActionResult $result): RedirectResponse
    {
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

            return $this->toRouteWithError(
                routeName: $loginRoute,
                parameters: [],
                message: $result->message
            );
        }

        if ($result->hasRedirect() && $result->redirect?->isRoute()) {
            return $this->toRouteWithStatus(
                routeName: $result->redirect->target,
                parameters: $result->redirect->parameters,
                message: $result->message
            );
        }

        return $this->toRouteWithStatus(
            routeName: $loginRoute,
            parameters: [],
            message: $result->message
        );
    }
}