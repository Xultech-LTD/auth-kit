<?php

namespace Xul\AuthKit\Http\Controllers\Api\PasswordReset;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\PasswordReset\RequestPasswordResetAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\Http\Requests\PasswordReset\ForgotPasswordRequest;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * ForgotPasswordController
 *
 * Starts a password reset flow by requesting a reset link/code.
 *
 * Returns:
 * - JSON for AJAX/JSON requests.
 * - Redirect + flash messages for standard SSR form posts.
 */
final class ForgotPasswordController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    public function __invoke(
        ForgotPasswordRequest $request,
        RequestPasswordResetAction $action
    ): JsonResponse|RedirectResponse {
        $email = (string) data_get($request->validated(), 'email', '');

        $result = $action->execute($email);

        $payload = [
            'ok' => $result->ok,
            'message' => $result->message,
        ];

        if (ResponseResolver::expectsJson($request)) {
            return $this->ok($payload, $result->ok ? 200 : 422);
        }

        $webNames = (array) config('authkit.route_names.web', []);
        $forgotRoute = (string) ($webNames['password_forgot'] ?? 'authkit.web.password.forgot');

        if (! $result->ok) {
            return $this->toRouteWithError(
                routeName: $forgotRoute,
                parameters: [],
                message: $result->message
            );
        }

        // Where to send the browser after requesting reset instructions.
        $mode = (string) data_get(config('authkit.password_reset.post_request', []), 'mode', 'sent_page');

        if ($mode === 'token_page') {
            $tokenRoute = (string) data_get(
                config('authkit.password_reset.post_request', []),
                'token_route',
                'authkit.web.password.reset.token'
            );

            return $this->toRouteWithStatus(
                routeName: $tokenRoute,
                parameters: ['email' => $email],
                message: $result->message
            );
        }

        $sentRoute = (string) data_get(
            config('authkit.password_reset.post_request', []),
            'sent_route',
            (string) ($webNames['password_forgot_sent'] ?? 'authkit.web.password.forgot.sent')
        );

        return $this->toRouteWithStatus(
            routeName: $sentRoute,
            parameters: ['email' => $email],
            message: $result->message
        );
    }
}