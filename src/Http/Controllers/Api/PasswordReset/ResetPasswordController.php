<?php

namespace Xul\AuthKit\Http\Controllers\Api\PasswordReset;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\PasswordReset\ResetPasswordAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Requests\PasswordReset\ResetPasswordRequest;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * ResetPasswordController
 *
 * Completes a password reset using an email, token or code, and new password.
 *
 * Responsibilities:
 * - Validate the incoming request through ResetPasswordRequest.
 * - Build the normalized mapped payload for the reset-password context.
 * - Delegate reset orchestration to ResetPasswordAction.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
 */
final class ResetPasswordController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle the incoming request.
     *
     * @param ResetPasswordRequest $request
     * @param ResetPasswordAction $action
     * @return JsonResponse|RedirectResponse
     */
    public function __invoke(
        ResetPasswordRequest $request,
        ResetPasswordAction $action
    ): JsonResponse|RedirectResponse {
        $payload = MappedPayloadBuilder::build('password_reset', $request->validated());

        $result = $action->handle($payload);

        if (ResponseResolver::expectsJson($request)) {
            return $this->ok($result->toArray(), $result->status);
        }

        $email = (string) data_get($payload, 'attributes.email', '');
        $token = (string) data_get($payload, 'attributes.token', '');

        return $this->toWebResponse($result, $email, $token);
    }

    /**
     * Convert the standardized action result into a web redirect response.
     *
     * @param AuthKitActionResult $result
     * @param string $email
     * @param string $token
     * @return RedirectResponse
     */
    protected function toWebResponse(AuthKitActionResult $result, string $email, string $token): RedirectResponse
    {
        $webNames = (array) config('authkit.route_names.web', []);
        $resetRoute = (string) ($webNames['password_reset'] ?? 'authkit.web.password.reset');

        if (! $result->ok) {
            if ($result->hasRedirect() && $result->redirect?->isRoute()) {
                return $this->toRouteWithError(
                    routeName: $result->redirect->target,
                    parameters: $result->redirect->parameters,
                    message: $result->message
                );
            }

            return $this->toRouteWithError(
                routeName: $resetRoute,
                parameters: ['token' => $token, 'email' => $email],
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
            routeName: $resetRoute,
            parameters: ['token' => $token, 'email' => $email],
            message: $result->message
        );
    }
}