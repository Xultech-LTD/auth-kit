<?php

namespace Xul\AuthKit\Http\Controllers\Api\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Throwable;
use Xul\AuthKit\Actions\Auth\TwoFactorRecoveryAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Requests\Auth\TwoFactorRecoveryRequest;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * TwoFactorRecoveryController
 *
 * Handles recovery-code completion of a pending two-factor login challenge.
 *
 * Responsibilities:
 * - Validate the incoming request through TwoFactorRecoveryRequest.
 * - Build the normalized mapped payload for the recovery context.
 * - Delegate recovery-code authentication flow to TwoFactorRecoveryAction.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
 *
 * Design notes:
 * - TwoFactorRecoveryAction is the source of truth for outcome, flow,
 *   redirect, payload, and error semantics.
 * - Public JSON responses are generated from the standardized action result DTO.
 */
final class TwoFactorRecoveryController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle a two-factor recovery request.
     *
     * @param TwoFactorRecoveryRequest $request
     * @param TwoFactorRecoveryAction $action
     * @return JsonResponse|RedirectResponse
     * @throws Throwable
     */
    public function __invoke(
        TwoFactorRecoveryRequest $request,
        TwoFactorRecoveryAction $action
    ): JsonResponse|RedirectResponse {
        $payload = MappedPayloadBuilder::build('two_factor_recovery', $request->validated());

        $result = $action->handle($payload);

        if (ResponseResolver::expectsJson($request)) {
            return $this->ok($result->toArray(), $result->status);
        }

        $challenge = (string) data_get($payload, 'attributes.challenge', '');

        return $this->toWebResponse($result, $challenge);
    }

    /**
     * Convert the standardized action result into a web redirect response.
     *
     * @param AuthKitActionResult $result
     * @param string $challenge
     * @return RedirectResponse
     */
    protected function toWebResponse(AuthKitActionResult $result, string $challenge): RedirectResponse
    {
        $loginRoute = (string) data_get(
            config('authkit.route_names.web', []),
            'login',
            'authkit.web.login'
        );

        $twoFactorRoute = (string) data_get(
            config('authkit.route_names.web', []),
            'two_factor_challenge',
            'authkit.web.twofactor.challenge'
        );

        if (! $result->ok) {
            if ($result->status === 410) {
                return $this->toRouteWithError(
                    routeName: $loginRoute,
                    parameters: [],
                    message: $result->message
                );
            }

            if ($result->hasRedirect() && $result->redirect?->isRoute()) {
                return $this->toRouteWithError(
                    routeName: $result->redirect->target,
                    parameters: $result->redirect->parameters,
                    message: $result->message
                );
            }

            return $this->toRouteWithError(
                routeName: $twoFactorRoute,
                parameters: $challenge !== '' ? ['c' => $challenge] : [],
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