<?php

namespace Xul\AuthKit\Http\Controllers\Api\App\Confirmations;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\App\Confirmations\ConfirmPasswordAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Requests\App\Confirmations\ConfirmPasswordRequest;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * ConfirmPasswordController
 *
 * Handles authenticated password-confirmation submissions used by AuthKit's
 * step-up security flow.
 *
 * Responsibilities:
 * - Validate the incoming request through ConfirmPasswordRequest.
 * - Build the normalized mapped payload for the confirm-password context.
 * - Delegate password confirmation verification to ConfirmPasswordAction.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
 *
 * Notes:
 * - This controller is intended for already-authenticated users only.
 * - Session persistence for confirmation freshness is intentionally handled
 *   inside the action so the same action remains reusable across alternate
 *   controllers or transport layers.
 */
final class ConfirmPasswordController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle the incoming password confirmation request.
     *
     * @param  ConfirmPasswordRequest  $request
     * @param  ConfirmPasswordAction  $action
     * @return JsonResponse|RedirectResponse
     */
    public function __invoke(
        ConfirmPasswordRequest $request,
        ConfirmPasswordAction $action
    ): JsonResponse|RedirectResponse {
        $guard = (string) config('authkit.auth.guard', 'web');
        $user = $request->user($guard);

        $payload = MappedPayloadBuilder::build('confirm_password', $request->validated());

        $result = $action->handle(
            user: $user,
            data: $payload,
            session: $request->session(),
        );

        if (ResponseResolver::expectsJson($request)) {
            return $result->ok
                ? $this->ok($result->toArray(), $result->status)
                : $this->fail($result->toArray(), $result->status);
        }

        return $this->toWebResponse($result);
    }

    /**
     * Convert the standardized action result into a web redirect response.
     *
     * Resolution order:
     * - Action-provided route redirect
     * - Action-provided URL redirect
     * - Configured password confirmation page
     *
     * @param  AuthKitActionResult  $result
     * @return RedirectResponse
     */
    protected function toWebResponse(AuthKitActionResult $result): RedirectResponse
    {
        $confirmRoute = (string) data_get(
            config('authkit.route_names.web', []),
            'confirm_password',
            'authkit.web.confirm.password'
        );

        if (! $result->ok) {
            if ($result->hasRedirect() && $result->redirect?->isRoute()) {
                return $this->toRouteWithError(
                    routeName: $result->redirect->target,
                    parameters: $result->redirect->parameters,
                    message: $result->message
                );
            }

            if ($result->hasRedirect() && $result->redirect?->isUrl()) {
                $redirect = redirect()->to($result->redirect->target);

                if ($result->message !== '') {
                    $redirect->with('error', $result->message);
                }

                return $redirect;
            }

            return $this->toRouteWithError(
                routeName: $confirmRoute,
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

        if ($result->hasRedirect() && $result->redirect?->isUrl()) {
            $redirect = redirect()->to($result->redirect->target);

            if ($result->message !== '') {
                $redirect->with('status', $result->message);
            }

            return $redirect;
        }

        return $this->toRouteWithStatus(
            routeName: $confirmRoute,
            parameters: [],
            message: $result->message
        );
    }
}