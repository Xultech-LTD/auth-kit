<?php

namespace Xul\AuthKit\Http\Controllers\Api\PasswordReset;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\PasswordReset\RequestPasswordResetAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Requests\PasswordReset\ForgotPasswordRequest;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * ForgotPasswordController
 *
 * Starts a password reset flow by requesting a reset link or reset code.
 *
 * Responsibilities:
 * - Validate the incoming request through ForgotPasswordRequest.
 * - Build the normalized mapped payload for the forgot-password context.
 * - Delegate reset request orchestration to RequestPasswordResetAction.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
 */
final class ForgotPasswordController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle the incoming request.
     *
     * @param ForgotPasswordRequest $request
     * @param RequestPasswordResetAction $action
     * @return JsonResponse|RedirectResponse
     */
    public function __invoke(
        ForgotPasswordRequest $request,
        RequestPasswordResetAction $action
    ): JsonResponse|RedirectResponse {
        $payload = MappedPayloadBuilder::build('password_forgot', $request->validated());

        $result = $action->handle($payload);

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
        $webNames = (array) config('authkit.route_names.web', []);
        $forgotRoute = (string) ($webNames['password_forgot'] ?? 'authkit.web.password.forgot');

        if (! $result->ok) {
            if ($result->hasRedirect() && $result->redirect?->isRoute()) {
                return $this->toRouteWithError(
                    routeName: $result->redirect->target,
                    parameters: $result->redirect->parameters,
                    message: $result->message
                );
            }

            return $this->toRouteWithError(
                routeName: $forgotRoute,
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
            routeName: $forgotRoute,
            parameters: [],
            message: $result->message
        );
    }
}