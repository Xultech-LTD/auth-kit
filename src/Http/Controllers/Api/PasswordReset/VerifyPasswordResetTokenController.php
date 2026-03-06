<?php

namespace Xul\AuthKit\Http\Controllers\Api\PasswordReset;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\PasswordReset\VerifyPasswordResetTokenAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\Http\Requests\PasswordReset\VerifyPasswordResetTokenRequest;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;
use Xul\AuthKit\Actions\PasswordReset\VerifyPasswordResetTokenResult;

/**
 * VerifyPasswordResetTokenController
 *
 * Verifies a reset token/code for token-driver flows.
 *
 * Returns:
 * - JSON for AJAX/JSON requests.
 * - Redirect + flash messages for standard SSR form posts.
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

        $result = $action->execute($email, $token);

        $payload = [
            'ok' => $result->ok,
            'message' => $result->message,
        ];

        $status = $result->ok ? 200 : 422;

        if (! $result->ok && $result->message === VerifyPasswordResetTokenResult::expired()->message) {
            $status = 410;
        }

        if (ResponseResolver::expectsJson($request)) {
            return $this->ok($payload, $status);
        }

        $webNames = (array) config('authkit.route_names.web', []);
        $tokenPageRoute = (string) ($webNames['password_reset_token_page'] ?? 'authkit.web.password.reset.token');

        if (! $result->ok) {
            return $this->toRouteWithError(
                routeName: $tokenPageRoute,
                parameters: ['email' => $email],
                message: $result->message
            );
        }

        return $this->toRouteWithStatus(
            routeName: $tokenPageRoute,
            parameters: ['email' => $email],
            message: $result->message
        );
    }
}