<?php

namespace Xul\AuthKit\Http\Controllers\Web\EmailVerification;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Xul\AuthKit\Actions\EmailVerification\VerifyEmailLinkAction;

/**
 * VerifyEmailLinkController
 *
 * Handles signed email verification link requests.
 *
 * This controller delegates verification logic to VerifyEmailLinkAction and
 * applies AuthKit UX configuration for post-verification navigation.
 */
final class VerifyEmailLinkController
{
    /**
     * Create a new instance.
     *
     * @param VerifyEmailLinkAction $action
     */
    public function __construct(
        protected VerifyEmailLinkAction $action
    ) {}

    /**
     * Handle the incoming request.
     *
     * Route params:
     * - id: user identifier
     * - hash: raw verification token
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $id = (string) $request->route('id', '');
        $hash = (string) $request->route('hash', '');

        $result = $this->action->execute($id, $hash);

        if (!$result->ok) {
            return $this->redirectToLogin()
                ->with('error', $result->message);
        }

        $mode = (string) data_get(config('authkit.email_verification.post_verify', []), 'mode', 'redirect');

        if ($mode === 'success_page') {
            return $this->redirectToSuccessPage()
                ->with('status', $result->message);
        }

        return $this->redirectAfterSuccess()
            ->with('status', $result->message);
    }

    /**
     * Redirect after successful verification (mode=redirect).
     *
     * @return RedirectResponse
     */
    protected function redirectAfterSuccess(): RedirectResponse
    {
        $redirectRoute = (string) (data_get(config('authkit.email_verification.post_verify', []), 'redirect_route') ?? '');

        if ($redirectRoute !== '') {
            return redirect()->route($redirectRoute);
        }

        $loginAfterVerify = (bool) data_get(config('authkit.email_verification.post_verify', []), 'login_after_verify', false);

        if ($loginAfterVerify) {
            $redirectRoute = data_get(config('authkit.login', []), 'redirect_route');
            $dashboardRoute = (string) data_get(config('authkit.login', []), 'dashboard_route', 'dashboard');

            $target = is_string($redirectRoute) && $redirectRoute !== ''
                ? $redirectRoute
                : $dashboardRoute;

            if ($target !== '') {
                return redirect()->route($target);
            }
        }

        return $this->redirectToLogin();
    }

    /**
     * Redirect to AuthKit success page (mode=success_page).
     *
     * @return RedirectResponse
     */
    protected function redirectToSuccessPage(): RedirectResponse
    {
        $webNames = (array) config('authkit.route_names.web', []);
        $fallback = (string) data_get(config('authkit.email_verification.post_verify', []), 'success_route', 'authkit.web.email.verify.success');

        $routeName = (string) ($webNames['verify_success'] ?? $fallback);

        return redirect()->route($routeName);
    }

    /**
     * Redirect to the configured login route.
     *
     * @return RedirectResponse
     */
    protected function redirectToLogin(): RedirectResponse
    {
        $login = (string) data_get(
            config('authkit.route_names.web', []),
            'login',
            (string) data_get(config('authkit.email_verification.post_verify', []), 'login_route', 'authkit.web.login')
        );

        return redirect()->route($login);
    }

}