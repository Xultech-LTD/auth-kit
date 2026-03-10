<?php

namespace Xul\AuthKit\Http\Controllers\Api\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\Auth\RegisterAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Requests\Auth\RegisterRequest;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * RegisterController
 *
 * Handles registration requests for both JSON and standard web form submissions.
 *
 * Responsibilities:
 * - Validate the incoming request through RegisterRequest.
 * - Delegate registration flow orchestration to RegisterAction.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
 *
 * Design notes:
 * - RegisterAction is the source of truth for outcome, flow, redirect, and payload.
 * - Public JSON responses are generated from the standardized action result DTO.
 */
final class RegisterController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle a registration request.
     *
     * @param RegisterRequest $request
     * @param RegisterAction $action
     * @return JsonResponse|RedirectResponse
     */
    public function __invoke(RegisterRequest $request, RegisterAction $action): JsonResponse|RedirectResponse
    {
        $result = $action->handle($request->validated());

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
        $noticeRoute = (string) data_get(
            config('authkit.route_names.web', []),
            'verify_notice',
            'authkit.web.email.verify.notice'
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
                routeName: $noticeRoute,
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
            routeName: $noticeRoute,
            parameters: [],
            message: $result->message
        );
    }
}