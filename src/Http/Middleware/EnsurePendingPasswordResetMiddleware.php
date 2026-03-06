<?php

namespace Xul\AuthKit\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Xul\AuthKit\Support\PendingPasswordReset;

/**
 * EnsurePendingPasswordResetMiddleware
 *
 * Ensures the current request has a valid pending password reset context.
 *
 * Supported flows:
 * - Link driver: user arrives via a reset link and then completes reset via API/action endpoint.
 * - Token driver: user enters a reset code on a dedicated page and then completes reset via API/action endpoint.
 *
 * Requirements (all drivers):
 * - email context via query string (?email=...)
 * - pending presence for that email
 *
 * Notes:
 * - This middleware intentionally does NOT validate the reset token on the web (GET) route.
 *   Token validation and consumption happen when the user submits to the API/action endpoint.
 *
 * If no valid pending context exists, the request is redirected to the forgot-password page.
 */
final class EnsurePendingPasswordResetMiddleware
{
    /**
     * Create a new instance.
     *
     * @param PendingPasswordReset $pending
     */
    public function __construct(
        protected PendingPasswordReset $pending
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response|RedirectResponse
     * @throws InvalidArgumentException
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $email = $this->emailFromRequest($request);

        if ($email === '') {
            return $this->redirectToForgot();
        }

        if (!$this->pending->hasPendingForEmail($email)) {
            return $this->redirectToForgot();
        }

        return $next($request);
    }

    /**
     * Extract email context from request.
     *
     * @param Request $request
     * @return string
     */
    protected function emailFromRequest(Request $request): string
    {
        return mb_strtolower(trim((string) $request->query('email', '')));
    }

    /**
     * Redirect to the configured forgot-password page.
     *
     * @return RedirectResponse
     */
    protected function redirectToForgot(): RedirectResponse
    {
        $webNames = (array) config('authkit.route_names.web', []);
        $routeName = (string) ($webNames['password_forgot'] ?? 'authkit.web.password.forgot');

        return redirect()->route($routeName);
    }
}