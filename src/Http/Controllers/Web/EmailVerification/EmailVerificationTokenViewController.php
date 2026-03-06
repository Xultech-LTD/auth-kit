<?php

namespace Xul\AuthKit\Http\Controllers\Web\EmailVerification;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * EmailVerificationTokenViewController
 *
 * Renders the token-based email verification page.
 *
 * This page is used when the email verification driver is configured to "token".
 * The page is stateless and does not assume an authenticated user.
 *
 * Access is protected by EnsurePendingEmailVerificationMiddleware which must confirm
 * that the provided email has a pending verification presence.
 */
final class EmailVerificationTokenViewController
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

        return view('authkit::email-verification.token', [
            'email' => $email,
        ]);
    }
}