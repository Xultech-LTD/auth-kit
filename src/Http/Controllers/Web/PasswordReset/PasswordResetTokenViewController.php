<?php

namespace Xul\AuthKit\Http\Controllers\Web\PasswordReset;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * PasswordResetTokenViewController
 *
 * Renders the token-entry page for password reset when the password_reset driver is "token".
 *
 * Access:
 * - Protected by EnsurePendingPasswordResetMiddleware.
 * - Requires a pending reset presence for the provided email (?email=...).
 *
 * Notes:
 * - Token verification and password reset are handled by API/action routes.
 */
final class PasswordResetTokenViewController
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return View
     */
    public function __invoke(Request $request): View
    {
        $email = mb_strtolower(trim((string) $request->query('email', '')));

        return view('authkit::password-reset.token', [
            'email' => $email,
            'driver' => (string) config('authkit.password_reset.driver', 'link'),
        ]);
    }
}