<?php

namespace Xul\AuthKit\Http\Controllers\Api\App\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\App\Settings\RegenerateTwoFactorRecoveryCodesAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Requests\App\Settings\RegenerateTwoFactorRecoveryCodesRequest;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * RegenerateTwoFactorRecoveryCodesController
 *
 * Handles authenticated submissions that regenerate two-factor recovery codes
 * from the AuthKit settings area.
 *
 * Responsibilities:
 * - Validate the incoming request through RegenerateTwoFactorRecoveryCodesRequest.
 * - Build the normalized mapped payload for the recovery-regeneration context.
 * - Delegate recovery-code regeneration business logic to
 *   RegenerateTwoFactorRecoveryCodesAction.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
 *
 * Notes:
 * - This controller is intentionally thin.
 * - The action is responsible for OTP verification, regeneration, persistence,
 *   and flashing/returning the newly generated recovery codes.
 */
final class RegenerateTwoFactorRecoveryCodesController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle the incoming recovery-code regeneration request.
     *
     * @param  RegenerateTwoFactorRecoveryCodesRequest  $request
     * @param  RegenerateTwoFactorRecoveryCodesAction  $action
     * @return JsonResponse|RedirectResponse
     */
    public function __invoke(
        RegenerateTwoFactorRecoveryCodesRequest $request,
        RegenerateTwoFactorRecoveryCodesAction $action
    ): JsonResponse|RedirectResponse {
        $guard = (string) config('authkit.auth.guard', 'web');
        $user = $request->user($guard);

        $payload = MappedPayloadBuilder::build(
            'two_factor_recovery_regenerate',
            $request->validated()
        );

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