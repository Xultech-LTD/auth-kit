<?php

namespace Xul\AuthKit\Http\Controllers\Api\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\Auth\LoginAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Requests\Auth\LoginRequest;
use Xul\AuthKit\Support\AuthKitSessionKeys;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * LoginController
 *
 * Handles login requests for both JSON and standard web form submissions.
 *
 * Responsibilities:
 * - Validate the incoming request through LoginRequest.
 * - Build the normalized mapped payload for the login context.
 * - Delegate login flow orchestration to LoginAction.
 * - Persist internal two-factor challenge state to session when required.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
 *
 * Design notes:
 * - This controller treats LoginAction as the source of truth for flow state,
 *   redirect intent, public payload, and internal transport data.
 * - Internal payload is never exposed in JSON responses.
 * - Flow branching is driven by AuthKitActionResult and AuthKitFlowStep.
 */
final class LoginController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle a login request.
     *
     * @param LoginRequest $request
     * @param LoginAction $action
     * @return JsonResponse|RedirectResponse
     * @throws \Throwable
     */
    public function __invoke(LoginRequest $request, LoginAction $action): JsonResponse|RedirectResponse
    {
        $payload = MappedPayloadBuilder::build('login', $request->validated());

        $result = $action->handle($payload);

        $this->persistInternalState($request, $result);

        if (ResponseResolver::expectsJson($request)) {
            return $this->ok($result->toArray(), $result->status);
        }

        return $this->toWebResponse($result);
    }

    /**
     * Persist internal transport state required by the current login result.
     *
     * @param LoginRequest $request
     * @param AuthKitActionResult $result
     * @return void
     */
    protected function persistInternalState(LoginRequest $request, AuthKitActionResult $result): void
    {
        if (! $result->hasInternal()) {
            return;
        }

        if (! $result->flow?->is('two_factor_required')) {
            return;
        }

        $challenge = (string) $result->internal?->get('challenge', '');

        if ($challenge === '') {
            return;
        }

        $request->session()->put(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE, $challenge);
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