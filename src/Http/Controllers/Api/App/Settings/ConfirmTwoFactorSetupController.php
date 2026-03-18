<?php

namespace Xul\AuthKit\Http\Controllers\Api\App\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\App\Settings\ConfirmTwoFactorSetupAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Requests\App\Settings\ConfirmTwoFactorSetupRequest;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * ConfirmTwoFactorSetupController
 *
 * Handles authenticated submissions that finalize two-factor setup from the
 * AuthKit settings area.
 *
 * Responsibilities:
 * - Validate the incoming request through ConfirmTwoFactorSetupRequest.
 * - Build the normalized mapped payload for the two-factor setup confirmation context.
 * - Delegate two-factor confirmation business logic to ConfirmTwoFactorSetupAction.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
 *
 * Notes:
 * - This controller is intentionally thin.
 * - The action is responsible for verification, persistence, and recovery-code
 *   generation so the same action remains reusable even when consumers replace
 *   the packaged controller through configuration.
 */
final class ConfirmTwoFactorSetupController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle the incoming two-factor setup confirmation request.
     *
     * @param  ConfirmTwoFactorSetupRequest  $request
     * @param  ConfirmTwoFactorSetupAction  $action
     * @return JsonResponse|RedirectResponse
     */
    public function __invoke(
        ConfirmTwoFactorSetupRequest $request,
        ConfirmTwoFactorSetupAction $action
    ): JsonResponse|RedirectResponse {
        $guard = (string) config('authkit.auth.guard', 'web');
        $user = $request->user($guard);

        $payload = MappedPayloadBuilder::build('two_factor_confirm', $request->validated());

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