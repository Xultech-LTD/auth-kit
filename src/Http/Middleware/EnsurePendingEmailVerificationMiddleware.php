<?php

namespace Xul\AuthKit\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Xul\AuthKit\Support\PendingEmailVerification;

/**
 * EnsurePendingEmailVerificationMiddleware
 *
 * Ensures the current request has a valid pending email verification context.
 *
 * Supported flows:
 * - Link driver: validates {id}/{hash} against a user record and confirms token existence (peek-only).
 * - Token driver: requires an email in request context and checks that a pending
 *   verification exists for that email (presence only).
 *
 * If no valid pending context exists, the request is redirected to the login page.
 */
final class EnsurePendingEmailVerificationMiddleware
{
    /**
     * Create a new instance.
     *
     * @param PendingEmailVerification $pending
     */
    public function __construct(
        protected PendingEmailVerification $pending
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     *
     * @return Response|RedirectResponse
     * @throws InvalidArgumentException
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $driver = (string) config('authkit.email_verification.driver', 'link');

        if ($driver === 'link') {
            return $this->handleLinkDriver($request, $next);
        }

        return $this->handleTokenDriver($request, $next);
    }

    /**
     * Validate link-based verification context.
     *
     * Rules:
     * - If {id}/{hash} are present, validate them against a user record and token presence.
     * - Otherwise, require an email context and a pending presence for that email.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     *
     * @return Response|RedirectResponse
     * @throws InvalidArgumentException
     */
    protected function handleLinkDriver(Request $request, Closure $next): Response|RedirectResponse
    {
        $id = (string) $request->route('id', '');
        $hash = (string) $request->route('hash', '');

        if ($id !== '' && $hash !== '') {
            $ok = $this->pending->isLinkContextValid($id, $hash);

            if (!$ok) {
                return $this->redirectToLogin();
            }

            return $next($request);
        }

        $email = $this->emailFromRequest($request);

        if ($email === '') {
            return $this->redirectToLogin();
        }

        if (!$this->pending->hasPendingForEmail($email)) {
            return $this->redirectToLogin();
        }

        return $next($request);
    }

    /**
     * Validate token-based verification context.
     *
     * Requires:
     * - email context
     * - pending presence for that email
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     *
     * @return Response|RedirectResponse
     * @throws InvalidArgumentException
     */
    protected function handleTokenDriver(Request $request, Closure $next): Response|RedirectResponse
    {
        $email = $this->emailFromRequest($request);

        if ($email === '') {
            return $this->redirectToLogin();
        }

        if (!$this->pending->hasPendingForEmail($email)) {
            return $this->redirectToLogin();
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
     * Redirect to the configured login page.
     *
     * @return RedirectResponse
     */
    protected function redirectToLogin(): RedirectResponse
    {
        $login = (string) (data_get(config('authkit.route_names.web', []), 'login') ?? 'authkit.web.login');

        return redirect()->route($login);
    }
}