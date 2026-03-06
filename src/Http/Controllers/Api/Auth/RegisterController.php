<?php

namespace Xul\AuthKit\Http\Controllers\Api\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\Http\Requests\Auth\RegisterRequest;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;
use Xul\AuthKit\Actions\Auth\RegisterAction;

final class RegisterController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle a registration request.
     *
     * Returns:
     * - JSON responses for AJAX/JSON requests.
     * - Redirect responses with flash messages for standard SSR form posts.
     */
    public function __invoke(RegisterRequest $request, RegisterAction $action): JsonResponse|RedirectResponse
    {
        $result = $action->handle($request->validated());

        if (ResponseResolver::expectsJson($request)) {
            return $this->ok($result, 201);
        }

        $route = (string) config('authkit.route_names.web.verify_notice', 'authkit.web.email.verify.notice');

        return $this->toRouteWithStatus(
            routeName: $route,
            parameters: (array) ($result['redirect_params'] ?? []),
            message: (string) ($result['message'] ?? 'Account created. Please verify your email.')
        );
    }
}