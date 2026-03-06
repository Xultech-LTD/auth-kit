<?php

namespace Xul\AuthKit\Http\Controllers\Web\PasswordReset;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * ForgotPasswordViewController
 *
 * Renders the "forgot password" page.
 *
 * This page allows a guest user to request a password reset email.
 * The actual reset request (sending email/token/link) is handled by API routes.
 */
final class ForgotPasswordViewController
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return View
     */
    public function __invoke(Request $request): View
    {
        return view('authkit::password-reset.forgot', [
            'driver' => (string) config('authkit.password_reset.driver', 'link'),
            'email' => mb_strtolower(trim((string) $request->query('email', ''))),
        ]);
    }
}