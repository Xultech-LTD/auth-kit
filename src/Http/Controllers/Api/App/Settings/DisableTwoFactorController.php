<?php

namespace Xul\AuthKit\Http\Controllers\Api\App\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\App\Settings\DisableTwoFactorAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Requests\App\Settings\DisableTwoFactorRequest;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * DisableTwoFactorController
 *
 * Handles authenticated submissions that disable two-factor authentication from
 * the AuthKit settings area.
 *
 * Responsibilities:
 * - Validate the incoming request through DisableTwoFactorRequest.
 * - Delegate two-factor disable business logic to DisableTwoFactorAction.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
 *
 * Notes:
 * - This controller is intentionally thin.
 * - The action is responsible for verifying either the authenticator code or
 *   recovery code, disabling two-factor, and clearing related state.
 */
final class DisableTwoFactorController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle the incoming disable-two-factor request.
     *
     * @param  DisableTwoFactorRequest  $request
     * @param  DisableTwoFactorAction  $action
     * @return JsonResponse|RedirectResponse
     */
    public function __invoke(
        DisableTwoFactorRequest $request,
        DisableTwoFactorAction $action
    ): JsonResponse|RedirectResponse {
        $guard = (string) config('authkit.auth.guard', 'web');
        $user = $request->user($guard);

        $result = $action->handle(
            user: $user,
            data: $request->validated()
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
        $twoFactorRoute = (string) data_get(
            config('authkit.route_names.web', []),
            'two_factor_settings',
            'authkit.web.settings.two_factor'
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
                routeName: $twoFactorRoute,
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
            routeName: $twoFactorRoute,
            parameters: [],
            message: $result->message
        );
    }
}