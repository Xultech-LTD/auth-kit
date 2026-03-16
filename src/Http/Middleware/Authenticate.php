<?php

namespace Xul\AuthKit\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Authenticate
 *
 * AuthKit-aware authentication middleware for protected package pages and actions.
 *
 * Responsibilities:
 * - Ensures the current request is authenticated using the configured AuthKit guard.
 * - Redirects unauthenticated browser requests to AuthKit's configured login route.
 * - Preserves Laravel's default JSON/API unauthenticated behavior for non-browser requests.
 *
 * Design notes:
 * - This middleware intentionally replaces direct use of Laravel's default
 *   Authenticate middleware inside AuthKit configuration.
 * - Redirect resolution is route-name driven so consuming applications may
 *   override the AuthKit login route name through configuration.
 */
final class Authenticate extends Middleware
{
    /**
     * Resolve the guards that should be checked for the request.
     *
     * @return array<int, string|null>
     */
    protected function authenticate($request, array $guards): void
    {
        if ($guards === []) {
            $guards = [
                (string) config('authkit.auth.guard', 'web'),
            ];
        }

        parent::authenticate($request, $guards);
    }

    /**
     * Get the path the user should be redirected to when unauthenticated.
     *
     * @param Request $request
     * @return string|null
     */
    protected function redirectTo($request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        $webNames = (array) config('authkit.route_names.web', []);
        $loginRoute = (string) ($webNames['login'] ?? 'authkit.web.login');

        return route($loginRoute);
    }
}