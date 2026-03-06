<?php

namespace Xul\AuthKit\Http\Controllers\Api\EmailVerification;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\EmailVerification\VerifyEmailTokenAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\Http\Requests\EmailVerification\EmailVerificationTokenRequest;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

final class VerifyEmailTokenController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Verify email using a token/code.
     *
     * Returns:
     * - JSON responses for AJAX/JSON requests.
     * - Redirect responses with flash messages for standard SSR form posts.
     */
    public function __invoke(
        EmailVerificationTokenRequest $request,
        VerifyEmailTokenAction $action
    ): JsonResponse|RedirectResponse {
        $result = $action->execute(
            email: (string) data_get($request->validated(), 'email', ''),
            token: (string) data_get($request->validated(), 'token', '')
        );

        $payload = [
            'ok' => $result->ok,
            'message' => $result->message,
        ];

        if (ResponseResolver::expectsJson($request)) {
            return $this->ok($payload, $result->ok ? 200 : 422);
        }

        $webNames = (array) config('authkit.route_names.web', []);
        $noticeRoute = (string) ($webNames['verify_notice'] ?? 'authkit.web.email.verify.notice');

        if (! $result->ok) {
            return $this->toRouteWithError(
                routeName: $noticeRoute,
                parameters: ['email' => (string) $request->input('email', '')],
                message: $result->message
            );
        }

        $mode = (string) data_get(config('authkit.email_verification.post_verify', []), 'mode', 'redirect');

        if ($mode === 'success_page') {
            $successRoute = (string) ($webNames['verify_success'] ?? 'authkit.web.email.verify.success');

            return $this->toRouteWithStatus(
                routeName: $successRoute,
                parameters: [],
                message: $result->message
            );
        }

        $redirectRoute = (string) (data_get(config('authkit.email_verification.post_verify', []), 'redirect_route') ?? '');

        if ($redirectRoute !== '') {
            return redirect()->route($redirectRoute)->with('status', $result->message);
        }

        $loginAfterVerify = (bool) data_get(config('authkit.email_verification.post_verify', []), 'login_after_verify', false);

        if ($loginAfterVerify) {
            $redirectRoute = data_get(config('authkit.login', []), 'redirect_route');
            $dashboardRoute = (string) data_get(config('authkit.login', []), 'dashboard_route', 'dashboard');

            $target = is_string($redirectRoute) && $redirectRoute !== ''
                ? $redirectRoute
                : $dashboardRoute;

            if ($target === '') {
                $target = (string) ($webNames['login'] ?? 'authkit.web.login');
            }

            return redirect()->route($target)->with('status', $result->message);
        }

        $loginRoute = (string) ($webNames['login'] ?? 'authkit.web.login');

        return redirect()->route($loginRoute)->with('status', $result->message);
    }
}