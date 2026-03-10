<?php

namespace Xul\AuthKit\Http\Controllers\Api\PasswordReset;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\PasswordReset\VerifyPasswordResetTokenAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Requests\PasswordReset\VerifyPasswordResetTokenRequest;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * VerifyPasswordResetTokenController
 *
 * Verifies a reset token or code for token-driver flows and completes
 * the password reset on the same submission.
 *
 * Responsibilities:
 * - Validate the incoming request through VerifyPasswordResetTokenRequest.
 * - Delegate token verification and password reset orchestration to VerifyPasswordResetTokenAction.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
 */
final class VerifyPasswordResetTokenController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle the incoming request.
     *
     * @param VerifyPasswordResetTokenRequest $request
     * @param VerifyPasswordResetTokenAction $action
     * @return JsonResponse|RedirectResponse
     * @throws \Throwable
     */
    public function __invoke(
        VerifyPasswordResetTokenRequest $request,
        VerifyPasswordResetTokenAction $action
    ): JsonResponse|RedirectResponse {
        $email = (string) data_get($request->validated(), 'email', '');
        $token = (string) data_get($request->validated(), 'token', '');
        $password = (string) data_get($request->validated(), 'password', '');

        $result = $action->handle($email, $token, $password);

        if (ResponseResolver::expectsJson($request)) {
            return $this->ok($result->toArray(), $result->status);
        }

        return $this->toWebResponse($result, $email);
    }

    /**
     * Convert the standardized action result into a web redirect response.
     *
     * @param AuthKitActionResult $result
     * @param string $email
     * @return RedirectResponse
     */
    protected function toWebResponse(AuthKitActionResult $result, string $email): RedirectResponse
    {
        $webNames = (array) config('authkit.route_names.web', []);
        $tokenPageRoute = (string) ($webNames['password_reset_token_page'] ?? 'authkit.web.password.reset.token');
        $loginRoute = (string) ($webNames['login'] ?? 'authkit.web.login');

        if (! $result->ok) {
            if ($result->hasRedirect() && $result->redirect?->isRoute()) {
                return $this->toRouteWithError(
                    routeName: $result->redirect->target,
                    parameters: $result->redirect->parameters,
                    message: $result->message
                );
            }

            return $this->toRouteWithError(
                routeName: $tokenPageRoute,
                parameters: ['email' => $email],
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