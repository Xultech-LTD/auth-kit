<?php

namespace Xul\AuthKit\Http\Controllers\Api\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Xul\AuthKit\Actions\Auth\LogoutAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * LogoutController
 *
 * Handles logout requests for both JSON and standard web form submissions.
 *
 * Responsibilities:
 * - Delegate logout orchestration to LogoutAction.
 * - Invalidate and regenerate session state after successful logout.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
 *
 * Design notes:
 * - LogoutAction is the source of truth for outcome, flow, redirect, and payload.
 * - Session invalidation remains in the HTTP layer because it is a transport concern.
 */
final class LogoutController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @param LogoutAction $action
     * @return JsonResponse|RedirectResponse
     */
    public function __invoke(Request $request, LogoutAction $action): JsonResponse|RedirectResponse
    {
        $result = $action->handle();

        if ($result->ok) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

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
        $loginRoute = (string) data_get(
            config('authkit.route_names.web', []),
            'login',
            'authkit.web.login'
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
                routeName: $loginRoute,
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
            routeName: $loginRoute,
            parameters: [],
            message: $result->message
        );
    }
}