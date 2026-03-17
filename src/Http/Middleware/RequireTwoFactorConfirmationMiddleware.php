<?php

namespace Xul\AuthKit\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RequireTwoFactorConfirmationMiddleware
 *
 * Enforces a fresh two-factor confirmation for sensitive authenticated pages
 * or actions.
 *
 * Responsibilities:
 * - Check whether AuthKit confirmation features are enabled.
 * - Check whether two-factor confirmation is enabled.
 * - Resolve the current authenticated user from the configured guard.
 * - Confirm whether the authenticated user has two-factor authentication enabled.
 * - Redirect users without two-factor enabled to the two-factor settings/setup page.
 * - Determine whether a fresh two-factor confirmation timestamp exists in session.
 * - Allow the request to continue when confirmation is still fresh.
 * - Store the intended URL in session when confirmation is missing or stale.
 * - Store the requested confirmation type in session for downstream flow handling.
 * - Redirect the user to the configured two-factor confirmation page.
 *
 * Notes:
 * - This middleware does not validate the submitted authentication code itself.
 *   That responsibility belongs to the confirmation action/controller.
 * - This middleware is distinct from the login-time two-factor challenge flow.
 * - This middleware protects already-authenticated users who are trying to
 *   access a sensitive page or perform a sensitive action.
 */
final class RequireTwoFactorConfirmationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure(Request): Response  $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->confirmationSystemEnabled() || ! $this->twoFactorConfirmationEnabled()) {
            return $next($request);
        }

        $guard = (string) config('authkit.auth.guard', 'web');
        $user = auth($guard)->user();

        if ($user === null) {
            return $next($request);
        }

//        if (! $this->userHasTwoFactorEnabled($user)) {
//            return $this->redirectToTwoFactorSetup($request);
//        }

        if ($this->hasFreshTwoFactorConfirmation($request)) {
            return $next($request);
        }

        return $this->redirectToTwoFactorConfirmation($request);
    }

    /**
     * Determine whether the overall confirmation feature is enabled.
     *
     * @return bool
     */
    protected function confirmationSystemEnabled(): bool
    {
        return (bool) config('authkit.confirmations.enabled', true);
    }

    /**
     * Determine whether two-factor confirmation is enabled.
     *
     * @return bool
     */
    protected function twoFactorConfirmationEnabled(): bool
    {
        return (bool) config('authkit.confirmations.two_factor.enabled', true);
    }

    /**
     * Determine whether the authenticated user currently has two-factor enabled.
     *
     * Resolution order:
     * - hasTwoFactorEnabled() model method
     * - configured enabled column from authkit.two_factor.columns.enabled
     *
     * @param  mixed  $user
     * @return bool
     */
    protected function userHasTwoFactorEnabled(mixed $user): bool
    {
        if (! is_object($user)) {
            return false;
        }

        if (method_exists($user, 'hasTwoFactorEnabled')) {
            return (bool) $user->hasTwoFactorEnabled();
        }

        $enabledColumn = (string) config('authkit.two_factor.columns.enabled', 'two_factor_enabled');

        return (bool) data_get($user, $enabledColumn, false);
    }

    /**
     * Determine whether the current request has a fresh two-factor confirmation.
     *
     * Freshness is determined by:
     * - reading the configured two-factor confirmation session key
     * - ensuring it contains a valid timestamp
     * - ensuring the timestamp is still within the configured TTL window
     *
     * @param  Request  $request
     * @return bool
     */
    protected function hasFreshTwoFactorConfirmation(Request $request): bool
    {
        $sessionKey = (string) config('authkit.confirmations.session.two_factor_key', 'authkit.confirmed.two_factor_at');
        $ttlMinutes = max(1, (int) config('authkit.confirmations.ttl_minutes.two_factor', 10));

        $confirmedAt = $request->session()->get($sessionKey);

        if (! is_numeric($confirmedAt)) {
            return false;
        }

        return ((int) $confirmedAt + ($ttlMinutes * 60)) >= time();
    }

    /**
     * Redirect the user to the configured two-factor confirmation page.
     *
     * Before redirecting, this method stores:
     * - the intended destination URL
     * - the requested confirmation type
     *
     * These session values allow the confirmation action to redirect the user
     * back to the protected destination after successful confirmation.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    protected function redirectToTwoFactorConfirmation(Request $request): RedirectResponse
    {
        $intendedKey = (string) config('authkit.confirmations.session.intended_key', 'authkit.confirmation.intended');
        $typeKey = (string) config('authkit.confirmations.session.type_key', 'authkit.confirmation.type');
        $routeName = (string) config('authkit.confirmations.routes.two_factor', 'authkit.web.confirm.two_factor');

        $request->session()->put($intendedKey, $request->fullUrl());
        $request->session()->put($typeKey, 'two_factor');

        return redirect()->route($routeName);
    }

    /**
     * Redirect the user to the dedicated two-factor settings/setup page.
     *
     * This is used when the user is being asked for a two-factor confirmation
     * but has not actually enabled two-factor authentication yet.
     *
     * The intended URL is still stored so the consuming application may later
     * decide whether to return the user after setup/confirmation is complete.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    protected function redirectToTwoFactorSetup(Request $request): RedirectResponse
    {
        $intendedKey = (string) config('authkit.confirmations.session.intended_key', 'authkit.confirmation.intended');
        $typeKey = (string) config('authkit.confirmations.session.type_key', 'authkit.confirmation.type');
        $routeName = (string) config('authkit.route_names.web.two_factor_settings', 'authkit.web.settings.two_factor');

        $request->session()->put($intendedKey, $request->fullUrl());
        $request->session()->put($typeKey, 'two_factor');

        return redirect()->route($routeName)->with(
            'error',
            'Please enable two-factor authentication before accessing this protected area.'
        );
    }
}