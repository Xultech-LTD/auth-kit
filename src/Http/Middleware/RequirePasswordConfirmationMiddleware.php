<?php

namespace Xul\AuthKit\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RequirePasswordConfirmationMiddleware
 *
 * Enforces a fresh password confirmation for sensitive authenticated pages
 * or actions.
 *
 * Responsibilities:
 * - Check whether AuthKit confirmation features are enabled.
 * - Check whether password confirmation is enabled.
 * - Resolve the current authenticated user from the configured guard.
 * - Determine whether a fresh password-confirmation timestamp exists in session.
 * - Allow the request to continue when confirmation is still fresh.
 * - Store the intended URL in session when confirmation is missing or stale.
 * - Store the requested confirmation type in session for downstream flow handling.
 * - Redirect the user to the configured password-confirmation page.
 *
 * Notes:
 * - This middleware does not validate the submitted password itself.
 *   That responsibility belongs to the confirmation action/controller.
 * - This middleware only protects access by requiring a fresh confirmation marker.
 * - The confirmation page itself should not use this middleware, otherwise it
 *   would redirect back to itself.
 */
final class RequirePasswordConfirmationMiddleware
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
        if (! $this->confirmationSystemEnabled() || ! $this->passwordConfirmationEnabled()) {
            return $next($request);
        }

        $guard = (string) config('authkit.auth.guard', 'web');
        $user = auth($guard)->user();

        if ($user === null) {
            return $next($request);
        }

        if ($this->hasFreshPasswordConfirmation($request)) {
            return $next($request);
        }

        return $this->redirectToPasswordConfirmation($request);
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
     * Determine whether password confirmation is enabled.
     *
     * @return bool
     */
    protected function passwordConfirmationEnabled(): bool
    {
        return (bool) config('authkit.confirmations.password.enabled', true);
    }

    /**
     * Determine whether the current request has a fresh password confirmation.
     *
     * Freshness is determined by:
     * - reading the configured password confirmation session key
     * - ensuring it contains a valid timestamp
     * - ensuring the timestamp is still within the configured TTL window
     *
     * @param  Request  $request
     * @return bool
     */
    protected function hasFreshPasswordConfirmation(Request $request): bool
    {
        $sessionKey = (string) config('authkit.confirmations.session.password_key', 'authkit.confirmed.password_at');
        $ttlMinutes = max(1, (int) config('authkit.confirmations.ttl_minutes.password', 15));

        $confirmedAt = $request->session()->get($sessionKey);

        if (! is_numeric($confirmedAt)) {
            return false;
        }

        return ((int) $confirmedAt + ($ttlMinutes * 60)) >= time();
    }

    /**
     * Redirect the user to the configured password confirmation page.
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
    protected function redirectToPasswordConfirmation(Request $request): RedirectResponse
    {
        $intendedKey = (string) config('authkit.confirmations.session.intended_key', 'authkit.confirmation.intended');
        $typeKey = (string) config('authkit.confirmations.session.type_key', 'authkit.confirmation.type');
        $routeName = (string) config('authkit.confirmations.routes.password', 'authkit.web.confirm.password');

        $request->session()->put($intendedKey, $request->fullUrl());
        $request->session()->put($typeKey, 'password');

        return redirect()->route($routeName);
    }
}