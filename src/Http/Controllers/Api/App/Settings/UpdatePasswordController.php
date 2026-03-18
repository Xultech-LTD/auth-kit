<?php

namespace Xul\AuthKit\Http\Controllers\Api\App\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\App\Settings\UpdatePasswordAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Requests\App\Settings\UpdatePasswordRequest;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * UpdatePasswordController
 *
 * Handles authenticated password update submissions originating from the
 * AuthKit security/settings area.
 *
 * Responsibilities:
 * - Validate the incoming request through UpdatePasswordRequest.
 * - Build the normalized mapped payload for the password-update context.
 * - Delegate password-update business logic to UpdatePasswordAction.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
 *
 * Notes:
 * - This controller is intended for already-authenticated users.
 * - The actual password verification and persistence rules are handled by the
 *   action, not by this controller.
 * - The action is treated as the source of truth for outcome state,
 *   redirect intent, and public payload.
 */
final class UpdatePasswordController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle the incoming password update request.
     *
     * @param  UpdatePasswordRequest  $request
     * @param  UpdatePasswordAction  $action
     * @return JsonResponse|RedirectResponse
     */
    public function __invoke(
        UpdatePasswordRequest $request,
        UpdatePasswordAction $action
    ): JsonResponse|RedirectResponse {
        $guard = (string) config('authkit.auth.guard', 'web');
        $user = $request->user($guard);

        $payload = MappedPayloadBuilder::build('password_update', $request->validated());

        $result = $action->handle(
            user: $user,
            data: $payload
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
     * @param  AuthKitActionResult  $result
     * @return RedirectResponse
     */
    protected function toWebResponse(AuthKitActionResult $result): RedirectResponse
    {
        $securityRoute = (string) data_get(
            config('authkit.route_names.web', []),
            'security',
            'authkit.web.settings.security'
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
                routeName: $securityRoute,
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
            routeName: $securityRoute,
            parameters: [],
            message: $result->message
        );
    }
}