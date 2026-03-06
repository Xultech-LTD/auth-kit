<?php

namespace Xul\AuthKit\Http\Controllers\Web\PasswordReset;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Psr\SimpleCache\InvalidArgumentException;
use Xul\AuthKit\Support\PendingPasswordReset;

/**
 * ResetPasswordViewController
 *
 * Renders the reset-password form page.
 *
 * Access:
 * - Protected by EnsurePendingPasswordResetMiddleware.
 * - For link driver: expects /reset-password/{token}?email=...
 *
 * Notes:
 * - The actual reset action is handled by API/action routes.
 * - For link driver, we also ensure the token matches the email on GET,
 *   so invalid links redirect back to forgot-password (required by tests).
 */
final class ResetPasswordViewController
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @param string $token
     * @param PendingPasswordReset $pending
     * @return View|RedirectResponse
     * @throws InvalidArgumentException
     */
    public function __invoke(Request $request, string $token, PendingPasswordReset $pending): View|RedirectResponse
    {
        $email = mb_strtolower(trim((string) $request->query('email', '')));

        $webNames = (array) config('authkit.route_names.web', []);
        $forgotRoute = (string) ($webNames['password_forgot'] ?? 'authkit.web.password.forgot');

        if ($email === '') {
            return redirect()->route($forgotRoute);
        }

        $driver = (string) config('authkit.password_reset.driver', 'link');

        if ($driver === 'link') {
            $payload = $pending->peekToken($email, $token);

            if ($payload === null) {
                return redirect()->route($forgotRoute);
            }
        }

        return view('authkit::password-reset.reset', [
            'email' => $email,
            'token' => $token,
            'driver' => $driver,
        ]);
    }
}